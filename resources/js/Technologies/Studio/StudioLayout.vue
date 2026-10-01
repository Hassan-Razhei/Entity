<script setup>
import { Head, router } from '@inertiajs/vue3'
import SplitPane from './Layouts/SplitPane.vue'
import ReferencePane from './Panes/ReferencePane.vue'
import EditorPane from './Panes/EditorPane.vue'
import StudioAddButton from './Components/StudioAddButton.vue'
import { useStudioContentProcess } from './Composables/useStudioContentProcess'
import { useEditorStore } from '../Store/EditorStore'
import { useMediaStore } from '@/Technologies/Store/MediaStore'
import { Play } from 'lucide-vue-next'
import { onMounted, onUnmounted, computed, watch, ref, provide, nextTick } from 'vue'

const props = defineProps({
    type: { type: String, required: true }, // 'manuscript' | 'audio' | 'video'
    entity: { type: Object, required: true },
    editorContent: { type: String, default: '' },
    fullContent: { type: String, default: '' }, // Source of truth for full aggregation
    isFullView: { type: Boolean, default: false },
    activeChildId: { type: String, default: null },
    title: { type: String, default: 'Entity Studio' },
    visual_map: { type: Object, default: () => ({}) },
    contentNode: { type: Object, default: null }, // Critical: Added missing prop
    _legacy: { type: Object, default: () => ({}) }
})

const store = useEditorStore()
const mediaStore = useMediaStore()

const isPlayerDocked = ref(true)

const toggleDock = () => {
    isPlayerDocked.value = !isPlayerDocked.value
}

provide('toggleDock', toggleDock)
provide('isPlayerDocked', isPlayerDocked)

const showSplitLayout = computed(() => {
   return props.type === 'manuscript' || isPlayerDocked.value
})

// Load document state
onMounted(() => {
    store.setEditorMode(props.type)
    
    // Step 1: Ensure visual_map is available for node insertion behavior
    if (props.visual_map) {
        store.setResourceData({ visual_map: props.visual_map })
    }
    
    // Step 2: Load document state (Rule: Prefer top-level props over _legacy)
    if (props.isFullView) {
        store.loadDocument(props.entity, { id: 'full', title: 'كامل المحتوى', content: props.editorContent }, [], {})
    } else if (props.contentNode) {
        store.loadDocument(props.entity, props.contentNode, [], {})
        
        // Seek player if applicable
        if (props.contentNode.start_time !== undefined) {
            mediaStore.requestSeek(props.contentNode.start_time);
        }
    } else if (props._legacy?.contentNode) {
        // Fallback for safety during transition
        store.loadDocument(props.entity, props._legacy.contentNode, [], {})
        if (props._legacy.contentNode.start_time !== undefined) {
            mediaStore.requestSeek(props._legacy.contentNode.start_time);
        }
    }
    // TEST HELPER: Global visibility for Dusk (Expose always for now to avoid timing issues)
    window.MediaStore = mediaStore;
    window.EditorStore = store;
    window.addEventListener('test:set-time', (e) => {
        console.log('[StudioLayout] Test Set Time:', e.detail);
        mediaStore.setCurrentTime(e.detail);
    });
})

// Watch for content node changes (when navigating between segments/pages)
watch(() => props.activeChildId, (newId, oldId) => {
    if (newId !== oldId && !props.isFullView) {
        const nodeToLoad = props.contentNode || props._legacy?.contentNode;
        if (nodeToLoad) {
            store.loadDocument(props.entity, nodeToLoad, [], {})
            
            if (nodeToLoad.start_time !== undefined) {
                mediaStore.requestSeek(nodeToLoad.start_time);
            }
        }
    }
})

// SYNC: Watch for deep changes in contentNode (Selective Sync)
watch(() => props._legacy?.contentNode?.title, (newTitle) => {
    if (newTitle && store.currentContentNode && store.currentContentNode.title !== newTitle) {
        console.log('[StudioLayout] Syncing title from props:', newTitle);
        store.currentContentNode.title = newTitle;
    }
});

