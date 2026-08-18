<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ setting: { type: Object, required: true } });

const platforms = [
  { key: 'android', label: 'Android', modeKey: 'androidMode' },
  { key: 'ios', label: 'iOS', modeKey: 'iosMode' },
];

const form = useForm({
  androidMode: props.setting.androidMode,
  iosMode: props.setting.iosMode,
  confirmProduction: false,
});
const activePlatformKey = ref(null);
const confirmationStep = ref(false);

watch(() => props.setting, setting => {
  form.androidMode = setting.androidMode;
  form.iosMode = setting.iosMode;
  form.confirmProduction = false;
}, { deep: true });

const activePlatform = computed(() => platforms.find(platform => platform.key === activePlatformKey.value) ?? null);
const activeMode = computed(() => activePlatform.value ? form[activePlatform.value.modeKey] : null);
const currentActiveMode = computed(() => activePlatform.value ? props.setting[activePlatform.value.modeKey] : null);
const hasActiveChange = computed(() => !!activePlatform.value && activeMode.value !== currentActiveMode.value);

const modeLabel = mode => mode === 'production' ? 'Production' : 'Test';
const modeDescription = mode => mode === 'production'
  ? 'Gerçek AdMob birimleri kullanılır ve uygun gösterimler gelir üretir.'
  : 'Google’ın resmî demo birimleri kullanılır; gelir oluşmaz.';
const platformHistory = platform => props.setting.platforms?.[platform.key] ?? {
  mode: props.setting[platform.modeKey],
  updatedAt: props.setting.updatedAt,
  updatedBy: props.setting.updatedBy || 'Sistem',
  configurationVersion: props.setting.configurationVersion,
};

const openEditor = platform => {
  form.clearErrors();
  form.androidMode = props.setting.androidMode;
  form.iosMode = props.setting.iosMode;
  form.confirmProduction = false;
  confirmationStep.value = false;
  activePlatformKey.value = platform.key;
};

const closeEditor = () => {
  if (form.processing) return;
  confirmationStep.value = false;
  activePlatformKey.value = null;
  form.clearErrors();
};

const requestSave = () => {
  form.clearErrors();
  if (!hasActiveChange.value || form.processing) return;
  if (currentActiveMode.value !== 'production' && activeMode.value === 'production') {
    confirmationStep.value = true;
    return;
  }
  submit(false);
};

const submit = confirmProduction => {
  form.confirmProduction = confirmProduction;
  form.patch('/admin/advertising-runtime', {
    preserveScroll: true,
    onSuccess: closeEditor,
    onFinish: () => { form.confirmProduction = false; },
  });
};
</script>

