<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({ listings: Object, filters: Object, counts: Object, pageSizes: Array });
const filter = reactive({ ...props.filters });
const removing = ref(null);
const removeForm = useForm({ reason: '' });
const statuses = { active: 'Aktif', reserved: 'Rezerve', completed: 'Tamamlandı', cancelled: 'İptal' };
const statusClasses = { active: 'bg-emerald-50 text-emerald-800', reserved: 'bg-amber-50 text-amber-800', completed: 'bg-sky-50 text-sky-800', cancelled: 'bg-slate-100 text-slate-700' };
const materials = { pet: 'PET', glass: 'Cam', aluminum: 'Alüminyum' };
const applyFilters = () => router.get('/admin/listings', { ...filter, search: filter.search || undefined, region: filter.region || undefined, status: filter.status || undefined, material: filter.material || undefined }, { preserveState: true, replace: true });
const setStatus = status => { filter.status = status; applyFilters(); };
const clearFilters = () => { Object.assign(filter, { search: '', region: '', status: '', material: '', per_page: 50 }); applyFilters(); };
const askRemove = listing => { removing.value = listing; removeForm.reset(); removeForm.clearErrors(); };
const closeRemove = () => { if (!removeForm.processing) { removing.value = null; removeForm.reset(); } };
const confirmRemove = () => removeForm.delete(`/admin/listings/${removing.value.id}`, { preserveScroll: true, onSuccess: closeRemove });
</script>