watch(() => props.editorContent, (newContent) => {
    if (newContent && store.content !== newContent) {
        console.log('[StudioLayout] Syncing content from props (Server Update)');
        store.updateContent(newContent);
    }
});

// Auto-save is now handled by the Page/Composable, not the layout
const saveStatusColor = computed(() => {
    if (store.isSaving) return 'text-yellow-500'
    return 'text-green-500'
})

const saveStatusText = computed(() => {
    if (store.isSaving) return 'جاري الحفظ...'
    return store.lastSaveMessage || 'محفوظ'
})

const navigateToFull = () => {
    // OPTIMIZATION: Client-Side Switch to Full View
    // We now use props.fullContent which is always the full aggregation source!
    console.log('[StudioLayout] Client-side switch to Full View');

    // 1. Load Full Document from the stable source
    store.loadDocument(props.entity, { id: 'full', title: 'كامل المحتوى', content: props.fullContent }, [], {});
    
    // 2. Clear Active Segment
    mediaStore.setActiveSegment(null);

    // 3. Update URL without reload
    const newUrl = route('studio.show', { type: props.type, slug: props.entity.slug });
    window.history.pushState({}, '', newUrl);

    // 4. Reset Scroll
    window.scrollTo({ top: 0, behavior: 'instant' });
}

const navigateToSpecific = () => {
    // If we have a child ID active, do nothing (already there), just open dropdown?
    // User wants to see dropdown ON CLICK.
    // If user clicks button, we toggle dropdown.
    toggleDropdown();
}

// Dropdown Logic
const isDropdownOpen = ref(false)
const searchInput = ref(null);

const toggleDropdown = async () => {
    isDropdownOpen.value = !isDropdownOpen.value;
    
    if (isDropdownOpen.value) {
        searchQuery.value = ''; // Reset search
        await nextTick();
        if (searchInput.value) searchInput.value.focus();
    }
}

const availableNodes = computed(() => {
    // 1. Get nodes from Props (Canonical/Permanent)
    let propNodes = []
    if (props.entity.children && props.entity.children.length > 0) {
        propNodes = props.entity.children.map(c => ({
            id: c._id || c.id,
            slug: c.slug,
            title: c.title || `مقطع #${c.order || '?'}`,
            start: c.start_time !== undefined ? c.start_time : (c.start !== undefined ? c.start : (c.metadata?.start_time !== undefined ? c.metadata.start_time : undefined))
        }))
    } else if (props._legacy && props._legacy.hierarchy) {
        propNodes = props._legacy.hierarchy.map(c => ({
            id: c._id || c.id,
            slug: c.slug,
            title: c.title || 'بدون عنوان',
            start: c.start_time !== undefined ? c.start_time : (c.start !== undefined ? c.start : (c.metadata?.start_time !== undefined ? c.metadata.start_time : undefined))
        }))
    }

    // 2. Get segments from MediaStore (Ephemeral/Active)
    const storeSegments = mediaStore.segments.map(s => ({
        id: s.id || s.slug,
        slug: s.slug,
        title: s.label || s.title || 'مقطع غير مسمى',
        start: s.start,
        content: s.content, // Ensure content is passed if available
        isEphemeral: true
    }));

    // Merge: Store segments take precedence for navigation within current file
    const unified = [...storeSegments];
    
    // Add prop nodes that aren't already represented by slug in store
    propNodes.forEach(pn => {
        if (!unified.find(u => u.slug === pn.slug)) {
            unified.push(pn);
        }
    });

    if (props.type === 'audio' || props.type === 'video') {
        unified.sort((a, b) => (Number(a.start) || 0) - (Number(b.start) || 0));
    }

    if (!searchQuery.value) return unified;

    // Filter by search query
    const lowerQuery = searchQuery.value.toLowerCase();
    return unified.filter(node => 
        (node.title && node.title.toLowerCase().includes(lowerQuery))
    );
})

const searchQuery = ref('');

