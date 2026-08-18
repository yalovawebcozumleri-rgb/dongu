<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ setting: { type: Object, required: true } });

const form = useForm({
  androidMode: props.setting.androidMode,
  iosMode: props.setting.iosMode,
  confirmProduction: false,
});
const confirmationOpen = ref(false);

watch(() => props.setting, setting => {
  form.androidMode = setting.androidMode;
  form.iosMode = setting.iosMode;
  form.confirmProduction = false;
}, { deep: true });

const hasChanges = computed(() => form.androidMode !== props.setting.androidMode || form.iosMode !== props.setting.iosMode);
const productionPlatforms = computed(() => [
  form.androidMode !== props.setting.androidMode && form.androidMode === 'production' ? 'Android' : null,
  form.iosMode !== props.setting.iosMode && form.iosMode === 'production' ? 'iOS' : null,
].filter(Boolean));

const modeLabel = mode => mode === 'production' ? 'Production' : 'Test';
const modeDescription = mode => mode === 'production'
  ? 'Gerçek AdMob birimleri kullanılır ve uygun gösterimler gelir üretir.'
  : 'Google’ın resmî demo birimleri kullanılır; gelir oluşmaz.';

const requestSave = () => {
  form.clearErrors();
  if (!hasChanges.value || form.processing) return;
  if (productionPlatforms.value.length) {
    confirmationOpen.value = true;
    return;
  }
  submit(false);
};

const submit = confirmProduction => {
  form.confirmProduction = confirmProduction;
  form.patch('/admin/advertising-runtime', {
    preserveScroll: true,
    onSuccess: () => { confirmationOpen.value = false; },
    onFinish: () => { form.confirmProduction = false; },
  });
};
</script>

<template>
  <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">AdMob çalışma ortamı</p>
        <h2 class="mt-1 text-lg font-semibold text-slate-950">Reklam çalışma modu</h2>
        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">Android ve iOS için test veya gerçek reklam kullanımını bağımsız yönet. Değişiklik backend üzerinden uygulanır; yeni mobil build gerekmez.</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-right">
        <p class="text-xs font-semibold text-slate-500">Yapılandırma sürümü</p>
        <p class="mt-0.5 text-sm font-semibold text-slate-950">#{{ setting.configurationVersion }}</p>
      </div>
    </header>

    <div class="grid gap-4 p-5 lg:grid-cols-2">
      <article v-for="platform in [{ key: 'android', label: 'Android' }, { key: 'ios', label: 'iOS' }]" :key="platform.key" class="rounded-2xl border border-slate-200 p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h3 class="font-semibold text-slate-950">{{ platform.label }}</h3>
            <p class="mt-1 text-xs text-slate-600">Mevcut: {{ modeLabel(setting[`${platform.key}Mode`]) }}</p>
          </div>
          <span :class="['rounded-full px-3 py-1 text-xs font-semibold', form[`${platform.key}Mode`] === 'production' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900']">
            {{ modeLabel(form[`${platform.key}Mode`]) }}
          </span>
        </div>
        <div class="mt-4 grid grid-cols-2 rounded-xl bg-slate-100 p-1" role="radiogroup" :aria-label="`${platform.label} reklam modu`">
          <button v-for="mode in ['test', 'production']" :key="mode" type="button" role="radio" :aria-checked="form[`${platform.key}Mode`] === mode" :class="['h-10 rounded-lg text-sm font-semibold transition', form[`${platform.key}Mode`] === mode ? 'bg-white text-emerald-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900']" @click="form[`${platform.key}Mode`] = mode">
            {{ modeLabel(mode) }}
          </button>
        </div>
        <p class="mt-3 text-xs leading-5 text-slate-600">{{ modeDescription(form[`${platform.key}Mode`]) }}</p>
        <p v-if="form.errors[`${platform.key}Mode`]" class="mt-2 text-xs font-semibold text-red-700">{{ form.errors[`${platform.key}Mode`] }}</p>
      </article>
    </div>

    <footer class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 bg-slate-50 px-5 py-4">
      <div class="text-xs leading-5 text-slate-600">
        <p><strong class="font-semibold text-slate-800">Son değişiklik:</strong> {{ setting.updatedAt ? $adminDate(setting.updatedAt) : 'İlk sistem ayarı' }}</p>
        <p><strong class="font-semibold text-slate-800">Değiştiren:</strong> {{ setting.updatedBy || 'Sistem' }}</p>
      </div>
      <button type="button" :disabled="!hasChanges || form.processing" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" @click="requestSave">
        {{ form.processing ? 'Kaydediliyor…' : 'Değişiklikleri kaydet' }}
      </button>
      <p v-if="form.errors.confirmation" class="w-full text-right text-xs font-semibold text-red-700">{{ form.errors.confirmation }}</p>
    </footer>
  </section>

  <div v-if="confirmationOpen" class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/50 p-4 backdrop-blur-sm" @click.self="!form.processing && (confirmationOpen = false)" @keydown.esc.window="!form.processing && (confirmationOpen = false)">
    <section role="alertdialog" aria-modal="true" aria-labelledby="admob-production-title" class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
      <div class="grid size-11 place-items-center rounded-xl bg-amber-100 text-amber-900"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.7 20h18.6L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg></div>
      <h2 id="admob-production-title" class="mt-4 text-xl font-semibold text-slate-950">Gerçek reklamlar etkinleştirilsin mi?</h2>
      <p class="mt-2 text-sm leading-6 text-slate-600"><strong class="font-semibold text-slate-900">{{ productionPlatforms.join(' ve ') }}</strong> için gerçek AdMob birimleri kullanılacak. Uygun reklam gösterimleri gelir üretebilir ve değişiklik mobil uygulamalara backend üzerinden uygulanır.</p>
      <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Açık reklam alanlarında gerçek birim kimliği eksikse sistem production geçişini güvenli şekilde reddeder.</div>
      <div class="mt-6 flex justify-end gap-2">
        <button type="button" :disabled="form.processing" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700" @click="confirmationOpen = false">Vazgeç</button>
        <button type="button" :disabled="form.processing" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white disabled:opacity-50" @click="submit(true)">{{ form.processing ? 'Kaydediliyor…' : 'Production’ı etkinleştir' }}</button>
      </div>
    </section>
  </div>
</template>
