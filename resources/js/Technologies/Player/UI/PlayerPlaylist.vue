<script setup>
import { Check, Search, Minus, X, Play, FileAudio, Bookmark, ChevronDown, ChevronRight, Plus, Edit3, Trash2 } from 'lucide-vue-next';
import { ref, nextTick, computed, watch, onMounted } from 'vue';

const props = defineProps({
    title: String,
    segments: { type: Array, default: () => [] },
    activeSlug: String,
    currentTime: { type: Number, default: 0 }, // Added for Add Segment
    duration: { type: Number, default: 0 } // Media duration for validation
});

const emit = defineEmits(['select', 'close', 'add', 'delete', 'update', 'commit-add', 'navigate-full']); // Added 'commit-add'

const isSegmentsExpanded = ref(true);
const scrollContainer = ref(null);
const rootElement = ref(null);
const segmentElements = ref([]);

const isArabic = (text) => {
    if (!text) return true;
    const arabicPattern = /[\u0600-\u06FF]/;
    return arabicPattern.test(text);
};

const titleDirection = computed(() => isArabic(props.title) ? 'rtl' : 'ltr');

const scrollToActive = () => {
    nextTick(() => {
        let target = null;
        if (!props.activeSlug) {
            target = rootElement.value;
        } else {
            // Find the active segment element
            const activeIndex = props.segments.findIndex(s => s.slug === props.activeSlug);
            if (activeIndex !== -1 && segmentElements.value[activeIndex]) {
                target = segmentElements.value[activeIndex];
            }
        }

        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
                inline: 'nearest'
            });
        }
    });
};

watch(() => props.activeSlug, () => {
    scrollToActive();
});

onMounted(() => {
    scrollToActive();
});

// --- Editing Logic ---
const editingSlug = ref(null);
const editingTitle = ref('');
const editingTime = ref('');
const titleInput = ref(null);
const editErrorMsg = ref('');

const startEditing = (seg) => {
    editingSlug.value = seg.slug;
    editingTitle.value = seg.label || seg.title || '';
    editingTime.value = formatTime(seg.start || 0);
    editErrorMsg.value = '';
    nextTick(() => {
        if (titleInput.value && titleInput.value[0]) {
            titleInput.value[0].focus();
        }
    });
};

const cancelEditing = () => {
    editingSlug.value = null;
    editingTitle.value = '';
    editingTime.value = '';
    editErrorMsg.value = '';
};

const saveEditing = () => {
    if (!editingSlug.value) return;
    
    editErrorMsg.value = '';
    
    // Find segment
    const seg = props.segments.find(s => s.slug === editingSlug.value);
    if (!seg) return;
    
    // Validate time if changed
    const newSeconds = parseTimeString(editingTime.value);
    if (isNaN(newSeconds)) {
        editErrorMsg.value = 'صيغة الوقت غير صحيحة';
        return;
    }
    
    // Validate time doesn't exceed duration
    if (props.duration > 0 && newSeconds > props.duration) {
        const maxTime = formatTime(props.duration);
        editErrorMsg.value = `يتجاوز مدة الملف (${maxTime})`;
        return;
    }
    
    // Check if anything changed
    const titleChanged = editingTitle.value.trim() !== (seg.title || seg.label || '');
    const timeChanged = Math.abs(newSeconds - (seg.start || 0)) > 0.1; // Allow small floating point differences
    
    if (titleChanged || timeChanged) {
        emit('update', { 
            ...seg, 
            title: editingTitle.value.trim() || seg.title,
            start: newSeconds
        });
    }
    
    cancelEditing();
};

const startEditingActive = () => {
    if (!props.activeSlug) return;
    const seg = props.segments.find(s => s.slug === props.activeSlug);
    if (seg) startEditing(seg);
};

defineExpose({ startEditingActive });

// V1 Parity: Support hours
const formatTime = (seconds) => {
    if (!seconds || isNaN(seconds)) return "00:00";
    const date = new Date(seconds * 1000);
    const hh = date.getUTCHours();
    const mm = date.getUTCMinutes();
    const ss = date.getUTCSeconds().toString().padStart(2, '0');
    return hh ? `${hh}:${mm.toString().padStart(2, '0')}:${ss}` : `${mm}:${ss}`;
};

