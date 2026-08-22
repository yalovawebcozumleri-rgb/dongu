<script setup>
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ stats: Object, listingStatusCounts: Object, downloadClicks: Object });

const platformLabels = { android: 'Android', ios: 'iOS', desktop: 'Masaüstü', other: 'Diğer' };
const sourceLabels = { direct: 'Doğrudan', dongu_website: 'Döngü web sitesi', facebook: 'Facebook', instagram: 'Instagram', youtube: 'YouTube', google: 'Google' };
</script>

<template>
  <Head title="Genel Bakış" />
  <AdminLayout eyebrow="Genel Bakış" title="Pazaryeri durumu" description="Kullanıcıları, ilanları ve platformdaki güncel hareketleri tek ekrandan takip et.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Link href="/admin/users" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
          <p class="text-sm font-semibold text-slate-700">Kullanıcılar</p><p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ stats.users.toLocaleString('tr-TR') }}</p><p class="mt-1 text-sm text-slate-600">Kayıtlı hesapları yönet</p><p class="mt-4 text-xs font-semibold text-emerald-700">Kullanıcı yönetimine git →</p>
        </Link>
        <Link href="/admin/listings?status=active" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
          <p class="text-sm font-semibold text-slate-700">Aktif ilanlar</p><p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ stats.activeListings.toLocaleString('tr-TR') }}</p><p class="mt-1 text-sm text-slate-600">Yayındaki ilanları yönet</p><p class="mt-4 text-xs font-semibold text-emerald-700">İlan yönetimine git →</p>
        </Link>
        <Link href="/admin/listings?status=completed" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
          <p class="text-sm font-semibold text-slate-700">Tamamlanan teslimatlar</p><p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ stats.completedTransactions.toLocaleString('tr-TR') }}</p><p class="mt-1 text-sm text-slate-600">Alıcı ve satıcı işlemleri</p><p class="mt-4 text-xs font-semibold text-emerald-700">Tamamlananları incele →</p>
        </Link>
        <Link href="/admin/listings" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
          <p class="text-sm font-semibold text-slate-700">Toplam ambalaj</p><p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ stats.materials.toLocaleString('tr-TR') }}</p><p class="mt-1 text-sm text-slate-600">İlanlardaki toplam adet</p><p class="mt-4 text-xs font-semibold text-emerald-700">İlanlarda görüntüle →</p>
        </Link>
      </section>

      <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div><h2 class="text-lg font-semibold text-slate-950">İndirme bağlantısı performansı</h2><p class="mt-1 text-sm text-slate-600"><code>/indir</code> bağlantısına yapılan gerçek ziyaretler; bağlantı önizleme botları sayılmaz.</p></div>
          <p class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">Kaynak örneği: /indir?source=facebook</p>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
          <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Bugün</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ downloadClicks.today.toLocaleString('tr-TR') }}</p></div>
          <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Son 7 gün</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ downloadClicks.last7Days.toLocaleString('tr-TR') }}</p></div>
          <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Tüm zamanlar</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ downloadClicks.total.toLocaleString('tr-TR') }}</p></div>
        </div>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
          <div><h3 class="text-sm font-semibold text-slate-900">Platformlar · son 30 gün</h3><div class="mt-3 grid gap-2 sm:grid-cols-2"><div v-for="item in downloadClicks.platforms" :key="item.name" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3"><span class="text-sm font-semibold text-slate-700">{{ platformLabels[item.name] || item.name }}</span><span class="text-sm font-semibold text-slate-950">{{ item.clicks.toLocaleString('tr-TR') }}</span></div><p v-if="!downloadClicks.platforms.length" class="text-sm text-slate-600">Henüz tıklama yok.</p></div></div>
          <div><h3 class="text-sm font-semibold text-slate-900">Kaynaklar · son 30 gün</h3><div class="mt-3 grid gap-2 sm:grid-cols-2"><div v-for="item in downloadClicks.sources" :key="item.name" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3"><span class="text-sm font-semibold text-slate-700">{{ sourceLabels[item.name] || item.name }}</span><span class="text-sm font-semibold text-slate-950">{{ item.clicks.toLocaleString('tr-TR') }}</span></div><p v-if="!downloadClicks.sources.length" class="text-sm text-slate-600">Henüz tıklama yok.</p></div></div>
        </div>
      </section>

      <section class="mt-6 grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-950">İlan dağılımı</h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <Link v-for="item in [['active','Aktif',listingStatusCounts.active],['reserved','Rezerve',listingStatusCounts.reserved],['completed','Tamamlandı',listingStatusCounts.completed],['cancelled','İptal',listingStatusCounts.cancelled]]" :key="item[0]" :href="`/admin/listings?status=${item[0]}`" class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span class="text-sm font-semibold text-slate-800">{{ item[1] }}</span><span class="text-sm font-semibold text-slate-950">{{ item[2] }}</span></Link>
          </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-950">Bekleyen işler</h2>
          <div class="mt-4 space-y-3">
            <Link href="/admin/message-reports" class="flex w-full items-center justify-between rounded-xl bg-red-50 px-4 py-3 text-left"><span class="text-sm font-semibold text-red-900">Güvenlik incelemeleri</span><span class="text-sm font-semibold text-red-800">{{ stats.pendingModeration }}</span></Link>
            <Link href="/admin/announcements" class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span class="text-sm font-semibold text-slate-800">Planlı duyurular</span><span class="text-sm font-semibold text-slate-950">{{ stats.scheduledAnnouncements }}</span></Link>
          </div>
        </section>
      </section>
    </main>
  </AdminLayout>
</template>
