<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import PlacementSettings from './PlacementSettings.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
  campaigns: Object,
  filters: Object,
  counts: Object,
  placementSettings: Array,
  pageSizes: Array,
  placementOptions: Array,
  adMob: Object,
});

const flash = computed(() => usePage().props.flash?.success);
const filter = reactive({ ...props.filters });
const composeOpen = ref(false);
const viewing = ref(null);
const toggleCandidate = ref(null);
const deleteCandidate = ref(null);
const actionPending = ref(false);
const deleteForm = useForm({});

const form = useForm({
  sponsorName: '', headline: '', body: '', format: 'native', image: null,
  ctaLabel: '', targetUrl: '', backgroundColor: '#E8F4E9', startsAt: '', endsAt: '',
  priority: 0, isActive: false, placements: [],
});

const statuses = { active: 'Yayında', scheduled: 'Planlandı', paused: 'Durduruldu', ended: 'Sona erdi' };
const statusClasses = {
  active: 'bg-emerald-50 text-emerald-800',
  scheduled: 'bg-sky-50 text-sky-800',
  paused: 'bg-slate-100 text-slate-700',
  ended: 'bg-amber-50 text-amber-900',
};
const formatLabels = { native: 'Standart reklam kartı', image: 'Standart reklam kartı', compact: 'Standart reklam kartı' };
const labelFor = value => props.placementOptions.find(option => option.value === value)?.label || value;
const rate = item => item.impressions ? `${((item.clicks / item.impressions) * 100).toFixed(1).replace('.', ',')}%` : '0,0%';
const placementHint = value => props.placementOptions.find(option => option.value === value)?.hint || '';

const applyFilters = () => router.get('/admin/advertisements', {
  search: filter.search || undefined,
  status: filter.status || undefined,
  placement: filter.placement || undefined,
  per_page: filter.per_page,
}, { preserveState: true, replace: true });
const setStatus = status => { filter.status = status; applyFilters(); };
const clearFilters = () => { Object.assign(filter, { search: '', status: '', placement: '', per_page: 50 }); applyFilters(); };

const openComposer = () => { form.clearErrors(); composeOpen.value = true; };
const closeComposer = () => { if (!form.processing) composeOpen.value = false; };
const submit = () => form.post('/admin/advertisements', {
  preserveScroll: true,
  forceFormData: true,
  onSuccess: () => { form.reset(); composeOpen.value = false; },
});

const askToggle = campaign => { toggleCandidate.value = campaign; };
const closeToggle = () => { if (!actionPending.value) toggleCandidate.value = null; };
const confirmToggle = () => {
  if (!toggleCandidate.value || actionPending.value) return;
  const campaign = toggleCandidate.value;
  actionPending.value = true;
  router.patch(`/admin/advertisements/${campaign.id}`, { isActive: !campaign.isActive }, {
    preserveScroll: true,
    onSuccess: () => { toggleCandidate.value = null; },
    onFinish: () => { actionPending.value = false; },
  });
};
const askDelete = campaign => { deleteCandidate.value = campaign; };
const closeDelete = () => { if (!deleteForm.processing) deleteCandidate.value = null; };
const confirmDelete = () => deleteForm.delete(`/admin/advertisements/${deleteCandidate.value.id}`, {
  preserveScroll: true,
  onSuccess: () => {
    if (viewing.value?.id === deleteCandidate.value?.id) viewing.value = null;
    deleteCandidate.value = null;
  },
});
</script>