const navigateToChild = (id) => {
    // Determine if id is a slug or internal ID
    const node = availableNodes.value.find(n => n.id === id || n.slug === id);
    if (!node) {
        console.warn('[StudioLayout] Navigate request failed, node not found:', id);
        return;
    }
    
    navigateToNode(node);
}

const navigateToNode = (node) => {
    isDropdownOpen.value = false
    
    // OPTIMIZATION: Client-Side Switching vs Server Round-Trip
    // If the node has content available, we switch locally!
    if (node.content || node.html_content) {
        console.log('[StudioLayout] Client-side switch to node:', node.title);
        
        // 1. Load into Store
        const content = node.content || node.html_content || '';
        store.loadDocument(props.entity, { 
            id: node.id, 
            slug: node.slug,
            title: node.title, 
            content: content, 
            start_time: node.start
        }, [], {});

        // 2. Seek Player (if has start time)
        if (node.start !== undefined || node.start_time !== undefined) {
             const time = node.start !== undefined ? node.start : node.start_time;
             mediaStore.requestSeek(time);
        }

        // 3. Update URL
        const newUrl = route('studio.show', { type: props.type, slug: props.entity.slug, childId: node._id || node.id || node.slug });
        window.history.pushState({}, '', newUrl);

        // 4. Reset Scroll
        window.scrollTo({ top: 0, behavior: 'instant' });
    } else {  
        // Fallback: Server Round-Trip
        router.visit(route('studio.show', { type: props.type, slug: props.entity.slug, childId: node.id }))
    }
}

const specificNodeTitle = computed(() => {
    if (store.currentContentNode?.id === 'full') return 'عرض مقطع محدد';
    
    // Check if activeSlug or activeChildId matches any of our unified nodes
    const active = availableNodes.value.find(n => 
        (mediaStore.activeSegmentSlug && n.slug === mediaStore.activeSegmentSlug) || 
        (props.activeChildId && (n.id === props.activeChildId || n.slug === props.activeChildId))
    );
    
    return active?.title || store.currentContentNode?.title || props._legacy?.contentNode?.title || 'المقطع الحالي';
})

// --- STEP 3 RE-ALIGNMENT: Context Data ---
const contextData = computed(() => {
    // 1. Current Time (from MediaStore)
    const currentTime = mediaStore.currentTime;

    // 2. Current Folio (for manuscripts)
    let currentFolio = 0;
    if (props.type === 'manuscript') {
        const folios = availableNodes.value
            .filter(n => n.title && (n.title.includes('ورقة') || n.title.includes('لوحة') || n.title.includes('صفحة')))
            .map(n => {
                const match = n.title.match(/\d+/);
                return match ? parseInt(match[0]) : 0;
            });
        currentFolio = folios.length > 0 ? Math.max(...folios) : availableNodes.value.length;
    }

    // 3. Last Marker
    const lastMarker = availableNodes.value.length;

    return { currentTime, currentFolio, lastMarker };
});
// --- STEP 3: STUDIO ADD BUTTON HANDLER ---
const { insertNode } = useStudioContentProcess()
const handleInsertNode = ({ type, title, time }) => {
    insertNode(type, title, time)
}
</script>

