<script setup>
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({ settings: { type: Array, required: true } });
const editing = ref(null);
const pending = ref(false);
const errors = ref({});
const form = reactive({
  enabled: false,
  androidEnabled: true,
  iosEnabled: true,
  firstAfter: 0,
  repeatEvery: 0,
  maxPerSession: 1,
  minItems: 0,
  adMobAndroidUnitId: '',
  adMobIosUnitId: '',
  boostHours: 24,
  dailyLimit: 3,
  ordinalsText: '2, 4',
  usageRewards: [],
});

const pageSettings = computed(() => props.settings.filter(item => item.kind === 'native'));
const singleNativePlacements = new Set([
  'leaderboard',
  'listing_detail',
  'public_profile',
  'transaction_detail',
  'profile_home',
  'usage_limits',
]);
const nativeMaximum = item => singleNativePlacements.has(item?.key) ? 1 : 5;
const maximumFor = item => item?.kind === 'native' ? nativeMaximum(item) : 1000;
const isSingleNativePlacement = item => item?.kind === 'native' && nativeMaximum(item) === 1;

const actionSettings = computed(() => props.settings.filter(item => ['interstitial', 'rewarded'].includes(item.kind)));

const displayRule = item => {
  if (item.key === 'rewarded_extra_rights') return `${item.usageRewards.filter(reward => reward.enabled).length}/${item.usageRewards.length} ek hak açık`;
  if (item.kind === 'rewarded') return `${item.boostHours} saat öne çıkarma · 24 saatte ${item.dailyLimit} kullanım`;
  if (item.kind === 'interstitial') return `${(item.ordinals || []).join(', ')}. alım taleplerinden sonra`;
  if (item.repeatEvery > 0) return `${item.firstAfter}. içerikten sonra, her ${item.repeatEvery} içerikte`;
  if (item.firstAfter > 0) return `${item.firstAfter}. içerikten sonra tek reklam`;
  return item.locationLabel;
};

const unitLabel = value => value || 'Tanımlı değil';
const platformLabel = item => {
  if (!item.enabled) return 'Kapalı';
  const platforms = [];
  if (item.androidEnabled) platforms.push('Android');
  if (item.iosEnabled) platforms.push('iOS');
  return platforms.length ? platforms.join(' + ') : 'Platform kapalı';
};

const open = item => {
  editing.value = item;
  errors.value = {};
  Object.assign(form, {
    enabled: item.enabled,
    androidEnabled: item.enabled ? Boolean(item.androidEnabled) : false,
    iosEnabled: item.enabled ? Boolean(item.iosEnabled) : false,
    firstAfter: item.firstAfter,
    repeatEvery: item.repeatEvery,
    maxPerSession: Math.min(item.maxPerSession, maximumFor(item)),
    minItems: item.minItems,
    adMobAndroidUnitId: item.adMobAndroidUnitId || '',
    adMobIosUnitId: item.adMobIosUnitId || '',
    boostHours: item.boostHours,
    dailyLimit: item.dailyLimit,
    ordinalsText: (item.ordinals || []).join(', '),
    usageRewards: (item.usageRewards || []).map(reward => ({ ...reward })),
  });
};

watch(() => form.enabled, enabled => {
  if (!enabled) {
    form.androidEnabled = false;
    form.iosEnabled = false;
    return;
  }
  if (!form.androidEnabled && !form.iosEnabled) {
    form.androidEnabled = true;
    form.iosEnabled = true;
  }
});

watch(() => form.maxPerSession, value => {
  if (!editing.value) return;
  const maximum = maximumFor(editing.value);
  if (isSingleNativePlacement(editing.value) || Number(value) > maximum) {
    form.maxPerSession = maximum;
  }
});

const close = () => { if (!pending.value) editing.value = null; };
const save = () => {
  if (!editing.value || pending.value) return;
  pending.value = true;
  errors.value = {};
  const ordinals = form.ordinalsText.split(',').map(value => Number(value.trim())).filter(value => Number.isInteger(value) && value > 0);
  router.patch(`/admin/advertisement-placements/${editing.value.id}`, { ...form, ordinals }, {
    preserveScroll: true,
    onSuccess: () => { editing.value = null; },
    onError: value => { errors.value = value; },
    onFinish: () => { pending.value = false; },
  });
};
</script>

