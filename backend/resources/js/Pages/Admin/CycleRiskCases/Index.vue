<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({ cases: Object, filters: Object, counts: Object, pageSizes: Array });
const flash = computed(() => usePage().props.flash?.success);
const filter = reactive({ ...props.filters });
const deleteCandidate = ref(null);
const deleteForm = useForm({});
const statuses = { pending: 'İncelenecek', cleared: 'Temiz', confirmed: 'Hile doğrulandı' };
const severities = { low: 'Düşük', medium: 'Orta', high: 'Yüksek' };
const statusClasses = { pending: 'bg-amber-50 text-amber-900', confirmed: 'bg-red-50 text-red-800', cleared: 'bg-emerald-50 text-emerald-800' };
const severityClasses = { high: 'bg-red-50 text-red-800', medium: 'bg-orange-50 text-orange-800', low: 'bg-slate-100 text-slate-700' };
const applyFilters = () => router.get('/admin/cycle-risk-cases', { ...filter, search: filter.search || undefined, status: filter.status || undefined, severity: filter.severity || undefined }, { preserveState: true, replace: true });
const setStatus = status => { filter.status = status; applyFilters(); };
const clearFilters = () => { Object.assign(filter, { search: '', status: '', severity: '', per_page: 50 }); applyFilters(); };
const askDelete = item => { deleteCandidate.value = item; };
const closeDeleteConfirmation = () => { if (!deleteForm.processing) deleteCandidate.value = null; };
const confirmDelete = () => {
  if (!deleteCandidate.value || deleteCandidate.value.status === 'pending' || deleteForm.processing) return;
  const caseId = deleteCandidate.value.id;
  deleteForm.delete(`/admin/cycle-risk-cases/${caseId}`, {
    preserveScroll: true,
    onSuccess: () => { deleteCandidate.value = null; },
  });
};
</script>

