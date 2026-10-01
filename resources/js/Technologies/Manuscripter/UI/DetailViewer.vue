<script setup>
import { ref, computed } from 'vue'
import ResourceNavigator from '@/Technologies/Common/ResourceNavigator.vue'

defineOptions({
  name: 'DetailViewer'
})

const props = defineProps(['resource', 'currentNode'])

// Simplified store-less state for this standalone/secondary viewer for now
// As per spec, this is a "Secondary Component" often used in isolation
// but we can start standardizing it. For now, porting logic directly.

const zoomLevel = ref(1.0)
const isPanning = ref(false)
const panStart = ref({ x: 0, y: 0 })
const scrollStart = ref({ left: 0, top: 0 })

// Versions Logic
const versions = computed(() => {
    // 1. Priority: Current Page Image
    const rawUrl = props.currentNode?.image_url || props.currentNode?.metadata?.image_url || props.resource?.file_path || props.resource?.cover_path
    if (rawUrl) {
        const formattedUrl = (rawUrl.startsWith('http://') || rawUrl.startsWith('https://') || rawUrl.startsWith('/') || rawUrl.startsWith('blob:'))
            ? rawUrl 
            : '/storage/' + rawUrl
        return [{
            id: 'node-view',
            title: props.currentNode?.title || 'المعاينة الحالية',
            url: formattedUrl
        }]
    }
    // 2. Fallback: Saved Versions
    if (!props.resource?.versions || props.resource.versions.length === 0) {
        return []
    }
    return props.resource.versions
})

const activeVersionIndex = ref(0)
const displayedVersion = computed(() => versions.value[activeVersionIndex.value])

// Zoom Controls
const zoomIn = () => zoomLevel.value = Math.min(zoomLevel.value + 0.1, 3)
const zoomOut = () => zoomLevel.value = Math.max(zoomLevel.value - 0.1, 0.5)
const resetZoom = () => zoomLevel.value = 1.0

// Panning Logic
const handlePanStart = (e) => {
    isPanning.value = true
    panStart.value = { x: e.clientX, y: e.clientY }
    const target = e.currentTarget
    scrollStart.value = { left: target.scrollLeft, top: target.scrollTop }
}

const handlePanMove = (e) => {
    if (!isPanning.value) return
    const dx = e.clientX - panStart.value.x
    const dy = e.clientY - panStart.value.y
    const target = e.currentTarget
    target.scrollLeft = scrollStart.value.left - dx
    target.scrollTop = scrollStart.value.top - dy
}

const handlePanEnd = () => {
    isPanning.value = false
}
</script>

<template>
  <div class="h-full bg-gray-50 flex flex-col border-l border-gray-200 overflow-hidden text-right" dir="rtl">
    <!-- Glass Header -->
    <div class="glass-header flex items-center justify-between border-b border-gray-200 px-2 z-10 sticky top-0 h-10">
      <div class="flex items-center gap-4 overflow-hidden">
        <div class="border-l border-gray-200 pl-4 ml-2">
            <!-- Mock ResourceNavigator integration or import if needed -->
             <ResourceNavigator
                type="manuscript"
                :current-id="resource?.id"
             />
        </div>
        
        <div v-if="resource?.title" class="text-sm font-bold text-gray-700 whitespace-nowrap flex items-center">
            {{ resource?.title }}
        </div>
      </div>
    </div>

    <!-- Viewer Content -->
    <div 
      class="flex-1 bg-gray-200 overflow-auto relative h-full custom-scrollbar cursor-grab active:cursor-grabbing select-none"
      @mousedown="handlePanStart"
      @mousemove="handlePanMove"
      @mouseup="handlePanEnd"
      @mouseleave="handlePanEnd"
    >
        <div
            class="flex flex-col items-center justify-start min-h-full transition-all duration-300 mx-auto pointer-events-none p-10"
            :style="{ width: (zoomLevel * 100) + '%', minWidth: '100%' }"
        >
            <div v-if="displayedVersion?.url" class="relative inline-block w-full text-center">
                <img
                  :src="displayedVersion.url"
                  class="inline-block max-w-full shadow-2xl rounded-sm border-4 border-gray-800"
                >
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="absolute bottom-6 left-6 flex flex-col gap-2 z-30">
        <button class="w-10 h-10 rounded-full bg-white/80 backdrop-blur shadow-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:text-blue-500 transition-all hover:scale-110" @click="resetZoom">
            <i class="fas fa-expand" />
        </button>
        <button class="w-10 h-10 rounded-full bg-white/80 backdrop-blur shadow-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:text-blue-500 transition-all hover:scale-110" @click="zoomIn">
            <i class="fas fa-plus" />
        </button>
        <button class="w-10 h-10 rounded-full bg-white/80 backdrop-blur shadow-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:text-blue-500 transition-all hover:scale-110" @click="zoomOut">
            <i class="fas fa-minus" />
        </button>
    </div>
  </div>
</template>

<style scoped>
.glass-header {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(12px);
}
</style>
