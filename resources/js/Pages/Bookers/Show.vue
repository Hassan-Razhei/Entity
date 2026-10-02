<template>
  <AuthenticatedLayout :title="'مساهم: ' + booker.name">
    <template #header>
      <div class="relative overflow-hidden bg-cyan-700 rounded-[2.5rem] p-12 text-white shadow-2xl shadow-cyan-900/40">
        <div class="relative flex flex-col md:flex-row justify-between items-start md:items-center gap-8">
          <div class="flex items-center gap-6">
            <div class="w-20 h-20 rounded-3xl bg-white/10 backdrop-blur-xl flex items-center justify-center text-4xl border border-white/20 shadow-inner">
              <svg
                class="w-10 h-10 text-cyan-200"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              ><path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
              /></svg>
            </div>
            <div>
              <h1 class="text-4xl font-black tracking-tight">
                {{ booker.name }}
              </h1>
              <p class="mt-2 text-cyan-100/80 text-sm font-bold">
                مساهم ومحقق في المحتوى العلمي
              </p>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <Link
              :href="route('bookers.edit', booker.id)"
              class="px-6 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-black text-xs backdrop-blur-xl border border-white/20 transition-all shadow-lg active:scale-95"
            >
              تعديل المساهم
            </Link>
            <Link
              :href="route('bookers.index')"
              class="px-6 py-3 rounded-2xl bg-white text-cyan-900 font-black text-xs transition-all shadow-lg active:scale-95 hover:bg-cyan-50"
            >
              العودة للقائمة
            </Link>
          </div>
        </div>
      </div>
    </template>

    <div class="max-w-7xl mx-auto py-8 space-y-8">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-[#0a0a0a] border border-gray-100 dark:border-white/5 rounded-3xl p-6 text-center">
          <p class="text-xs text-gray-400 font-bold">الكتب</p>
          <p class="text-3xl font-black text-gray-900 dark:text-white mt-2">{{ booker.books_count ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-[#0a0a0a] border border-gray-100 dark:border-white/5 rounded-3xl p-6 text-center">
          <p class="text-xs text-gray-400 font-bold">الصوتيات</p>
          <p class="text-3xl font-black text-gray-900 dark:text-white mt-2">{{ booker.audios_count ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-[#0a0a0a] border border-gray-100 dark:border-white/5 rounded-3xl p-6 text-center">
          <p class="text-xs text-gray-400 font-bold">المرئيات</p>
          <p class="text-3xl font-black text-gray-900 dark:text-white mt-2">{{ booker.videos_count ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-[#0a0a0a] border border-gray-100 dark:border-white/5 rounded-3xl p-6 text-center">
          <p class="text-xs text-gray-400 font-bold">المخطوطات</p>
          <p class="text-3xl font-black text-gray-900 dark:text-white mt-2">{{ booker.manuscripts_count ?? 0 }}</p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    booker: Object,
});
</script>