<template>
  <Head title="Puan Denetimi" />
  <AdminLayout eyebrow="Güvenlik" title="Puan denetimi" description="Şüpheli puan hareketlerini otomatik sinyaller, taraf bilgileri ve işlem kanıtlarıyla incele.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <div v-if="flash" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ flash }}</div>
      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><button v-for="item in [['', 'Tüm vakalar', counts.all], ['pending', 'İncelenecek', counts.pending], ['confirmed', 'Doğrulandı', counts.confirmed], ['cleared', 'Temiz', counts.cleared]]" :key="item[0]" type="button" @click="setStatus(item[0])" :class="['rounded-2xl border bg-white p-5 text-left transition', (filter.status || '') === item[0] ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200 hover:border-slate-300']"><p class="text-sm font-semibold text-slate-700">{{ item[1] }}</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ Number(item[2]).toLocaleString('tr-TR') }}</p></button></section>
      <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><form class="grid gap-3 xl:grid-cols-[1fr_210px_210px_140px_auto]" @submit.prevent="applyFilters"><label class="text-xs font-semibold text-slate-700">Alıcı veya satıcı ara<input v-model="filter.search" maxlength="100" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" placeholder="Ad veya e-posta" /></label><label class="text-xs font-semibold text-slate-700">Durum<select v-model="filter.status" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="">Tümü</option><option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option></select></label><label class="text-xs font-semibold text-slate-700">Risk seviyesi<select v-model="filter.severity" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="">Tümü</option><option v-for="(label, value) in severities" :key="value" :value="value">{{ label }}</option></select></label><label class="text-xs font-semibold text-slate-700">Sayfa başına<select v-model.number="filter.per_page" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option v-for="size in pageSizes" :key="size" :value="size">{{ size }}</option></select></label><div class="flex items-end gap-2"><button class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white">Uygula</button><button type="button" class="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="clearFilters">Temizle</button></div></form></section>
      <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="text-lg font-semibold text-slate-950">Puan denetimi kayıtları</h2><p class="mt-1 text-sm text-slate-600">{{ cases.total.toLocaleString('tr-TR') }} sonuç</p></div><p class="text-sm text-slate-600">Sayfa {{ cases.current_page }} / {{ cases.last_page }}</p></div><div v-if="cases.data.length" class="overflow-x-auto"><table class="w-full min-w-[1180px] text-left text-sm"><thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-700"><tr><th class="px-5 py-3.5">Vaka</th><th class="px-5 py-3.5">Alıcı</th><th class="px-5 py-3.5">Satıcı</th><th class="px-5 py-3.5">İncelenen puan</th><th class="px-5 py-3.5">Risk</th><th class="px-5 py-3.5">Durum</th><th class="px-5 py-3.5 text-right">İşlem</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in cases.data" :key="item.id" class="text-slate-800 hover:bg-slate-50"><td class="px-5 py-4"><p class="font-semibold text-slate-950">#{{ item.id }} · {{ item.listingArea }}</p><p class="mt-1 text-xs text-slate-600">{{ $adminDate(item.detectedAt) }} · {{ item.ruleCount }} sinyal</p></td><td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ item.buyer.name }}</p><p class="mt-0.5 text-xs text-slate-600">{{ item.buyer.email }}</p></td><td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ item.seller.name }}</p><p class="mt-0.5 text-xs text-slate-600">{{ item.seller.email }}</p></td><td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ item.points.toLocaleString('tr-TR') }} puan</p><p class="mt-0.5 text-xs text-slate-600">Satıcı döngü puanı</p></td><td class="px-5 py-4"><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', severityClasses[item.severity]]">{{ item.riskScore }}/100 · {{ severities[item.severity] }}</span></td><td class="px-5 py-4"><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusClasses[item.status]]">{{ statuses[item.status] }}</span></td><td class="px-5 py-4 text-right"><div class="inline-flex items-center gap-2"><Link :href="`/admin/cycle-risk-cases/${item.id}`" title="Vakayı incele" aria-label="Vakayı incele" class="inline-grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 hover:border-emerald-500 hover:text-emerald-700"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></Link><button v-if="item.status !== 'pending'" type="button" title="Kaydı kalıcı olarak sil" aria-label="Kaydı kalıcı olarak sil" class="inline-grid size-9 place-items-center rounded-lg border border-red-200 text-red-700 transition hover:border-red-500 hover:bg-red-50" @click="askDelete(item)"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5"/></svg></button></div></td></tr></tbody></table></div><div v-else class="px-5 py-16 text-center"><p class="font-semibold text-slate-900">Bu filtrelerde puan denetimi kaydı bulunamadı.</p><button type="button" class="mt-3 text-sm font-semibold text-emerald-700" @click="clearFilters">Filtreleri temizle</button></div></section>
      <nav v-if="cases.last_page > 1" class="mt-5 flex flex-wrap gap-2"><Link v-for="link in cases.links" :key="link.label" :href="link.url || ''" :class="['rounded-lg border px-3 py-2 text-sm font-semibold', link.active ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-700', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" /></nav>
    </main>

    <div v-if="deleteCandidate" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/55 p-4" @click.self="closeDeleteConfirmation">
      <section role="alertdialog" aria-modal="true" aria-labelledby="risk-delete-title" class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4">
          <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-700">
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5"/></svg>
          </span>
          <div>
            <h2 id="risk-delete-title" class="text-lg font-semibold text-slate-950">Puan denetimi kaydı silinsin mi?</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600"><strong>#{{ deleteCandidate.id }}</strong> numaralı sonuçlandırılmış vaka ve bu vakaya ait yönetici denetim geçmişi kalıcı olarak silinecek.</p>
          </div>
        </div>
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">Kullanıcının aktif veya iptal edilmiş puan durumu, toplam puanı ve rozetleri değişmeyecek. Silinen vaka ayrıntıları geri getirilemez.</div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" :disabled="deleteForm.processing" @click="closeDeleteConfirmation">Vazgeç</button>
          <button type="button" class="h-11 rounded-xl bg-red-700 px-4 text-sm font-semibold text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="deleteForm.processing" @click="confirmDelete">{{ deleteForm.processing ? 'Siliniyor…' : 'Kalıcı olarak sil' }}</button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