<template>
  <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">AdMob çalışma ortamı</p>
        <h2 class="mt-1 text-lg font-semibold text-slate-950">Reklam çalışma modları</h2>
        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">Android ve iOS ortamlarını ayrı satırlardan güvenli biçimde yönet. Değişiklik backend üzerinden uygulanır; yeni mobil build gerekmez.</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-right">
        <p class="text-xs font-semibold text-slate-500">Genel yapılandırma</p>
        <p class="mt-0.5 text-sm font-semibold text-slate-950">#{{ setting.configurationVersion }}</p>
      </div>
    </header>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-5 py-3">Platform</th>
            <th class="px-5 py-3">Çalışma modu</th>
            <th class="px-5 py-3">Reklam kullanımı</th>
            <th class="px-5 py-3">Son düzenleme</th>
            <th class="px-5 py-3">Değiştiren</th>
            <th class="px-5 py-3 text-right">İşlem</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-for="platform in platforms" :key="platform.key" class="align-middle">
            <td class="px-5 py-4">
              <p class="font-semibold text-slate-950">{{ platform.label }}</p>
              <p class="mt-1 text-xs text-slate-500">Yapılandırma #{{ platformHistory(platform).configurationVersion }}</p>
            </td>
            <td class="px-5 py-4">
              <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', platformHistory(platform).mode === 'production' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900']">
                {{ modeLabel(platformHistory(platform).mode) }}
              </span>
            </td>
            <td class="max-w-sm px-5 py-4 text-xs leading-5 text-slate-600">{{ modeDescription(platformHistory(platform).mode) }}</td>
            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-600">{{ platformHistory(platform).updatedAt ? $adminDate(platformHistory(platform).updatedAt) : 'İlk sistem ayarı' }}</td>
            <td class="whitespace-nowrap px-5 py-4 text-xs font-medium text-slate-700">{{ platformHistory(platform).updatedBy || 'Sistem' }}</td>
            <td class="px-5 py-4 text-right">
              <button type="button" class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-xs font-semibold text-emerald-800 transition hover:border-emerald-400 hover:bg-emerald-50" @click="openEditor(platform)">Düzenle</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <div v-if="activePlatform" class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/50 p-4 backdrop-blur-sm" @click.self="closeEditor" @keydown.esc.window="closeEditor">
    <section role="dialog" aria-modal="true" aria-labelledby="admob-mode-title" class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
      <template v-if="!confirmationStep">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">{{ activePlatform.label }}</p>
        <h2 id="admob-mode-title" class="mt-1 text-xl font-semibold text-slate-950">Reklam çalışma modunu düzenle</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Yalnızca {{ activePlatform.label }} reklam ortamı değiştirilecek. Diğer platformun ayarı aynı kalır.</p>

        <div class="mt-5 grid grid-cols-2 rounded-xl bg-slate-100 p-1" role="radiogroup" :aria-label="`${activePlatform.label} reklam modu`">
          <button v-for="mode in ['test', 'production']" :key="mode" type="button" role="radio" :aria-checked="activeMode === mode" :class="['h-11 rounded-lg text-sm font-semibold transition', activeMode === mode ? 'bg-white text-emerald-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900']" @click="form[activePlatform.modeKey] = mode">
            {{ modeLabel(mode) }}
          </button>
        </div>

        <div :class="['mt-4 rounded-xl border px-4 py-3 text-sm leading-6', activeMode === 'production' ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950']">
          {{ modeDescription(activeMode) }}
        </div>
        <p v-if="form.errors[`${activePlatform.key}Mode`]" class="mt-3 text-xs font-semibold text-red-700">{{ form.errors[`${activePlatform.key}Mode`] }}</p>
        <p v-if="form.errors.confirmation" class="mt-3 text-xs font-semibold text-red-700">{{ form.errors.confirmation }}</p>

        <div class="mt-6 flex justify-end gap-2">
          <button type="button" :disabled="form.processing" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700" @click="closeEditor">Vazgeç</button>
          <button type="button" :disabled="!hasActiveChange || form.processing" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" @click="requestSave">{{ form.processing ? 'Kaydediliyor…' : 'Değişikliği kaydet' }}</button>
        </div>
      </template>

      <template v-else>
        <div class="grid size-11 place-items-center rounded-xl bg-amber-100 text-amber-900"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.7 20h18.6L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg></div>
        <h2 class="mt-4 text-xl font-semibold text-slate-950">{{ activePlatform.label }} gerçek reklamlara geçirilsin mi?</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ activePlatform.label }} için gerçek AdMob birimleri kullanılacak. Uygun gösterimler gelir üretir; diğer platformun modu değişmez.</p>
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Açık reklam alanlarında gerçek birim kimliği eksikse sistem geçişi güvenli şekilde reddeder.</div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" :disabled="form.processing" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700" @click="confirmationStep = false">Geri dön</button>
          <button type="button" :disabled="form.processing" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white disabled:opacity-50" @click="submit(true)">{{ form.processing ? 'Kaydediliyor…' : 'Production’ı etkinleştir' }}</button>
        </div>
      </template>
    </section>
  </div>
</template>