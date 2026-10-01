<script setup>
import { useManuscriptStore } from '@/Technologies/Store/ManuscriptStore'

const store = useManuscriptStore()

const toggleVersion = (id) => {
    store.toggleVersionSelection(id)
}
</script>

<template>
    <header class="absolute top-0 left-0 right-0 z-10 h-12 px-4 flex items-center justify-between gap-4 transition-all bg-gradient-to-b from-black/60 to-transparent">
        <!-- Right Section: Navigation Tools -->
        <div class="flex items-center gap-4 flex-1 md:flex-initial">
            <!-- Work Title & Code -->
            <div class="flex flex-col text-white/90 mr-2">
                <span class="text-[14px] font-bold truncate max-w-[200px]">{{ store.manuscript?.title }}</span>
                <span class="text-[9px] text-white/40 font-mono tracking-widest uppercase">{{ (store.manuscript?.code?.includes('-') ? store.manuscript.code.split('-').slice(0, -1).join('-') : store.manuscript?.code) }}</span>
            </div>

            <div class="w-px h-6 bg-white/10 mx-2" />

            <!-- Versions / Copies List -->
            <div class="flex items-center gap-1 bg-white/5 rounded-full p-1 border border-white/5 backdrop-blur-sm overflow-hidden">
                <div 
                    v-for="version in store.allVersions" 
                    :key="version.id"
                    class="flex items-center rounded-full transition-all"
                    :class="store.selectedVersionIds.includes(version.id) 
                        ? 'bg-blue-600 text-white shadow-sm pl-1 pr-3 py-1' 
                        : 'px-3 py-1 text-stone-400 hover:text-stone-200 hover:bg-white/5 cursor-pointer'"
                    @click="!store.selectedVersionIds.includes(version.id) ? toggleVersion(version.id) : null"
                >
                    <span 
                        v-if="store.selectedVersionIds.includes(version.id)"
                        class="mr-2 cursor-pointer opacity-80 hover:opacity-100"
                        @click.stop="toggleVersion(version.id)"
                    >✕</span>

                    <span class="text-[11px] font-medium whitespace-nowrap mr-2">{{ version.name }}</span>

                    <input 
                        v-if="store.selectedVersionIds.includes(version.id)"
                        :value="store.getVersionShot(version.id)" 
                        type="number"
                        class="w-10 bg-black/20 border-none rounded px-1 py-0.5 text-[10px] font-mono text-white text-center focus:ring-1 focus:ring-white/30 outline-none"
                        placeholder="#"
                        @input="store.setVersionShot(version.id, $event.target.value)"
                        @click.stop
                    >
                </div>
            </div>

            <div class="w-px h-4 bg-white/10 hidden md:block" />

            <!-- Shot Input -->
            <div class="flex items-center gap-2 group cursor-text">
                <span class="text-[12px] text-stone-400 group-hover:text-stone-200 transition-colors hidden sm:inline">لقطة</span>
                <div class="relative">
                    <input 
                        v-model="store.shotNumber" 
                        type="number"
                        :max="store.totalPages"
                        min="1"
                        class="w-12 text-[14px] font-bold text-stone-200 bg-transparent outline-none p-0 border-b border-white/20 focus:border-blue-400 transition-colors placeholder-stone-600 text-center"
                        placeholder="#"
                    >
                </div>
                <span class="text-[12px] text-stone-500">/ {{ store.totalPages }}</span>
            </div>
        </div>

        <!-- Left Section: View Options -->
        <div class="flex items-center gap-4">
            <!-- View Modes -->
            <div class="flex items-center bg-white/5 rounded-full p-1 border border-white/5 backdrop-blur-sm">
                <button 
                    class="w-8 h-8 flex items-center justify-center rounded-full transition-all"
                    :class="store.viewMode === 'list' ? 'bg-indigo-500 text-white shadow-sm' : 'text-stone-400 hover:text-stone-200 hover:bg-white/5'"
                    title="القراءة العمودية"
                    @click="store.viewMode = 'list'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect width="20" height="12" x="2" y="6" rx="2"/></svg>
                </button>

                <button 
                    class="w-8 h-8 flex items-center justify-center rounded-full transition-all"
                    :class="store.viewMode === 'grid' ? 'bg-indigo-500 text-white shadow-sm' : 'text-stone-400 hover:text-stone-200 hover:bg-white/5'"
                    title="عرض الشبكة"
                    @click="store.viewMode = 'grid'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                </button>

                <button 
                    class="w-8 h-8 flex items-center justify-center rounded-full transition-all"
                    :class="store.viewMode === 'default' ? 'bg-indigo-500 text-white shadow-sm' : 'text-stone-400 hover:text-stone-200 hover:bg-white/5'"
                    title="الفهرس"
                    @click="store.viewMode = 'default'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>
                </button>
            </div>

            <div class="w-px h-4 bg-white/10" />

            <!-- Compare Switcher -->
            <div class="flex items-center gap-3">
                <button 
                    class="text-[13px] transition-colors focus:outline-none"
                    :class="!store.isCompareMode ? 'font-bold text-stone-200' : 'font-medium text-stone-500 hover:text-stone-300'"
                    @click="store.setCompareMode(false)"
                >
                    مفرد
                </button>
                <button 
                    class="text-[13px] transition-colors focus:outline-none"
                    :class="store.isCompareMode ? 'font-bold text-indigo-400' : 'font-medium text-stone-500 hover:text-stone-300'"
                    @click="store.setCompareMode(true)"
                >
                    مقارنة
                </button>
            </div>
        </div>
    </header>
</template>