<template>
  <Head title="Reklam Yönetimi" />
  <AdminLayout eyebrow="Gelir" title="Reklam yönetimi" description="Döngü’ye ait kampanyaları ve AdMob reklam alanlarını tek merkezden yönet.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <div v-if="flash" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ flash }}</div>
      <PlacementSettings :settings="placementSettings" />

      <section class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <button type="button" @click="setStatus('')" :class="['rounded-2xl border bg-white p-5 text-left transition', !filter.status ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200 hover:border-slate-300']"><p class="text-sm font-semibold text-slate-700">Toplam kampanya</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ Number(counts.all).toLocaleString('tr-TR') }}</p><p class="mt-1 text-xs text-slate-600">Döngü kampanya kayıtları</p></button>
        <button type="button" @click="setStatus('active')" :class="['rounded-2xl border bg-white p-5 text-left transition', filter.status === 'active' ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200 hover:border-slate-300']"><p class="text-sm font-semibold text-slate-700">Şu an yayında</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ Number(counts.active).toLocaleString('tr-TR') }}</p><p class="mt-1 text-xs text-slate-600">Tarih ve durum koşulları uygun</p></button>
        <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-700">Toplam gösterim</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ Number(counts.impressions).toLocaleString('tr-TR') }}</p><p class="mt-1 text-xs text-slate-600">Döngü kampanyaları</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-700">Tıklanma oranı</p><p class="mt-2 text-3xl font-semibold text-slate-950">%{{ Number(counts.ctr).toLocaleString('tr-TR') }}</p><p class="mt-1 text-xs text-slate-600">{{ Number(counts.clicks).toLocaleString('tr-TR') }} doğrulanmış tıklama</p></article>
      </section>

      <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form class="grid gap-3 xl:grid-cols-[1fr_210px_210px_140px_auto]" @submit.prevent="applyFilters">
          <label class="text-xs font-semibold text-slate-700">Kampanya ara<input v-model="filter.search" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" placeholder="Sponsor, başlık veya açıklama" /></label>
          <label class="text-xs font-semibold text-slate-700">Yayın alanı<select v-model="filter.placement" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option value="">Tüm alanlar</option><option v-for="option in placementOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
          <label class="text-xs font-semibold text-slate-700">Kampanya durumu<select v-model="filter.status" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option value="">Tüm durumlar</option><option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option></select></label>
          <label class="text-xs font-semibold text-slate-700">Sayfa başına<select v-model.number="filter.per_page" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option v-for="size in pageSizes" :key="size" :value="size">{{ size }}</option></select></label>
          <div class="flex items-end gap-2"><button class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white">Uygula</button><button type="button" class="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="clearFilters">Temizle</button></div>
        </form>
      </section>

      <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"><div><h2 class="text-lg font-semibold text-slate-950">Döngü kampanyaları</h2><p class="mt-1 text-sm text-slate-600">Toplam {{ Number(campaigns.total).toLocaleString('tr-TR') }} sonuç · Sayfa {{ campaigns.current_page }} / {{ campaigns.last_page }}</p></div><button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white" @click="openComposer"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>Yeni kampanya oluştur</button></div>
        <div v-if="campaigns.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[1180px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-700"><tr><th class="px-5 py-3.5">Kampanya</th><th class="px-5 py-3.5">Yayın alanları</th><th class="px-5 py-3.5">Durum</th><th class="px-5 py-3.5">Yayın dönemi</th><th class="px-5 py-3.5">Performans</th><th class="px-5 py-3.5">Öncelik</th><th class="px-5 py-3.5 text-right">İşlemler</th></tr></thead>
            <tbody class="divide-y divide-slate-100"><tr v-for="campaign in campaigns.data" :key="campaign.id" class="text-slate-800 transition hover:bg-slate-50/80">
              <td class="max-w-[330px] px-5 py-4"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">#{{ campaign.id }} · {{ campaign.sponsorName }}</p><p class="mt-1 truncate font-semibold text-slate-950">{{ campaign.headline }}</p><p class="mt-1 text-xs text-slate-600">{{ formatLabels[campaign.format] }}</p></td>
              <td class="px-5 py-4"><div class="flex max-w-[250px] flex-wrap gap-1.5"><span v-for="placement in campaign.placements" :key="placement" class="rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ labelFor(placement) }}</span><span v-if="!campaign.placements.length" class="text-xs font-semibold text-red-700">Yayın alanı yok</span></div></td>
              <td class="px-5 py-4"><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusClasses[campaign.status]]">{{ statuses[campaign.status] }}</span></td>
              <td class="px-5 py-4 text-xs leading-5 text-slate-700"><p>{{ $adminDate(campaign.startsAt, 'Hemen') }}</p><p class="text-slate-500">→ {{ $adminDate(campaign.endsAt, 'Süresiz') }}</p></td>
              <td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ campaign.impressions.toLocaleString('tr-TR') }} gösterim</p><p class="mt-1 text-xs text-slate-600">{{ campaign.clicks.toLocaleString('tr-TR') }} tıklama · {{ rate(campaign) }}</p></td>
              <td class="px-5 py-4 font-semibold text-slate-950">{{ campaign.priority }}</td>
              <td class="px-5 py-4"><div class="flex justify-end gap-2"><button type="button" title="Kampanya ayrıntılarını görüntüle" aria-label="Kampanya ayrıntılarını görüntüle" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 hover:border-emerald-500 hover:text-emerald-700" @click="viewing = campaign"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button><button type="button" :title="campaign.isActive ? 'Kampanyayı durdur' : 'Kampanyayı etkinleştir'" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 hover:border-amber-400 hover:bg-amber-50 hover:text-amber-800" @click="askToggle(campaign)"><svg v-if="campaign.isActive" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5v14M15 5v14"/></svg><svg v-else viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 5 11 7-11 7V5Z"/></svg></button><button type="button" title="Kampanyayı sil" aria-label="Kampanyayı sil" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-600 hover:border-red-400 hover:bg-red-50 hover:text-red-700" @click="askDelete(campaign)"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5"/></svg></button></div></td>
            </tr></tbody>
          </table>
        </div>
        <div v-else class="px-5 py-16 text-center"><p class="font-semibold text-slate-900">Bu filtrelerde Döngü kampanyası bulunamadı</p><p class="mt-1 text-sm text-slate-600">Google test reklamlarının burada satır olarak görünmemesi normaldir.</p><button type="button" class="mt-4 text-sm font-semibold text-emerald-700" @click="openComposer">İlk Döngü kampanyasını oluştur</button></div>
      </section>
      <nav v-if="campaigns.last_page > 1" class="mt-5 flex flex-wrap gap-2"><Link v-for="link in campaigns.links" :key="link.label" :href="link.url || ''" preserve-scroll :class="['rounded-lg border px-3 py-2 text-sm font-semibold', link.active ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-700', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" /></nav>
    </main>

    <div v-if="composeOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="closeComposer" @keydown.esc.window="closeComposer">
      <section role="dialog" aria-modal="true" aria-labelledby="campaign-create-title" class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5"><div><h2 id="campaign-create-title" class="text-xl font-semibold text-slate-950">Yeni Döngü kampanyası</h2><p class="mt-1 text-sm text-slate-600">Sana ait marka veya projenin tanıtımını, yayın alanlarını ve tarih aralığını kaydet.</p></div><button type="button" class="grid size-10 place-items-center rounded-xl border border-slate-300 text-slate-600" @click="closeComposer"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg></button></header>
        <form class="overflow-y-auto p-6" @submit.prevent="submit">
          <fieldset><legend class="text-sm font-semibold text-slate-950">Yayın alanları <span class="text-red-600">*</span></legend><p class="mt-1 text-xs text-slate-600">Aynı kampanyayı bir veya birden fazla geçerli mobil alanda yayınlayabilirsin.</p><div class="mt-3 grid gap-3 md:grid-cols-3"><label v-for="option in placementOptions" :key="option.value" :class="['cursor-pointer rounded-xl border p-4 transition', form.placements.includes(option.value) ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-100' : 'border-slate-300 hover:border-slate-400']"><span class="flex items-center gap-2"><input v-model="form.placements" :value="option.value" type="checkbox" /><strong class="text-sm font-semibold text-slate-950">{{ option.label }}</strong></span><span class="mt-2 block text-xs leading-5 text-slate-600">{{ placementHint(option.value) }}</span></label></div><p v-if="form.errors.placements" class="mt-2 text-sm font-semibold text-red-700">{{ form.errors.placements }}</p></fieldset>
          <div class="mt-6 grid gap-4 md:grid-cols-2">
            <label class="text-xs font-semibold text-slate-700">Sponsor adı <span class="text-red-600">*</span><input v-model="form.sponsorName" maxlength="100" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950" /><span v-if="form.errors.sponsorName" class="mt-1 block text-xs text-red-700">{{ form.errors.sponsorName }}</span></label>
            <label class="text-xs font-semibold text-slate-700">Reklam biçimi <span class="mt-1.5 flex h-11 w-full items-center rounded-xl border border-slate-300 bg-slate-50 px-3 text-sm text-slate-950">Standart reklam kartı</span></label>
            <label class="text-xs font-semibold text-slate-700 md:col-span-2">Başlık <span class="text-red-600">*</span><input v-model="form.headline" maxlength="140" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950" /><span v-if="form.errors.headline" class="mt-1 block text-xs text-red-700">{{ form.errors.headline }}</span></label>
            <label class="text-xs font-semibold text-slate-700 md:col-span-2">Açıklama <span class="text-red-600">*</span><textarea v-model="form.body" maxlength="240" rows="4" class="mt-1.5 w-full rounded-xl border border-slate-300 p-3 text-sm leading-6 text-slate-950" /><span class="mt-1 flex justify-between text-xs"><span class="font-semibold text-red-700">{{ form.errors.body }}</span><span class="text-slate-500">{{ form.body.length }} / 240</span></span></label>
            <label class="rounded-xl border border-dashed border-slate-300 p-4 text-xs font-semibold text-slate-700 md:col-span-2">Reklam görseli <span class="font-normal text-slate-500">(isteğe bağlı)</span><span class="mt-1 block font-normal text-slate-600">JPG, PNG veya WebP · en fazla 4 MB</span><input type="file" accept="image/jpeg,image/png,image/webp" class="mt-3 block w-full text-sm" @change="form.image = $event.target.files[0]" /><span v-if="form.errors.image" class="mt-2 block text-red-700">{{ form.errors.image }}</span></label>
            <label class="text-xs font-semibold text-slate-700">Buton yazısı<input v-model="form.ctaLabel" maxlength="40" placeholder="İncele" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950" /></label>
            <label class="text-xs font-semibold text-slate-700">Arka plan rengi <span class="text-red-600">*</span><input v-model="form.backgroundColor" type="color" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 p-1" /></label>
            <label class="text-xs font-semibold text-slate-700 md:col-span-2">Yönlendirme bağlantısı<input v-model="form.targetUrl" type="url" maxlength="500" placeholder="https://" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950" /><span v-if="form.errors.targetUrl" class="mt-1 block text-xs text-red-700">{{ form.errors.targetUrl }}</span></label>
            <label class="text-xs font-semibold text-slate-700">Başlangıç<input v-model="form.startsAt" type="datetime-local" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950" /></label>
            <label class="text-xs font-semibold text-slate-700">Bitiş<input v-model="form.endsAt" type="datetime-local" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950" /><span v-if="form.errors.endsAt" class="mt-1 block text-xs text-red-700">{{ form.errors.endsAt }}</span></label>
            <label class="text-xs font-semibold text-slate-700">Öncelik <span class="text-red-600">*</span><input v-model.number="form.priority" type="number" min="0" max="1000" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950" /><span class="mt-1 block font-normal text-slate-500">Yüksek sayı önce gösterilir.</span></label>
            <label class="flex min-h-11 items-center gap-3 self-end rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-800"><input v-model="form.isActive" type="checkbox" /> Oluşturunca etkinleştir</label>
          </div>
          <div v-if="Object.keys(form.errors).length" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">Kırmızı gösterilen zorunlu alanları kontrol et.</div>
          <footer class="mt-6 flex justify-end gap-2 border-t border-slate-200 pt-5"><button type="button" :disabled="form.processing" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700" @click="closeComposer">Vazgeç</button><button :disabled="form.processing" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Kaydediliyor…' : 'Kampanyayı oluştur' }}</button></footer>
        </form>
      </section>
    </div>

    <div v-if="viewing" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="viewing = null" @keydown.esc.window="viewing = null"><section role="dialog" aria-modal="true" class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"><header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Kampanya #{{ viewing.id }}</p><h2 class="mt-1 text-xl font-semibold text-slate-950">{{ viewing.headline }}</h2><p class="mt-1 text-sm text-slate-600">{{ viewing.sponsorName }}</p></div><button type="button" class="grid size-10 place-items-center rounded-xl border border-slate-300 text-slate-600" @click="viewing = null"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg></button></header><div class="overflow-y-auto p-6"><img v-if="viewing.imageUrl" :src="viewing.imageUrl" :alt="viewing.headline" class="h-56 w-full rounded-2xl object-cover" /><section :style="{ backgroundColor: viewing.backgroundColor }" class="mt-4 rounded-2xl border border-slate-200 p-5"><div class="flex justify-between gap-3"><p class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ viewing.sponsorName }}</p><span class="text-xs font-semibold text-slate-600">SPONSORLU</span></div><h3 class="mt-2 text-lg font-semibold text-slate-950">{{ viewing.headline }}</h3><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ viewing.body }}</p><p v-if="viewing.ctaLabel" class="mt-3 text-sm font-semibold text-emerald-800">{{ viewing.ctaLabel }} ›</p></section><section class="mt-6"><div class="flex items-center justify-between"><h3 class="text-sm font-semibold text-slate-950">Yayın ve performans</h3><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusClasses[viewing.status]]">{{ statuses[viewing.status] }}</span></div><dl class="mt-3 grid gap-x-8 sm:grid-cols-2"><div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-600">Yayın dönemi</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $adminDate(viewing.startsAt, 'Hemen') }} → {{ $adminDate(viewing.endsAt, 'Süresiz') }}</dd></div><div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-600">Biçim / Öncelik</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ formatLabels[viewing.format] }} · {{ viewing.priority }}</dd></div><div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-600">Toplam gösterim</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ viewing.impressions.toLocaleString('tr-TR') }}</dd></div><div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-600">Tıklama / Oran</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ viewing.clicks.toLocaleString('tr-TR') }} · {{ rate(viewing) }}</dd></div></dl><div class="mt-4 overflow-hidden rounded-xl border border-slate-200"><div v-for="placement in viewing.placements" :key="placement" class="flex items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 last:border-0"><span class="text-sm font-semibold text-slate-950">{{ labelFor(placement) }}</span><span class="text-xs text-slate-600">{{ viewing.statistics[placement].impressions.toLocaleString('tr-TR') }} gösterim · {{ viewing.statistics[placement].clicks.toLocaleString('tr-TR') }} tıklama</span></div></div></section><a v-if="viewing.targetUrl" :href="viewing.targetUrl" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex text-sm font-semibold text-emerald-700">Hedef bağlantıyı aç ↗</a></div></section></div>

    <div v-if="toggleCandidate" class="fixed inset-0 z-[60] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="closeToggle"><section role="alertdialog" aria-modal="true" class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl"><h2 class="text-xl font-semibold text-slate-950">Kampanya {{ toggleCandidate.isActive ? 'durdurulsun' : 'etkinleştirilsin' }} mi?</h2><p class="mt-2 text-sm leading-6 text-slate-600"><strong class="font-semibold text-slate-900">{{ toggleCandidate.headline }}</strong> {{ toggleCandidate.isActive ? 'mobil reklam seçiminden çıkarılacak.' : 'tarih koşulları uygunsa mobil reklam seçiminde kullanılacak.' }}</p><div class="mt-6 flex justify-end gap-2"><button type="button" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700" @click="closeToggle">Vazgeç</button><button type="button" :disabled="actionPending" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white disabled:opacity-50" @click="confirmToggle">{{ actionPending ? 'İşleniyor…' : toggleCandidate.isActive ? 'Kampanyayı durdur' : 'Kampanyayı etkinleştir' }}</button></div></section></div>
    <div v-if="deleteCandidate" class="fixed inset-0 z-[60] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="closeDelete"><section role="alertdialog" aria-modal="true" class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl"><h2 class="text-xl font-semibold text-slate-950">Kampanya kalıcı olarak silinsin mi?</h2><p class="mt-2 text-sm leading-6 text-slate-600"><strong class="font-semibold text-slate-900">#{{ deleteCandidate.id }} · {{ deleteCandidate.headline }}</strong> ve ilişkili ölçüm kayıtları silinecek.</p><div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">Bu işlem geri alınamaz. Geçici olarak yayından kaldırmak istiyorsan kampanyayı durdur.</div><div class="mt-6 flex justify-end gap-2"><button type="button" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700" @click="closeDelete">Vazgeç</button><button type="button" :disabled="deleteForm.processing" class="h-11 rounded-xl bg-red-700 px-5 text-sm font-semibold text-white disabled:opacity-50" @click="confirmDelete">{{ deleteForm.processing ? 'Siliniyor…' : 'Kampanyayı sil' }}</button></div></section></div>
  </AdminLayout>
</template>