<template>
  <Head title="İlan Yönetimi" />
  <AdminLayout eyebrow="Pazaryeri" title="İlan yönetimi" description="İlanları bölge, durum, malzeme ve kullanıcı bilgileriyle ara; işlem geçmişini koruyarak yönet.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">

      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <button v-for="item in [['', 'Tüm ilanlar', counts.all], ['active', 'Aktif', counts.active], ['reserved', 'Rezerve', counts.reserved], ['completed', 'Tamamlandı', counts.completed], ['cancelled', 'İptal', counts.cancelled]]" :key="item[0]" type="button" @click="setStatus(item[0])" :class="['rounded-2xl border bg-white p-4 text-left transition', (filter.status || '') === item[0] ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200 hover:border-slate-300']">
          <p class="text-sm font-semibold text-slate-700">{{ item[1] }}</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ Number(item[2]).toLocaleString('tr-TR') }}</p>
        </button>
      </section>

      <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form class="grid gap-3 xl:grid-cols-[1.25fr_1fr_180px_180px_140px_auto]" @submit.prevent="applyFilters">
          <label class="text-xs font-semibold text-slate-700">İlan, satıcı veya alıcı ara<input v-model="filter.search" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" placeholder="Numara, ad veya e-posta" /></label>
          <label class="text-xs font-semibold text-slate-700">Bölge<input v-model="filter.region" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950 outline-none focus:border-emerald-600" placeholder="Yalova, Bağcılar…" /></label>
          <label class="text-xs font-semibold text-slate-700">Durum<select v-model="filter.status" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option value="">Tümü</option><option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option></select></label>
          <label class="text-xs font-semibold text-slate-700">Malzeme<select v-model="filter.material" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option value="">Tümü</option><option v-for="(label, value) in materials" :key="value" :value="value">{{ label }}</option></select></label>
          <label class="text-xs font-semibold text-slate-700">Sayfa başına<select v-model.number="filter.per_page" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option v-for="size in pageSizes" :key="size" :value="size">{{ size }}</option></select></label>
          <div class="flex items-end gap-2"><button class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white">Uygula</button><button type="button" class="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="clearFilters">Temizle</button></div>
        </form>
      </section>

      <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"><div><h2 class="text-lg font-semibold text-slate-950">İlan kayıtları</h2><p class="mt-1 text-sm text-slate-600">Toplam {{ listings.total.toLocaleString('tr-TR') }} sonuç</p></div><p class="text-sm font-medium text-slate-600">Sayfa {{ listings.current_page }} / {{ listings.last_page }}</p></div>
        <div v-if="listings.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[1120px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-700"><tr><th class="px-5 py-3.5">İlan</th><th class="px-5 py-3.5">Satıcı / Alıcı</th><th class="px-5 py-3.5">Malzemeler</th><th class="px-5 py-3.5">Bölge</th><th class="px-5 py-3.5">Durum</th><th class="px-5 py-3.5">Yayın</th><th class="px-5 py-3.5 text-right">İşlemler</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="listing in listings.data" :key="listing.id" class="text-slate-800 transition hover:bg-slate-50/80">
                <td class="px-5 py-4"><p class="font-semibold text-slate-950">#{{ listing.id }}</p></td>
                <td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ listing.seller?.name }}</p><p class="mt-0.5 text-xs text-slate-600">Satıcı · {{ listing.seller?.email }}</p><p v-if="listing.buyer" class="mt-2 font-semibold text-slate-950">{{ listing.buyer.name }}</p><p v-if="listing.buyer" class="mt-0.5 text-xs text-slate-600">Alıcı · {{ listing.buyer.email }}</p></td>
                <td class="px-5 py-4"><div class="flex flex-wrap gap-1.5"><span v-for="item in listing.materials" :key="item.type" class="rounded-lg bg-cream-100 px-2.5 py-1 text-xs font-semibold text-forest-900">{{ materials[item.type] }} · {{ item.quantity }}</span></div></td>
                <td class="px-5 py-4 font-medium text-slate-800">{{ listing.public_area }}</td>
                <td class="px-5 py-4"><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusClasses[listing.status]]">{{ statuses[listing.status] }}</span></td>
                <td class="px-5 py-4 text-slate-700">{{ $adminDate(listing.published_at, 'Taslak') }}</td>
                <td class="px-5 py-4"><div class="flex justify-end gap-2"><Link :href="`/admin/listings/${listing.id}`" title="İlanı görüntüle" aria-label="İlanı görüntüle" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 transition hover:border-emerald-500 hover:text-emerald-700"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></Link><button type="button" :disabled="!listing.can_remove" :title="listing.can_remove ? 'İlanı kaldır' : 'Açık talep veya rezervasyon var'" :aria-label="listing.can_remove ? 'İlanı kaldır' : 'İlan kaldırılamaz'" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-35" @click="askRemove(listing)"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5"/></svg></button></div></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="px-5 py-16 text-center"><p class="font-semibold text-slate-900">Bu filtrelerde ilan bulunamadı</p><button type="button" class="mt-3 text-sm font-semibold text-emerald-700" @click="clearFilters">Filtreleri temizle</button></div>
      </section>

      <nav v-if="listings.last_page > 1" aria-label="İlan sayfaları" class="mt-5 flex flex-wrap gap-2"><Link v-for="link in listings.links" :key="link.label" :href="link.url || ''" preserve-scroll :class="['rounded-lg border px-3 py-2 text-sm font-semibold', link.active ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-700', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" /></nav>
    </main>

    <div v-if="removing" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-5 backdrop-blur-sm" @click.self="closeRemove">
      <section role="dialog" aria-modal="true" aria-labelledby="remove-title" class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-700"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5m0 3h.01M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span><div><h2 id="remove-title" class="text-xl font-semibold text-slate-950">İlan #{{ removing.id }} kaldırılsın mı?</h2><p class="mt-2 text-sm leading-6 text-slate-600">İlan kullanıcı ekranlarından kaldırılır; işlem ve yönetici denetim kayıtları korunur.</p></div></div>
        <label class="mt-5 block text-sm font-semibold text-slate-800">Kaldırma gerekçesi<textarea v-model="removeForm.reason" rows="4" maxlength="1000" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm text-slate-950 outline-none focus:border-red-500" placeholder="En az 10 karakterlik denetlenebilir gerekçe yaz." /></label><p v-if="removeForm.errors.reason" class="mt-2 text-sm font-semibold text-red-700">{{ removeForm.errors.reason }}</p>
        <div class="mt-6 flex justify-end gap-2"><button type="button" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700" @click="closeRemove">Vazgeç</button><button type="button" :disabled="removeForm.processing" class="h-11 rounded-xl bg-red-700 px-5 text-sm font-semibold text-white disabled:opacity-50" @click="confirmRemove">İlanı kaldır</button></div>
      </section>
    </div>
  </AdminLayout>
</template>