// --- Add Segment Logic (Touch #25 - Playlist Integration) ---
const isAdding = ref(false);
const newTitle = ref('');
const newTime = ref('');
const capturedSeconds = ref(0);
const manualInputRef = ref(null);
const errorMsg = ref('');

const parseTimeString = (str) => {
    // Support formats: HH:MM:SS, MM:SS (unlimited minutes), or just seconds
    // Examples: 02:00:00, 120:00, 1:30, 90
    const regex = /^(\d+):(\d{1,2}):(\d{1,2})$|^(\d+):(\d{1,2})$|^(\d+)$/;
    if (!regex.test(str)) return NaN;
    
    const parts = str.split(':').map(Number);
    
    if (parts.length === 3) {
        // HH:MM:SS format
        const [hours, minutes, seconds] = parts;
        if (minutes >= 60 || seconds >= 60) return NaN;
        return (hours * 3600) + (minutes * 60) + seconds;
    }
    
    if (parts.length === 2) {
        // MM:SS format (minutes can be unlimited, like 120:00)
        const [minutes, seconds] = parts;
        if (seconds >= 60) return NaN;
        return (minutes * 60) + seconds;
    }
    
    // Just seconds
    return parts[0] || 0;
};

const openAddUI = (seconds) => {
    capturedSeconds.value = seconds;
    newTitle.value = '';
    newTime.value = formatTime(seconds);
    errorMsg.value = '';
    isAdding.value = true;
    nextTick(() => {
        if (manualInputRef.value) manualInputRef.value.focus();
    });
};

const cancelAdd = () => {
    isAdding.value = false;
    errorMsg.value = '';
};

const saveQuick = () => {
    errorMsg.value = '';
    const title = newTitle.value.trim() || `مقطع عند ${formatTime(capturedSeconds.value)}`;
    emit('commit-add', { 
        start: capturedSeconds.value, 
        title: title
    });
    isAdding.value = false;
};

const saveManual = () => {
    errorMsg.value = '';
    const seconds = parseTimeString(newTime.value);
    
    // Validate time format
    if (isNaN(seconds)) {
        errorMsg.value = 'صيغة الوقت غير صحيحة (مثال: 1:30 أو 0:45)';
        return;
    }
    
    // Validate time doesn't exceed duration
    if (props.duration > 0 && seconds > props.duration) {
        const maxTime = formatTime(props.duration);
        errorMsg.value = `الوقت يتجاوز مدة الملف (الحد الأقصى: ${maxTime})`;
        return;
    }
    
    const title = newTitle.value.trim() || `مقطع عند ${formatTime(seconds)}`;
    emit('commit-add', { 
        start: seconds, 
        title: title
    });
    isAdding.value = false;
};

const handleAddClick = () => {
    emit('add'); // Trigger pause in parent
    nextTick(() => {
        openAddUI(props.currentTime);
    });
};
</script>