<template>
  <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <header class="border-b border-slate-200 px-5 py-4">
      <h2 class="text-lg font-semibold text-slate-950">Reklam alanları</h2>
      <p class="mt-1 text-sm text-slate-600">Uygulamadaki AdMob reklam kartlarını ve gösterim yoğunluğunu tek yerden yönet. Sponsorlu banner kampanyaları aşağıdaki ayrı bölümden yönetilir.</p>
    </header>
    <div class="overflow-x-auto">
      <table class="w-full min-w-[1120px] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-700">
          <tr>
            <th class="px-5 py-3.5">Reklam alanı</th>
            <th class="px-5 py-3.5">Durum</th>
            <th class="px-5 py-3.5">Platform</th>
            <th class="px-5 py-3.5">Gösterim düzeni</th>
            <th class="px-5 py-3.5">AdMob birimleri</th>
            <th class="px-5 py-3.5 text-right">İşlem</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="item in pageSettings" :key="item.key">
            <td class="px-5 py-4 font-semibold text-slate-950">{{ item.label }}</td>
            <td class="px-5 py-4"><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', item.enabled ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700']">{{ item.enabled ? 'Açık' : 'Kapalı' }}</span></td>
            <td class="px-5 py-4 text-slate-700">{{ platformLabel(item) }}</td>
            <td class="px-5 py-4 text-slate-700">{{ displayRule(item) }}</td>
            <td class="px-5 py-4 text-xs text-slate-600"><div><span class="font-semibold text-slate-700">Android:</span> <span class="font-mono">{{ unitLabel(item.adMobAndroidUnitId) }}</span></div><div class="mt-1"><span class="font-semibold text-slate-700">iOS:</span> <span class="font-mono">{{ unitLabel(item.adMobIosUnitId) }}</span></div></td>
            <td class="px-5 py-4 text-right"><button class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-800 hover:border-emerald-500" @click="open(item)">Düzenle</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="mt-5">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="text-lg font-semibold text-slate-950">Sayfa dışı reklamlar</h2>
      <p class="mt-1 text-sm text-slate-600">Geçiş ve ödüllü video kurallarını yönet.</p>
      <div class="mt-4 divide-y divide-slate-100">
        <div v-for="item in actionSettings" :key="item.key" class="flex items-center justify-between gap-4 py-3">
          <div>
            <p class="font-semibold text-slate-950">{{ item.label }}</p>
            <p class="text-xs text-slate-600">{{ displayRule(item) }} · {{ platformLabel(item) }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', item.enabled ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700']">{{ item.enabled ? 'Açık' : 'Kapalı' }}</span>
            <button class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold" @click="open(item)">Düzenle</button>
          </div>
        </div>
      </div>
    </article>
  </section>

  <div v-if="editing" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="close" @keydown.esc.window="close">
    <section class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
      <header class="border-b border-slate-200 px-6 py-5"><h2 class="text-xl font-semibold text-slate-950">{{ editing.label }}</h2><p class="mt-1 text-sm text-slate-600">Değişiklikler kaydedildiğinde mobil uygulamaya doğrudan uygulanır.</p></header>
      <form class="overflow-y-auto p-6" @submit.prevent="save">
        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold"><span>Alan durumu</span><input v-model="form.enabled" type="checkbox" class="size-5" /></label>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label :class="['flex items-center justify-between rounded-xl border px-4 py-3 text-sm font-semibold', form.enabled ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50 text-slate-400']"><span>Android’de yayınla</span><input v-model="form.androidEnabled" :disabled="!form.enabled" type="checkbox" class="size-5" /></label>
          <label :class="['flex items-center justify-between rounded-xl border px-4 py-3 text-sm font-semibold', form.enabled ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50 text-slate-400']"><span>iOS’ta yayınla</span><input v-model="form.iosEnabled" :disabled="!form.enabled" type="checkbox" class="size-5" /></label>
        </div>
        <p class="mt-2 text-xs leading-5 text-slate-500">Alan durumu kapalıysa Android ve iOS şalterleri otomatik kapalı kaydedilir.</p>

        <template v-if="editing.kind === 'native'">
          <div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="text-xs font-semibold">İlk reklam<input v-model.number="form.firstAfter" type="number" min="0" class="mt-1 h-11 w-full rounded-xl border px-3" /></label><label class="text-xs font-semibold">Tekrarlama aralığı<input v-model.number="form.repeatEvery" type="number" min="0" class="mt-1 h-11 w-full rounded-xl border px-3" /></label><label class="text-xs font-semibold">Maksimum reklam<input v-model.number="form.maxPerSession" type="number" min="1" class="mt-1 h-11 w-full rounded-xl border px-3" /></label><label class="text-xs font-semibold">Minimum içerik<input v-model.number="form.minItems" type="number" min="0" class="mt-1 h-11 w-full rounded-xl border px-3" /></label></div>
        </template>

        <template v-if="editing.key === 'listing_rewarded_boost'"><div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="text-xs font-semibold">Öne çıkarma süresi<input v-model.number="form.boostHours" type="number" min="1" class="mt-1 h-11 w-full rounded-xl border px-3" /></label><label class="text-xs font-semibold">24 saatte kullanım<input v-model.number="form.dailyLimit" type="number" min="1" class="mt-1 h-11 w-full rounded-xl border px-3" /></label></div></template>

        <template v-if="editing.key === 'rewarded_extra_rights'">
          <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">Her satır bağımsızdır. Kazanılan haklar kullanıldıkça azalır; kullanılmayan bakiye geçerlilik süresi sonunda silinir. Yüklenemeyen veya tamamlanmayan reklamlar günlük sınıra eklenmez.</div>
          <div class="mt-4 space-y-3">
            <article v-for="reward in form.usageRewards" :key="reward.key" class="rounded-2xl border border-slate-200 p-4">
              <div class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold text-slate-950">{{ reward.label }}</p><p class="mt-1 text-xs text-slate-500">{{ reward.unit }}</p></div><label class="flex items-center gap-2 text-xs font-semibold"><span>{{ reward.enabled ? 'Açık' : 'Kapalı' }}</span><input v-model="reward.enabled" type="checkbox" class="size-5" /></label></div>
              <div class="mt-4 grid gap-3 sm:grid-cols-3"><label class="text-xs font-semibold">Reklam başına ek hak<input v-model.number="reward.amount" type="number" min="1" max="100" class="mt-1 h-10 w-full rounded-xl border px-3" /></label><label class="text-xs font-semibold">24 saatte tamamlanan reklam<input v-model.number="reward.dailyLimit" type="number" min="1" max="100" class="mt-1 h-10 w-full rounded-xl border px-3" /></label><label class="text-xs font-semibold">Kullanılmayan hakkın geçerliliği<input v-model.number="reward.validHours" type="number" min="1" max="720" class="mt-1 h-10 w-full rounded-xl border px-3" /><span class="mt-1 block font-normal text-slate-500">saat</span></label></div>
            </article>
          </div>
        </template>

        <template v-if="editing.kind === 'interstitial'"><label class="mt-5 block text-xs font-semibold">Reklam gösterilecek talep sıraları<input v-model="form.ordinalsText" placeholder="2, 4" class="mt-1 h-11 w-full rounded-xl border px-3" /><span class="mt-1 block font-normal text-slate-500">Virgülle ayır: 1, 2, 3</span></label></template>
        <div class="mt-5 grid gap-4"><label class="text-xs font-semibold">AdMob Android reklam birimi<input v-model="form.adMobAndroidUnitId" class="mt-1 h-11 w-full rounded-xl border px-3 font-mono text-xs" /></label><label class="text-xs font-semibold">AdMob iOS reklam birimi<input v-model="form.adMobIosUnitId" placeholder="iOS yayını öncesinde eklenecek" class="mt-1 h-11 w-full rounded-xl border px-3 font-mono text-xs" /></label></div>
        <p v-if="Object.keys(errors).length" class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ Object.values(errors)[0] }}</p>
        <footer class="mt-6 flex justify-end gap-2 border-t pt-5"><button type="button" class="h-11 rounded-xl border px-5 text-sm font-semibold" @click="close">Vazgeç</button><button :disabled="pending" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white disabled:opacity-50">{{ pending ? 'Kaydediliyor…' : 'Değişiklikleri kaydet' }}</button></footer>
      </form>
    </section>
  </div>
</template>