import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useEditorStore = defineStore('editor', () => {
    // State
    const currentEntity = ref(null)
    const currentContentNode = ref(null)
    const content = ref('')
    const isToolbarPinned = ref(false)
    const editorMode = ref('book')
    const isSaving = ref(false)
    const lastSaved = ref(null)
    const lastSaveMessage = ref(null) // NEW: Capture backend message
    const editor = ref(null)
    const resourceData = ref(null)
    const hierarchy = ref([])
    const navigation = ref({ prev: null, next: null })
    const contentVersion = ref(0)

    // Getters
    const documentTitle = computed(() => currentContentNode.value?.title || 'مستند جديد')

    const hasUnsavedChanges = computed(() => {
        return content.value !== (currentContentNode.value?.content || '')
    })

    // Actions
    const setEditor = (editorInstance) => {
        editor.value = editorInstance
    }

    const loadDocument = (entity, contentNode, hierarchyData = [], navigationData = {}) => {
        currentEntity.value = entity
        const nodeContent = contentNode?.content !== undefined 
            ? contentNode.content 
            : (contentNode?.content_html ?? contentNode?.html_content ?? '')
        currentContentNode.value = contentNode ? {
            ...contentNode,
            content: nodeContent
        } : null
        content.value = nodeContent
        hierarchy.value = hierarchyData
        navigation.value = navigationData
        contentVersion.value = 0
    }

    const updateContent = (newContent) => {
        content.value = newContent
        contentVersion.value++
    }

    const togglePin = () => {
        isToolbarPinned.value = !isToolbarPinned.value
    }

    const setEditorMode = (mode) => {
        editorMode.value = mode
        resourceData.value = null
    }

    const setResourceData = (data) => {
        resourceData.value = data
    }

    const setTitle = (title) => {
        if (currentContentNode.value) currentContentNode.value.title = title
    }

    const addMediaNode = () => {
        if (!Array.isArray(content.value)) {
            content.value = []
        }

        if (editorMode.value === 'audio') {
            content.value = [...content.value, {
                id: Date.now(),
                startTime: '00:00',
                endTime: '00:00',
                label: 'مقطع جديد',
                text: ''
            }]
        } else if (editorMode.value === 'video') {
            content.value = [...content.value, {
                id: Date.now(),
                timestamp: '00:00',
                title: 'مشهد جديد',
                description: ''
            }]
        }
    }

    const removeMediaNode = (index) => {
        if (!Array.isArray(content.value)) return
        const newContent = [...content.value]
        newContent.splice(index, 1)
        content.value = newContent
    }

    const executeCommand = (command, value = null) => {
        if (!editor.value) return

        const chain = editor.value.chain().focus()

        const commands = {
            bold: () => chain.toggleBold().run(),
            italic: () => chain.toggleItalic().run(),
            underline: () => chain.toggleUnderline().run(),
            heading: () => chain.toggleHeading({ level: value }).run(),
            bulletList: () => chain.toggleBulletList().run(),
            orderedList: () => chain.toggleOrderedList().run(),
            strike: () => chain.toggleStrike().run(),
            undo: () => chain.undo().run(),
            redo: () => chain.redo().run(),
            alignRight: () => chain.setTextAlign('right').run(),
            alignCenter: () => chain.setTextAlign('center').run(),
            alignLeft: () => chain.setTextAlign('left').run(),
            alignJustify: () => chain.setTextAlign('justify').run(),
            insertHeritagePoetry: () => chain.setHeritagePoetry().run(),
            insertQuranicVerse: () => chain.setQuranicVerse().run(),
        }

        if (commands[command]) {
            commands[command]()
        } else if (command === 'textAlign') {
            chain.setTextAlign(value).run()
        }
    }

    const isActive = (name, attributes = {}) => {
        if (!editor.value) return false
        return editor.value.isActive(name, attributes)
    }

    const save = async () => {
        if (!currentContentNode.value || !currentEntity.value) {
            console.error('[EditorStore] Cannot save: missing entity or content node')
            return false
        }

        isSaving.value = true

        try {
            const childId = currentContentNode.value.id === 'full' ? 'full' : (currentContentNode.value._id || currentContentNode.value.id)

            // Sync latest content from editor if active
            if (editor.value) {
                content.value = editor.value.getHTML()
            }

            const payload = {
                child_id: childId,
                title: currentContentNode.value.title || '',
                content: content.value,
                html_content: content.value,
                plain_text: editor.value?.getText() || '',
                json_content: editor.value?.getJSON() || null
            }

            // --- SMART SPLITTING FOR FULL VIEW ---
            if (childId === 'full' && editor.value) {
                const doc = editor.value.state.doc
                const segments = []
                let currentSegment = null

                doc.forEach((node, offset, index) => {
                    // Precision Detection: Top-level paragraph containing a segmentLink mark anywhere in its children
                    let segmentMark = null
                    node.content?.forEach(child => {
                        if (child.marks?.some(m => m.type.name === 'segmentLink')) {
                            segmentMark = child.marks.find(m => m.type.name === 'segmentLink')
                        }
                    })

                    const isHeader = node.type.name === 'paragraph' && segmentMark !== null

                    if (isHeader) {
                        console.log('[EditorStore] Found segment header:', node.textContent, 'marks detected');
                        currentSegment = {
                            id: segmentMark.attrs.segmentId || segmentMark.attrs.id,
                            title: node.textContent.trim().replace(/:$/, ''),
                            nodes: []
                        }
                        segments.push(currentSegment)
                    } else if (currentSegment) {
                        currentSegment.nodes.push(node)
                    }
                })

                payload.segments = segments.map(seg => ({
                    id: seg.id,
                    title: seg.title,
                    // Send as a proper Tiptap/ProseMirror content array
                    json: seg.nodes.map(node => node.toJSON())
                }))
            }

            const response = await axios.post(
                route('studio.save', {
                    type: editorMode.value,
                    slug: currentEntity.value.slug,
                    childId: childId
                }),
                payload
            )

            lastSaved.value = new Date(response.data.last_saved || new Date())
            lastSaveMessage.value = response.data.message || 'تم الحفظ بنجاح'

            // Sync local node state so hasUnsavedChanges immediately becomes false
            if (currentContentNode.value) {
                currentContentNode.value.content = content.value
                currentContentNode.value.content_html = content.value
                currentContentNode.value.html_content = content.value
            }

            // Also update node in currentEntity.children
            if (currentEntity.value?.children && childId !== 'full') {
                const targetChild = currentEntity.value.children.find(c => 
                    (c.id === childId || c._id === childId || c.slug === childId)
                )
                if (targetChild) {
                    targetChild.title = currentContentNode.value.title
                    targetChild.content = content.value
                    targetChild.content_html = content.value
                    targetChild.html_content = content.value
                }
            }

            console.log('[EditorStore] Content saved successfully:', lastSaveMessage.value)
            return true
        } catch (error) {
            console.error('[EditorStore] Save failed:', error)
            alert('فشل الحفظ: ' + (error.response?.data?.message || error.message))
            return false
        } finally {
            isSaving.value = false
        }
    }

    const insertNode = (type, title, metadata = {}) => {
        if (!editor.value) return

        const visualMap = resourceData.value?.visual_map || {}
        const config = visualMap[type] || { tag: 'h4', behavior: 'container' }
        const tag = config.tag || 'h4'
        const level = parseInt(tag.replace('h', '')) || 4

        if (config.behavior === 'container') {
            // 1. Container Behavior (Standard Heading)
            editor.value.commands.insertStructureNode(type, title, level)
        } else {
            // 2. Marker Behavior (Heading with Metadata)
            editor.value.commands.insertMarkerNode(type, title, {
                ...metadata,
                level
            })
        }
    }

    return {
        currentEntity, currentContentNode, content, isToolbarPinned,
        editorMode, resourceData, isSaving, lastSaved, lastSaveMessage, editor,
        hierarchy, navigation,
        documentTitle, hasUnsavedChanges,
        setEditor, loadDocument, updateContent, togglePin,
        executeCommand, isActive,
        setEditorMode, setResourceData, setTitle,
        addMediaNode, removeMediaNode,
        insertNode,
        save,
        contentVersion
    }
})