<template>
    <div class="playlist w-[180px] bg-[#0a0a0a] border-l border-[#222] flex flex-col h-full overflow-hidden text-[#aaa] font-sans shadow-2xl" dir="ltr">
        <!-- Minimal Window Controls (Transparent) -->
        <div class="pl-header h-[20px] flex items-center justify-between px-2 shrink-0 mt-1">
            <!-- Left: Segment Controls (Touch #25) -->
            <div class="flex items-center gap-1 transition-opacity duration-300" :class="{ 'opacity-30 pointer-events-none': isAdding }">
                <button class="text-lime-400 hover:text-lime-300 transition-transform active:scale-95" title="Add Segment" @click="handleAddClick">
                    <Plus class="w-3 h-3" stroke-width="2.5" />
                </button>
                <button 
                    class="text-yellow-400 hover:text-yellow-300 disabled:text-gray-600 transition-transform active:scale-95 disabled:cursor-not-allowed" 
                    title="Edit Segment Title" 
                    :disabled="!activeSlug"
                    @click="startEditingActive"
                >
                    <Edit3 class="w-3 h-3" stroke-width="2.5" />
                </button>
                <button 
                    class="text-red-400 hover:text-red-300 disabled:text-gray-600 transition-transform active:scale-95 disabled:cursor-not-allowed" 
                    title="Delete Segment" 
                    :disabled="!activeSlug"
                    @click="$emit('delete', { slug: activeSlug })"
                >
                    <Trash2 class="w-3 h-3" stroke-width="2.5" />
                </button>
            </div>

            <!-- Right: Window Controls -->
            <div class="flex items-center gap-2">
                <Minus class="w-3 h-3 cursor-pointer text-gray-400 hover:text-white transition-colors" stroke-width="2.5" />
                <X class="w-3 h-3 cursor-pointer text-red-500 hover:text-red-400 transition-colors" stroke-width="2.5" @click="$emit('close')" />
            </div>
        </div>

        <!-- Add Segment Form (Touch #25 - Optimized Fit) -->
        <transition name="fade">
            <div v-if="isAdding" class="px-1.5 py-1 border-b border-white/[0.03] bg-[#141414]" dir="rtl">
                <div class="flex gap-1 items-center">
                    <!-- Title Input -->
                    <input 
                        ref="manualInputRef"
                        v-model="newTitle"
                        placeholder="عنوان.."
                        class="flex-1 min-w-0 text-[10px] bg-transparent border-b border-white/10 pb-0.5 text-white placeholder-gray-600 focus:outline-none focus:border-lime-400/50 transition-colors"
                        @keyup.enter="saveManual"
                    />
                    <!-- Time Input -->
                    <input 
                        v-model="newTime"
                        placeholder="00:00"
                        class="w-9 text-[8px] bg-transparent border-b border-white/10 pb-0.5 text-lime-400 placeholder-lime-400/40 text-center font-mono focus:outline-none focus:border-lime-400/50 transition-colors"
                        @keyup.enter="saveManual"
                    />
                    <!-- Save Button -->
                    <button 
                        class="text-lime-400 hover:text-lime-300 transition-colors shrink-0"
                        @click="saveManual"
                        title="حفظ"
                    >
                        <Check class="w-2.5 h-2.5 stroke-[3]" />
                    </button>
                    <!-- Cancel Button -->
                    <button 
                        class="text-red-400 hover:text-red-300 transition-colors shrink-0"
                        @click="cancelAdd"
                        title="إلغاء"
                    >
                        <X class="w-2.5 h-2.5 stroke-[3]" />
                    </button>
                </div>
                <!-- Error Message (Smart Alert - Touch #25) -->
                <transition name="error-fade">
                    <div v-if="errorMsg" class="mt-1 px-2 py-1 rounded-md bg-red-950/50 border border-red-500/30">
                        <div class="text-[9px] text-red-400 text-center font-bold tracking-wide animate-pulse" 
                             style="text-shadow: 0 0 8px rgba(239, 68, 68, 0.8), 0 0 12px rgba(239, 68, 68, 0.5);">
                            ⚠️ {{ errorMsg }}
                        </div>
                    </div>
                </transition>
            </div>
        </transition>

        <!-- Fixed Media Root (Always Phosphorescent Green + Compact Toggle) -->
        <div 
            class="flex items-center gap-2 py-2 px-3 border-b border-[#222] transition-all bg-white/[0.03] text-lime-400"
        >
            <span 
                class="text-[10px] truncate tracking-tight uppercase flex-1 cursor-pointer hover:text-white transition-colors font-bold" 
                :class="[isArabic(title) ? 'text-right' : 'text-left']"
                style="text-shadow: 0 0 12px rgba(163, 230, 53, 0.6)"
                title="عرض كامل المحتوى"
                @click="$emit('navigate-full')"
            >
                {{ title || 'Original Full View' }}
            </span>
            
            <!-- Toggle Icon (Smart Position) -->
            <div 
                class="shrink-0 flex items-center justify-center w-4 h-4 hover:bg-white/5 rounded transition-colors ml-1 cursor-pointer"
                @click="isSegmentsExpanded = !isSegmentsExpanded"
            >
                <component 
                    :is="isSegmentsExpanded ? ChevronDown : (isArabic(title) ? ChevronRight : ChevronRight)" 
                    class="w-3 h-3"
                    :class="[
                        !activeSlug ? 'text-lime-400' : 'text-blue-400',
                        !isSegmentsExpanded && isArabic(title) ? 'rotate-180' : ''
                    ]"
                />
            </div>
        </div>

        <!-- Scrollable Tree Content -->
        <div ref="scrollContainer" class="flex-1 overflow-y-auto custom-scrollbar pt-0 pb-2 relative">
            <!-- Level 1: Segments (Phosphorescent Blue | Green when Active) -->
            <div v-if="isSegmentsExpanded" class="flex flex-col relative ml-3 pl-0.5">
                <div 
                    v-for="(seg, i) in (segments || [])" 
                    :key="seg.slug || i"
                    ref="segmentElements"
                    class="item px-2 py-0.5 mb-0.5 rounded-l-md cursor-pointer group relative transition-all"
                    :class="[seg.slug === activeSlug ? 'text-lime-400 font-bold' : 'text-blue-400 font-bold hover:text-blue-300']"
                    @click="editingSlug !== seg.slug ? $emit('select', seg) : null"
                >
                    <!-- Editing Mode -->
                    <div v-if="editingSlug === seg.slug" class="flex flex-col gap-1 py-1" dir="rtl" @click.stop>
                        <!-- Title Input -->
                        <input 
                            ref="titleInput"
                            v-model="editingTitle"
                            @keyup.enter="saveEditing"
                            @keyup.esc="cancelEditing"
                            class="text-[10px] bg-[#222] text-white border border-lime-500/30 py-1 px-2 rounded w-full focus:ring-1 focus:ring-lime-500 focus:outline-none font-sans"
                            placeholder="العنوان"
                        />
                        <!-- Time Input -->
                        <input 
                            v-model="editingTime"
                            @keyup.enter="saveEditing"
                            @keyup.esc="cancelEditing"
                            class="text-[9px] bg-[#222] text-lime-400 border border-lime-500/30 py-1 px-2 rounded w-full focus:ring-1 focus:ring-lime-500 focus:outline-none font-mono text-center"
                            placeholder="00:00"
                        />
                        <!-- Error Message -->
                        <transition name="error-fade">
                            <div v-if="editErrorMsg" class="text-[8px] text-red-400 text-center font-bold animate-pulse" 
                                 style="text-shadow: 0 0 6px rgba(239, 68, 68, 0.8);">
                                ⚠️ {{ editErrorMsg }}
                            </div>
                        </transition>
                        <!-- Action Buttons -->
                        <div class="flex gap-1 justify-center">
                            <button 
                                class="text-lime-400 hover:text-lime-300 transition-colors px-2 py-0.5 rounded bg-lime-500/10 hover:bg-lime-500/20"
                                @click="saveEditing"
                                title="حفظ"
                            >
                                <Check class="w-3 h-3 stroke-[2.5]" />
                            </button>
                            <button 
                                class="text-red-400 hover:text-red-300 transition-colors px-2 py-0.5 rounded bg-red-500/10 hover:bg-red-500/20"
                                @click="cancelEditing"
                                title="إلغاء"
                            >
                                <X class="w-3 h-3 stroke-[2.5]" />
                            </button>
                        </div>
                    </div>

                    <!-- Display Mode -->
                    <div v-else class="flex items-center justify-between min-w-0 gap-2 w-full" dir="rtl">
                        <!-- Label Part (Leads for the eye) -->
                         <div class="min-w-0 flex-1 text-right">
                            <span 
                                class="text-[10px] truncate block transition-colors"
                                :style="seg.slug === activeSlug ? 'text-shadow: 0 0 12px rgba(163, 230, 53, 0.6)' : ''"
                                @dblclick.stop="startEditing(seg)"
                                :title="seg.label || seg.title"
                            >
                                <bdi dir="auto">{{ seg.label || seg.title }}</bdi>
                            </span>
                         </div>

                         <!-- Time Part (Follows immediately) -->
                        <span 
                            class="text-[8px] shrink-0 font-mono" 
                            dir="ltr"
                            :class="[
                                seg.slug === activeSlug ? 'text-lime-400 font-bold' : 'text-blue-400'
                            ]"
                            :style="seg.slug === activeSlug ? 'text-shadow: 0 0 10px rgba(163, 230, 53, 0.5)' : ''"
                        >
                            {{ formatTime(seg.start || 0) }}
                        </span>
                    </div>
                </div>

                <div v-if="(segments || []).length === 0" class="p-6 text-center text-[9px] text-[#444] italic font-sans border-t border-white/[0.03] mt-1">
                    No segments
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.1);
}

.fade-enter-active, .fade-leave-active {
    transition: none;
    transform: none;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

.error-fade-enter-active, .error-fade-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.error-fade-enter-from, .error-fade-leave-to {
    opacity: 0;
    transform: translateY(-5px) scale(0.95);
}
</style>
