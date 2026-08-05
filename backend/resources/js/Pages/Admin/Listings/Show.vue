<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
const props = defineProps({ listing: Object });
const statuses = { active: 'Aktif', reserved: 'Rezerve', completed: 'Tamamlandı', cancelled: 'İptal', inquiry: 'Görüşme', pending: 'Bekliyor', accepted: 'Kabul edildi', rejected: 'Reddedildi' };
const materials = { pet: 'PET', glass: 'Cam', aluminum: 'Alüminyum' };
</script>

<template>
  <Head :title="`İlan #${listing.id}`" />
  <AdminLayout eyebrow="Pazaryeri" :title="`İlan #${listing.id}`" description="İlan içeriğini, tarafları, teslimat bilgilerini ve işlem geçmişini incele.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <Link href="/admin/listings" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">← İlan yönetimine dön</Link>
      <div class="mt-5 grid gap-6 lg:grid-cols-[1.25fr_.75fr]">
        <section class="space-y-5">
          <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-600">İlan bilgisi</p><h2 class="mt-1 text-xl font-semibold text-slate-950">{{ listing.public_area }}</h2></div><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">{{ statuses[listing.status] }}</span></div><p class="mt-5 whitespace-pre-wrap text-sm leading-7 text-slate-800">{{ listing.description }}</p><div class="mt-5 flex flex-wrap gap-2"><span v-for="item in listing.materials" :key="item.type" class="rounded-xl bg-cream-100 px-3 py-2 text-sm font-semibold text-forest-900">{{ materials[item.type] }} · {{ item.quantity }} adet · {{ item.unit_price }} TL</span></div></article>
          <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">Alım talepleri ve işlem geçmişi</h2><div v-if="listing.requests.length" class="mt-4 divide-y divide-slate-100"><div v-for="request in listing.requests" :key="request.id" class="grid gap-3 py-4 sm:grid-cols-[1fr_auto]"><div><p class="font-semibold text-slate-950">#{{ request.id }} · {{ request.buyer?.name }}</p><p class="mt-1 text-sm text-slate-600">{{ request.buyer?.email }}</p><p class="mt-2 text-xs text-slate-600">Kabul: {{ $adminDate(request.accepted_at) }} · Tamamlama: {{ $adminDate(request.completed_at) }}</p></div><span class="h-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800">{{ statuses[request.status] || request.status }}</span></div></div><p v-else class="mt-4 text-sm text-slate-600">Bu ilan için alım talebi bulunmuyor.</p></article>
        </section>
        <aside class="space-y-5">
          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">Satıcı</h2><p class="mt-4 font-semibold text-slate-950">{{ listing.seller.name }}</p><p class="mt-1 text-sm text-slate-700">{{ listing.seller.email }}</p><p class="mt-1 text-sm text-slate-700">{{ listing.seller.phone || 'Telefon yok' }}</p><div class="mt-4 grid grid-cols-2 gap-2"><div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-600">Teslimat</p><p class="mt-1 font-semibold text-slate-950">{{ listing.seller.completed_transactions }}</p></div><div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-600">Değerlendirme</p><p class="mt-1 font-semibold text-slate-950">{{ listing.seller.rating ?? '—' }} ({{ listing.seller.rating_count }})</p></div></div></section>
          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">Teslimat ve yayın</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="font-medium text-slate-600">Açık adres</dt><dd class="mt-1 font-semibold text-slate-950">{{ listing.exact_address || 'Kayıtlı değil' }}</dd></div><div><dt class="font-medium text-slate-600">Teslimat notu</dt><dd class="mt-1 text-slate-800">{{ listing.delivery_notes || 'Yok' }}</dd></div><div><dt class="font-medium text-slate-600">Yayın tarihi</dt><dd class="mt-1 text-slate-800">{{ $adminDate(listing.published_at, 'Taslak') }}</dd></div><div><dt class="font-medium text-slate-600">Bitiş tarihi</dt><dd class="mt-1 text-slate-800">{{ $adminDate(listing.expires_at, 'Belirtilmedi') }}</dd></div><div><dt class="font-medium text-slate-600">Ambalaj onayı</dt><dd class="mt-1 text-slate-800">{{ $adminDate(listing.condition_confirmed_at, 'Onaylanmadı') }}</dd></div></dl></section>
          <section v-if="listing.reports.length" class="rounded-2xl border border-red-200 bg-red-50 p-5"><h2 class="font-semibold text-red-950">İlan bildirimleri</h2><p class="mt-2 text-sm text-red-800">Bu ilan için {{ listing.reports.length }} bildirim bulunuyor.</p></section>
        </aside>
      </div>
    </main>
  </AdminLayout>
</template>
