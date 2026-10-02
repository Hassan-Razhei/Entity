import { ref, onUnmounted } from 'vue'
import { useEditorStore } from '@/Technologies/Store/EditorStore'

export function useEditorSave() {
    const store = useEditorStore()
    const autoSaveInterval = ref(null)

    const save = async () => {
        return await store.save()
    }

    const startAutoSave = () => {
        if (autoSaveInterval.value) clearInterval(autoSaveInterval.value)
        autoSaveInterval.value = setInterval(() => {
            if (store.hasUnsavedChanges) {
                save()
            }
        }, 30000)
    }

    const stopAutoSave = () => {
        if (autoSaveInterval.value) {
            clearInterval(autoSaveInterval.value)
            autoSaveInterval.value = null
        }
    }

    onUnmounted(() => {
        stopAutoSave()
    })

    return {
        save,
        startAutoSave,
        stopAutoSave
    }
}
