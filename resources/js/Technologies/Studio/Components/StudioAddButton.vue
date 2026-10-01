<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Plus, ChevronDown, Check } from 'lucide-vue-next'
import { useStudioContentProcess } from '../Composables/useStudioContentProcess'
import { storeToRefs } from 'pinia'

/**
 * Step 3: StudioAddButton (The Smart Trigger) 🖱️
 */
const props = defineProps({
    type: { type: String, required: true }, // book, manuscript, audio, video
    slug: { type: String, required: true },
    visualMap: { type: Object, default: () => ({}) },
    contextData: { type: Object, default: () => ({ currentTime: 0, currentFolio: 0, lastMarker: 0 }) }
})

const emit = defineEmits(['insert-node'])

const orchestrator = useStudioContentProcess()
const { mediaDuration, currentMedia } = storeToRefs(orchestrator.mediaStore)
const isOpen = ref(false)
const selectedType = ref(null)
const nodeTitle = ref('')
const nodeTimeSeconds = ref(0) // Raw seconds
const formattedNodeTime = ref('00:00') // Display format

const allowedTypes = computed(() => {
    return Object.keys(props.visualMap).map(key => ({
        id: key,
        ...props.visualMap[key]
    }))
})

const toggleDropdown = () => {
    isOpen.value = !isOpen.value
    if (isOpen.value) {
        selectedType.value = null
        nodeTitle.value = ''
        nodeTimeSeconds.value = 0
        formattedNodeTime.value = '00:00'
    }
}

const selectType = (type) => {
    selectedType.value = type
    
    // Auto-complete logic
    if (props.type === 'audio' || props.type === 'video') {
        const seconds = Math.floor(props.contextData.currentTime)
        nodeTimeSeconds.value = seconds
        formattedNodeTime.value = orchestrator.mediaStore.formatTime(seconds)
        nodeTitle.value = `${type.label} عند ${formattedNodeTime.value}`
    } else if (props.type === 'manuscript' && (type.id === 'folio' || type.id === 'page')) {
        nodeTitle.value = `${type.label} ${props.contextData.currentFolio + 1}`
    } else {
        nodeTitle.value = `${type.label} جديد`
    }
}

const parsedTime = computed(() => orchestrator.mediaStore.parseTime(formattedNodeTime.value))
const maxDuration = computed(() => mediaDuration.value || currentMedia.value?.duration || 0)
const isTimeInvalid = computed(() => {
    if (props.type !== 'audio' && props.type !== 'video') return false
    if (!selectedType.value) return false
    return parsedTime.value > maxDuration.value
})

const containerRef = ref(null)

const handleClickOutside = (event) => {
    if (isOpen.value && containerRef.value && !containerRef.value.contains(event.target)) {
        isOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const handleSubmit = () => {
    if (!selectedType.value || !nodeTitle.value || isTimeInvalid.value) return
    
    let finalTime = null
    if (props.type === 'audio' || props.type === 'video') {
        finalTime = parsedTime.value
    }
    
    emit('insert-node', {
        type: selectedType.value.id,
        title: nodeTitle.value,
        time: finalTime
    })
    
    isOpen.value = false
}
</script>

<template>
    <div ref="containerRef" class="relative inline-block text-right rtl">
        <!-- Main Trigger -->
        <button 
            @click="toggleDropdown"
            dusk="studio-add-button"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-md text-xs font-bold transition-all shadow-lg shadow-emerald-900/20 active:scale-95"
            title="إضافة عنصر هيكلي"
        >
            <Plus class="w-3.5 h-3.5" />
            <span>إضافة</span>
            <ChevronDown class="w-3 h-3 opacity-70" />
        </button>

        <!-- Dropdown Menu -->
        <div 
            v-if="isOpen"
            dusk="studio-add-dropdown"
            class="absolute right-0 mt-2 w-56 bg-zinc-900 border border-zinc-800 rounded-lg shadow-2xl z-[100] overflow-hidden"
        >
            <div v-if="!selectedType" class="py-1">
                <div class="px-3 py-2 text-[10px] uppercase tracking-wider text-zinc-500 font-bold">اختر نوع العنصر</div>
                <button
                    v-for="type in allowedTypes"
                    :key="type.id"
                    :dusk="`type-option-${type.id}`"
                    @click.stop="selectType(type)"
                    class="w-full h-9 px-3 flex items-center justify-between hover:bg-zinc-800 text-zinc-300 text-xs transition-colors"
                >
                    <span>{{ type.label }}</span>
                    <span class="text-[9px] opacity-40 px-1.5 py-0.5 border border-zinc-700 rounded">{{ type.tag }}</span>
                </button>
            </div>

            <!-- Entry Form (Step 3/10 Flow) -->
            <div v-else class="p-3 bg-zinc-800/50">
                <div class="text-[10px] text-emerald-400 font-bold mb-2">إضافة {{ selectedType.label }}</div>
                
                <!-- Unified Input Box -->
                <div 
                    class="flex items-center bg-zinc-900 border rounded overflow-hidden mb-1 transition-colors"
                    :class="[
                        isTimeInvalid ? 'border-red-500 bg-red-500/5' : 'border-zinc-700 focus-within:border-emerald-500'
                    ]"
                >
                    <!-- Title Part (Right in RTL) -->
                    <input 
                        v-model="nodeTitle"
                        dusk="node-title-input"
                        type="text"
                        class="flex-1 bg-transparent px-2.5 py-2 text-xs text-white focus:outline-none placeholder-zinc-600 min-w-0"
                        placeholder="العنوان..."
                        autoFocus
                        @keyup.enter="handleSubmit"
                    />

                    <!-- Time Part (Left in RTL Suffix) -->
                    <div v-if="type === 'audio' || type === 'video'" class="flex items-center bg-zinc-800/30 px-2 border-r border-zinc-800 h-9 shrink-0 gap-1.5">
                        <span class="text-[9px] text-zinc-500 select-none font-bold" :class="{'text-red-400': isTimeInvalid}">في</span>
                        <input 
                            v-model="formattedNodeTime"
                            dusk="node-time-input"
                            type="text"
                            class="w-16 bg-transparent text-[10px] font-mono focus:outline-none text-center"
                            :class="isTimeInvalid ? 'text-red-400' : 'text-emerald-400'"
                            placeholder="00:00"
                            title="الوقت (00:00:00)"
                        />
                    </div>
                </div>

                <div v-if="isTimeInvalid" 
                     class="text-[9px] text-red-400 mb-2 font-bold px-1 bg-red-950/20 border border-red-500/30 py-1 rounded-md text-center">
                    ⚠️ الوقت يتجاوز مدة الملف (الحد الأقصى: {{ orchestrator.mediaStore.formatTime(maxDuration) }})
                </div>

                <div class="flex gap-2">
                    <button 
                        @click="handleSubmit"
                        dusk="studio-add-submit"
                        :disabled="isTimeInvalid"
                        class="flex-1 text-white text-[10px] font-bold py-1.5 rounded transition-all"
                        :class="[
                            isTimeInvalid ? 'bg-zinc-700 cursor-not-allowed opacity-50' : 'bg-emerald-600 hover:bg-emerald-500'
                        ]"
                    >
                        تأكيد الإضافة
                    </button>
                    <button 
                        @click="selectedType = null"
                        class="bg-zinc-700 hover:bg-zinc-600 text-white text-[10px] font-bold px-3 py-1.5 rounded transition-colors"
                    >
                        رجوع
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rtl { direction: rtl; }
</style>
