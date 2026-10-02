<template>
  <AuthenticatedLayout title="إضافة مساهم جديد">
    <template #header>
      <div class="flex items-center gap-4">
        <Link
          :href="route('bookers.index')"
          class="p-2 text-gray-400 hover:text-cyan-600 hover:bg-cyan-50 dark:hover:bg-cyan-500/10 rounded-xl transition-all"
        >
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          ><path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M15 19l-7-7 7-7"
          /></svg>
        </Link>
        <div>
          <h2 class="font-black text-2xl dark:text-white leading-tight text-cyan-600">
            إضافة مساهم جديد
          </h2>
          <p class="text-[10px] text-gray-400 font-bold mt-1 uppercase tracking-widest">
            إضافة محقق، مترجم، أو مدقق جديد
          </p>
        </div>
      </div>
    </template>

    <div class="max-w-3xl mx-auto py-8">
      <Card>
        <form
          class="space-y-8"
          @submit.prevent="form.post(route('bookers.store'))"
        >
          <div class="grid grid-cols-1 gap-8">
            <!-- Name -->
            <div class="space-y-2">
              <InputLabel
                for="name"
                value="اسم المساهم"
              />
              <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="w-full"
                placeholder="مثال: د. أحمد المحقق، مركز الترجمة..."
                required
              />
              <p
                v-if="form.errors.name"
                class="text-xs text-rose-500 font-bold"
              >
                {{ form.errors.name }}
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100 dark:border-white/5">
            <Link
              :href="route('bookers.index')"
              class="px-6 py-3 rounded-xl border border-gray-200 dark:border-white/10 text-gray-500 hover:bg-gray-50 dark:hover:bg-white/5 text-xs font-bold transition-all"
            >
              إلغاء
            </Link>
            <PrimaryButton
              :class="{ 'opacity-25': form.processing }"
              :disabled="form.processing"
              class="!bg-cyan-600 hover:!bg-cyan-500 !shadow-cyan-500/20"
            >
              حفظ المساهم
            </PrimaryButton>
          </div>
        </form>
      </Card>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const form = useForm({
    name: '',
});
</script>
