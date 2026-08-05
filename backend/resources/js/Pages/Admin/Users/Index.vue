<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ users: Object, filters: Object, counts: Object, pageSizes: Array });
const filter = reactive({ ...props.filters });
const stateClasses = {
  active: 'bg-emerald-50 text-emerald-800',
  suspended: 'bg-amber-50 text-amber-900',
  closed: 'bg-red-50 text-red-800',
  inactive: 'bg-slate-100 text-slate-700',
};
const applyFilters = () => router.get('/admin/users', { ...filter, search: filter.search || undefined, status: filter.status || undefined }, { preserveState: true, replace: true });
const setStatus = status => { filter.status = status; applyFilters(); };
const clearFilters = () => { Object.assign(filter, { search: '', status: '', per_page: 50 }); applyFilters(); };
</script>

<template>
  <Head title="Kullanıcı Yönetimi" />
  <AdminLayout eyebrow="Hesaplar" title="Kullanıcı yönetimi" description="Kayıtlı hesapları ara, erişim durumlarını yönet ve denetlenebilir hesap kararları uygula.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <button v-for="item in [['', 'Tüm kullanıcılar', counts.all], ['active', 'Aktif', counts.active], ['suspended', 'Askıya alınmış', counts.suspended], ['closed', 'Kapatılmış', counts.closed]]" :key="item[0]" type="button" @click="setStatus(item[0])" :class="['rounded-2xl border bg-white p-5 text-left transition', (filter.status || '') === item[0] ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200 hover:border-slate-300']">
          <p class="text-sm font-semibold text-slate-700">{{ item[1] }}</p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ Number(item[2]).toLocaleString('tr-TR') }}</p>
        </button>
      </section>

      <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form class="grid gap-3 md:grid-cols-[1fr_210px_140px_auto]" @submit.prevent="applyFilters">
          <label class="text-xs font-semibold text-slate-700">Kullanıcı ara<input v-model="filter.search" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" placeholder="Ad, e-posta veya telefon" /></label>
          <label class="text-xs font-semibold text-slate-700">Hesap durumu<select v-model="filter.status" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option value="">Tümü</option><option value="active">Aktif</option><option value="suspended">Askıya alınmış</option><option value="closed">Kapatılmış</option><option value="inactive">Pasif / eski durum</option></select></label>
          <label class="text-xs font-semibold text-slate-700">Sayfa başına<select v-model.number="filter.per_page" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option v-for="size in pageSizes" :key="size" :value="size">{{ size }}</option></select></label>
          <div class="flex items-end gap-2"><button class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white">Uygula</button><button type="button" class="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="clearFilters">Temizle</button></div>
        </form>
      </section>

      <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="text-lg font-semibold text-slate-950">Kullanıcı kayıtları</h2><p class="mt-1 text-sm text-slate-600">{{ users.total.toLocaleString('tr-TR') }} sonuç</p></div><p class="text-sm text-slate-600">Sayfa {{ users.current_page }} / {{ users.last_page }}</p></div>
        <div v-if="users.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[1080px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-700"><tr><th class="px-5 py-3.5">Kullanıcı</th><th class="px-5 py-3.5">Hesap durumu</th><th class="px-5 py-3.5">İlan</th><th class="px-5 py-3.5">Teslimat</th><th class="px-5 py-3.5">Değerlendirme</th><th class="px-5 py-3.5">Kayıt</th><th class="px-5 py-3.5 text-right">İşlem</th></tr></thead>
            <tbody class="divide-y divide-slate-100"><tr v-for="user in users.data" :key="user.id" class="text-slate-800 hover:bg-slate-50"><td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ user.name }}</p><p class="mt-0.5 text-xs text-slate-600">#{{ user.id }} · {{ user.email }}</p><p v-if="user.phone" class="mt-0.5 text-xs text-slate-600">{{ user.phone }}</p></td><td class="px-5 py-4"><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', stateClasses[user.account_state]]">{{ user.account_state_label }}</span><p v-if="user.restriction_ends_at" class="mt-2 text-xs font-medium text-slate-600">{{ $adminDate(user.restriction_ends_at) }} tarihine kadar</p></td><td class="px-5 py-4 font-semibold text-slate-950">{{ user.listings_count }}</td><td class="px-5 py-4 font-semibold text-slate-950">{{ user.completed_transactions }}</td><td class="px-5 py-4 text-slate-800">{{ user.rating ?? '—' }} <span class="text-xs text-slate-600">({{ user.rating_count }})</span></td><td class="px-5 py-4 text-slate-700">{{ $adminDate(user.created_at) }}</td><td class="px-5 py-4 text-right"><Link :href="`/admin/users/${user.id}`" title="Kullanıcıyı görüntüle ve yönet" class="inline-grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 hover:border-emerald-500 hover:text-emerald-700"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></Link></td></tr></tbody>
          </table>
        </div>
        <div v-else class="px-5 py-16 text-center font-semibold text-slate-900">Bu filtrelerde kullanıcı bulunamadı.</div>
      </section>
      <nav v-if="users.last_page > 1" class="mt-5 flex flex-wrap gap-2"><Link v-for="link in users.links" :key="link.label" :href="link.url || ''" :class="['rounded-lg border px-3 py-2 text-sm font-semibold', link.active ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-700', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" /></nav>
    </main>
  </AdminLayout>
</template>
