<script setup>
import { ref, onUnmounted } from 'vue'
import { useManuscriptStore } from '@/Technologies/Store/ManuscriptStore'
import { useManuscript } from '@/Technologies/Manuscripter/Composables/useManuscript'

const store = useManuscriptStore()
const { parseFilename, calculateResize } = useManuscript()

// Resizing State
const isResizing = ref(false)
const resizeIndex = ref(-1)

const startResizing = (e, index) => {
    isResizing.value = true
    resizeIndex.value = index
    window.addEventListener('mousemove', handleResizeMove)
    window.addEventListener('mouseup', stopResizing)
    document.body.style.cursor = 'col-resize'
    document.body.style.userSelect = 'none'
}

const handleResizeMove = (e) => {
    if (!isResizing.value) return
    const container = document.getElementById('compare-container')
    if (!container) return

    const containerWidth = container.getBoundingClientRect().width
    const idx = resizeIndex.value
    const nextIdx = idx + 1
    
    if (nextIdx >= store.panelWidths.length) return

    const currentWidth = store.panelWidths[idx]
    const nextWidth = store.panelWidths[nextIdx]

    const result = calculateResize(e.movementX, containerWidth, currentWidth, nextWidth)
    
    if (result) {
        store.panelWidths[idx] = result.newCurrent
        store.panelWidths[nextIdx] = result.newNext
    }
}

const stopResizing = () => {
    isResizing.value = false
    resizeIndex.value = -1
    window.removeEventListener('mousemove', handleResizeMove)
    window.removeEventListener('mouseup', stopResizing)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
}

onUnmounted(() => {
    stopResizing()
})
</script>

<template>
    <div
        id="compare-container"
        class="w-full h-full flex overflow-hidden"
    >
        <template
            v-for="(version, idx) in store.displayedVersions"
            :key="version.id"
        >
            <div 
                class="flex flex-col items-center justify-center bg-black/40 overflow-hidden relative border-white/5"
                :class="{'border-l': idx > 0}"
                :style="{ width: store.panelWidths[idx] + '%' }"
            >
                <!-- Top Version Info Badge -->
                <div class="absolute top-14 right-4 z-10 bg-black/70 backdrop-blur-md px-3 py-1 rounded-lg border border-white/10 text-white flex items-center gap-2 pointer-events-none shadow-md">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    <span class="text-xs font-bold">{{ version.name }}</span>
                    <span class="text-[10px] text-white/50 font-mono">لوحة {{ store.getVersionShot(version.id) }}</span>
                </div>

                <!-- Image -->
                <div class="w-full h-full flex items-center justify-center p-4">
                    <img
                        :src="store.getPageUrl(store.getVersionShot(version.id), version)" 
                        class="max-h-full max-w-full object-contain shadow-2xl opacity-90 transition-opacity hover:opacity-100"
                    >
                </div>
                
                <!-- Minimal Filename Overlay -->
                <div class="absolute bottom-4 right-4 bg-black/40 backdrop-blur px-2 py-1 rounded text-white/50 text-[10px] font-mono pointer-events-none">
                    {{ parseFilename(store.getPageUrl(store.getVersionShot(version.id), version)) }}
                </div>
            </div>

            <!-- Resize Handle -->
            <div 
                v-if="idx < store.displayedVersions.length - 1"
                class="w-4 -mr-2 -ml-2 z-50 h-full flex items-center justify-center cursor-col-resize group/handle select-none relative"
                @mousedown.prevent="startResizing($event, idx)"
            >
                <div class="h-full w-[1px] bg-white/10 group-hover/handle:bg-blue-500/50 transition-colors" />
                <div class="absolute w-4 h-8 bg-black/60 border border-white/20 rounded-full flex flex-col items-center justify-center gap-0.5 opacity-0 group-hover/handle:opacity-100 transition-opacity backdrop-blur hover:bg-black/80 hover:border-blue-500/50">
                    <div class="w-0.5 h-0.5 rounded-full bg-white/70" />
                    <div class="w-0.5 h-0.5 rounded-full bg-white/70" />
                    <div class="w-0.5 h-0.5 rounded-full bg-white/70" />
                </div>
            </div>
        </template>
    </div>
</template>