<template>
  <Head :title="title" />
  
  <div class="h-screen w-screen overflow-hidden bg-gray-950 flex flex-col font-ui text-right" dir="rtl">
    <!-- 
        Global Studio Header 
        (Top Bar: Navigation, Save, User, etc.)
     -->
    <header class="h-12 bg-[#1e1e1e] border-b border-gray-800 flex items-center justify-between px-4 shrink-0 z-[90]">
      <div class="flex items-center gap-4">
        <!-- Logo / Home -->
        <a href="/dashboard" class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors">
          <div class="w-6 h-6 bg-lime-500 rounded-md flex items-center justify-center text-black font-bold text-xs">
            ES
          </div>
          <span class="font-bold text-sm tracking-wide hidden sm:inline">Entity Studio</span>
        </a>

        <!-- Divider -->
        <div class="h-4 w-px bg-gray-700"></div>

        <!-- Entity Title -->
        <div class="flex items-center gap-2">
            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-800 text-gray-300 border border-gray-700">
                {{ props.type === 'manuscript' ? 'مخطوط' : (props.type === 'audio' ? 'صوت' : 'فيديو') }}
            </span>
            <div class="flex flex-col">
                <h1 class="text-sm font-medium text-white truncate max-w-[200px] md:max-w-md">
                    {{ props.entity.title || props.entity.original_title || 'بدون عنوان' }}
                </h1>
                <span v-if="props.entity.code" class="text-[10px] text-gray-400 font-mono tracking-wider">
                    {{ props.entity.code }}
                </span>
            </div>

            <!-- Step 3: Studio Add Button (Always Visible in Studio) -->
            <StudioAddButton 
                :visual-map="visual_map"
                :type="type"
                :slug="entity.slug"
                :context-data="contextData"
                @insert-node="handleInsertNode"
            />
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3">
        <!-- Dual View Toggle -->
        <div class="hidden md:flex items-center bg-gray-800 rounded-lg p-1 gap-1 border border-gray-700">
            <!-- Full View Button -->
            <button 
                id="studio-full-view-btn"
                @click="navigateToFull"
                :class="[
                    'flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded transition-all',
                    store.currentContentNode?.id === 'full' 
                        ? 'bg-amber-500/10 text-amber-500 font-bold shadow-sm' 
                        : 'text-gray-400 hover:text-white hover:bg-white/5'
                ]"
                title="عرض كافة المحتوى مدمجاً"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                كامل المحتوى
            </button>

            <!-- Divider -->
            <div class="w-px h-3 bg-gray-700"></div>

            <!-- Specific View Group (Button + Dropdown) -->
            <div class="relative flex items-center">
                <button 
                    id="studio-dropdown-btn"
                    @click="toggleDropdown"
                    :class="[
                        'flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded-r transition-all',
                        store.currentContentNode?.id !== 'full' 
                            ? 'bg-blue-500/10 text-blue-400 font-bold shadow-sm' 
                            : 'text-gray-400 hover:text-white hover:bg-white/5'
                    ]"
                    :title="!props.isFullView ? 'أنت في وضع التركيز على هذا المقطع' : 'الانتقال للتركيز على مقطع محدد'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    {{ specificNodeTitle }}
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-gray-500" :class="{'rotate-180': isDropdownOpen}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <!-- Dropdown Menu -->
                <div 
                    v-if="isDropdownOpen"
                    class="absolute top-full right-0 mt-2 w-64 bg-[#1e1e1e] border border-gray-700 rounded-md shadow-xl overflow-hidden z-[60] flex flex-col max-h-[80vh]"
                >
                    <!-- Header / Search -->
                    <div class="px-2 py-2 bg-gray-800 border-b border-gray-700">
                        <input 
                            v-model="searchQuery"
                            ref="searchInput"
                            type="text" 
                            placeholder="ابحث عن مقطع..." 
                            class="w-full bg-gray-900 border border-gray-600 rounded px-2 py-1 text-[11px] text-gray-200 placeholder-gray-500 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none transition-all"
                            @click.stop
                        />
                    </div>

                    <!-- Scrollable List -->
                    <div class="overflow-y-auto flex-1 p-1 custom-scrollbar bg-[#1e1e1e]">
                        <button 
                            v-for="node in availableNodes" 
                            :key="node.slug || node.id"
                            :dusk="'node-item-' + (node.slug || node.id)"
                            @click="navigateToNode(node)"
                            class="w-full text-right px-3 py-2.5 text-xs rounded-lg hover:bg-white/5 flex items-center justify-between gap-3 transition-all group/item border border-transparent"
                            :class="(node.slug === mediaStore.activeSegmentSlug || node.id === props.activeChildId) ? 'bg-amber-500/10 text-amber-500 border-amber-500/20 font-bold' : 'text-gray-300 hover:border-white/5'"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <span v-if="node.start !== undefined" class="font-mono text-[9px] px-1 rounded bg-white/5 text-gray-500 group-hover/item:text-amber-500/70 transition-colors">
                                    {{ mediaStore.formatTime(node.start) }}
                                </span>
                                <span class="truncate">{{ node.title }}</span>
                            </div>
                            
                            <span v-if="node.slug === mediaStore.activeSegmentSlug || node.id === props.activeChildId" class="text-amber-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </span>
                        </button>
                        
                        <div v-if="availableNodes.length === 0" class="text-center py-6 text-gray-600 text-xs italic">
                            لا توجد مقاطع متاحة
                        </div>
                    </div>
                </div>
            
                <!-- Backdrop to close -->
                <div v-if="isDropdownOpen" @click="isDropdownOpen = false" class="fixed inset-0 z-[55] cursor-default"></div>
            </div>
        </div>

        <!-- Save Status -->
        <span 
          id="studio-save-status"
          class="text-[10px] flex items-center gap-1.5 border-r border-gray-800 pr-3 mr-1" 
          :class="saveStatusColor"
        >
            <div class="w-1 h-1 rounded-full bg-current" :class="store.isSaving ? 'animate-pulse' : ''"></div>
            {{ saveStatusText }}
        </span>

        <!-- Open Player Button (Restored) -->
        <button 
          v-if="props.type !== 'manuscript' && !mediaStore.isOpen"
          id="studio-open-player-btn"
          @click="mediaStore.setOpen(true)"
          class="flex items-center justify-center w-8 h-8 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-all shadow-lg shadow-blue-900/20 active:scale-95 ml-2"
          title="فتح المشغل"
        >
          <Play class="w-4 h-4 fill-current" />
        </button>


        <button 
            id="studio-save-btn"
            class="bg-blue-600 hover:bg-blue-500 text-white text-xs px-4 py-1.5 rounded font-bold transition-colors shadow-lg shadow-blue-900/20"
            @click="store.save"
        >
            حفظ
        </button>
      </div>
    </header>

    <!-- 
        Main Workspace (Split Pane) 
        Pane 1 (Right in RTL): Editor (Constant) - Wait, PLAN said Editor is Right Pane.
        Let's check the Plan: "Right Pane (Constant): The Smart Editor... Left Pane (Variable): Chameleon Window".
        In RTL, Right looks like Start.
        So Pane 1 should be Editor.
        Pane 2 should be Reference.
        
        Let's configure SplitPane.vue accordingly.
        SplitPane slots: 'pane-1', 'pane-2'.
        Pane 1 is Start. Pane 2 is End.
    -->
    <!-- 
        Main Workspace 
        - Manuscript: Split Pane (Editor + Reference)
        - Media (Audio/Video): Full Width Editor + Floating Player (Hidden in DOM)
    -->
    <div class="flex-1 overflow-hidden relative">
      
      <!-- Scenario A: Manuscript (Split View) -->
      <SplitPane v-if="props.type === 'manuscript'" :initial-split="40" :min-size="20">
        <template #pane-1>
          <EditorPane 
            :initial-content="props.editorContent" 
            :type="props.type"
            @navigate="navigateToChild"
            @navigate-full="navigateToFull"
            @add-node="handleInsertNode"
          />
        </template>
        <template #pane-2>
          <ReferencePane
            :type="props.type"
            :entity="props.entity" 
            :active-child-id="props.activeChildId"
            @navigate="navigateToChild"
            @navigate-full="navigateToFull"
            @add-node="handleInsertNode"
          />
        </template>
      </SplitPane>

      <!-- Scenario B: Media (Integrated Player inside Editor) -->
      <div v-else class="w-full h-full relative">
          <EditorPane 
            :initial-content="props.editorContent" 
            :media-entity="props.entity"
            :type="props.type"
            @navigate="navigateToChild"
            @navigate-full="navigateToFull"
            @add-node="handleInsertNode"
          />
      </div>

    </div>
  </div>
</template>
