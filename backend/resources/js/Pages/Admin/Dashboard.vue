<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const props = defineProps({
  generatedAt: String,
  health: Object,
  users: Object,
  listingMetrics: Object,
  transactions: Object,
  announcements: Object,
  downloadClicks: Object,
  moderation: Array,
});

const showDownloadDetails = ref(false);
const number = (value) => Number(value || 0).toLocaleString('tr-TR');
const time = (value) => new Intl.DateTimeFormat('tr-TR', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Istanbul' }).format(new Date(value));
const dayDate = (value) => new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'long', timeZone: 'Europe/Istanbul' }).format(new Date(`${value}T12:00:00+03:00`));
const generatedTime = computed(() => props.generatedAt ? time(props.generatedAt) : '—');
const moderationTotal = computed(() => (props.moderation || []).reduce((sum, item) => sum + Number(item.count || 0), 0));
const usersDelta = computed(() => Number(props.users.last7Days || 0) - Number(props.users.previous7Days || 0));
const listingsDelta = computed(() => Number(props.listingMetrics.last7Days || 0) - Number(props.listingMetrics.previous7Days || 0));
const transactionsDelta = computed(() => Number(props.transactions.completedLast7Days || 0) - Number(props.transactions.completedPrevious7Days || 0));

const platformLabels = { android: 'Android', ios: 'iOS', desktop: 'Masaüstü', other: 'Diğer' };
const sourceLabels = { direct: 'Doğrudan', dongu_website: 'Döngü web sitesi', facebook: 'Facebook', instagram: 'Instagram', youtube: 'YouTube', google: 'Google', whatsapp: 'WhatsApp' };
const stateMeta = {
  completed: { label: 'Gönderildi', classes: 'bg-emerald-50 text-emerald-800 ring-emerald-200' },
  scheduled: { label: 'Planlandı', classes: 'bg-sky-50 text-sky-800 ring-sky-200' },
  processing: { label: 'Gönderiliyor', classes: 'bg-amber-50 text-amber-800 ring-amber-200' },
  delayed: { label: 'Gecikmiş', classes: 'bg-orange-50 text-orange-800 ring-orange-200' },
  failed: { label: 'Başarısız', classes: 'bg-red-50 text-red-800 ring-red-200' },
};

const deltaText = (delta) => delta === 0 ? 'Önceki 7 günle aynı' : `${delta > 0 ? '+' : ''}${number(delta)} · önceki 7 güne göre`;
const deltaClass = (delta) => delta > 0 ? 'text-emerald-700' : delta < 0 ? 'text-amber-700' : 'text-slate-500';
const closeOnEscape = (event) => { if (event.key === 'Escape') showDownloadDetails.value = false; };
onMounted(() => window.addEventListener('keydown', closeOnEscape));
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape));
</script>

<template>
  <Head title="Genel Bakış" />
  <AdminLayout eyebrow="Genel Bakış" title="Operasyon merkezi" description="Büyümeyi, pazaryeri hareketlerini ve müdahale gerektiren işleri tek ekrandan izle.">
    <main class="mx-auto max-w-[1600px] space-y-6 px-5 py-8 lg:px-8">
      <section :class="['flex flex-col gap-4 rounded-2xl border p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between', health.issueCount ? 'border-amber-200 bg-amber-50/70' : 'border-emerald-200 bg-emerald-50/70']">
        <div class="flex items-start gap-3">
          <span :class="['mt-1 block h-2.5 w-2.5 rounded-full ring-4', health.issueCount ? 'bg-amber-500 ring-amber-100' : 'bg-emerald-500 ring-emerald-100']"></span>
          <div>
            <h2 class="font-semibold text-slate-950">{{ health.issueCount ? `${number(health.issueCount)} konu dikkat bekliyor` : 'Operasyon akışı normal' }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ health.issueCount ? 'Bekleyen incelemeler veya aksayan duyurular aşağıda ayrıntılı gösteriliyor.' : 'Bekleyen güvenlik incelemesi ya da gecikmiş duyuru bulunmuyor.' }}</p>
          </div>
        </div>
        <p class="text-xs font-medium text-slate-500">Son hesaplama {{ generatedTime }} · İstanbul</p>
      </section>

      <section class="grid gap-5 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Büyüme</p><h2 class="mt-1 text-xl font-semibold text-slate-950">Kullanıcılar</h2><p class="mt-1 text-sm text-slate-600">Silinen hesaplar büyüme verilerine dahil edilmez.</p></div>
            <Link href="/admin/users" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800">Yönet →</Link>
          </div>
          <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-slate-950 p-4 text-white"><p class="text-xs font-semibold text-slate-300">Toplam</p><p class="mt-2 text-2xl font-semibold">{{ number(users.total) }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Aktif</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number(users.active) }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Bugün yeni</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number(users.today) }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Son 30 gün</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number(users.last30Days) }}</p></div>
          </div>
          <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 text-sm"><span class="font-semibold text-slate-700">Son 7 gün: {{ number(users.last7Days) }}</span><span :class="['font-semibold', deltaClass(usersDelta)]">{{ deltaText(usersDelta) }}</span></div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Pazaryeri</p><h2 class="mt-1 text-xl font-semibold text-slate-950">İlanlar</h2><p class="mt-1 text-sm text-slate-600">Yalnızca panelde bulunan güncel ilan kayıtları.</p></div>
            <Link href="/admin/listings" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800">Yönet →</Link>
          </div>
          <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <Link href="/admin/listings" class="rounded-xl bg-slate-950 p-4 text-white"><p class="text-xs font-semibold text-slate-300">Toplam</p><p class="mt-2 text-2xl font-semibold">{{ number(listingMetrics.total) }}</p></Link>
            <Link href="/admin/listings?status=active" class="rounded-xl bg-emerald-50 p-4"><p class="text-xs font-semibold text-emerald-700">Aktif</p><p class="mt-2 text-2xl font-semibold text-emerald-950">{{ number(listingMetrics.active) }}</p></Link>
            <Link href="/admin/listings?status=reserved" class="rounded-xl bg-amber-50 p-4"><p class="text-xs font-semibold text-amber-700">Rezerve</p><p class="mt-2 text-2xl font-semibold text-amber-950">{{ number(listingMetrics.reserved) }}</p></Link>
            <Link href="/admin/listings?status=completed" class="rounded-xl bg-sky-50 p-4"><p class="text-xs font-semibold text-sky-700">Tamamlandı</p><p class="mt-2 text-2xl font-semibold text-sky-950">{{ number(listingMetrics.completed) }}</p></Link>
            <Link href="/admin/listings?status=cancelled" class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">İptal</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number(listingMetrics.cancelled) }}</p></Link>
          </div>
          <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-3">
            <p class="text-sm text-slate-600"><span class="font-semibold text-slate-900">{{ number(listingMetrics.materials) }}</span> güncel ambalaj</p>
            <p class="text-sm text-slate-600"><span class="font-semibold text-slate-900">{{ number(listingMetrics.today) }}</span> bugün yeni</p>
            <p :class="['text-sm font-semibold sm:text-right', deltaClass(listingsDelta)]">{{ number(listingMetrics.last7Days) }} son 7 gün · {{ deltaText(listingsDelta) }}</p>
          </div>
        </article>
      </section>

      <section class="grid gap-5 xl:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
          <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">İşlem akışı</p><h2 class="mt-1 text-xl font-semibold text-slate-950">Alım talepleri ve teslimatlar</h2></div><Link href="/admin/listings" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900">İlanlarda incele →</Link></div>
          <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-amber-50 p-4"><p class="text-xs font-semibold text-amber-700">Bekleyen talep</p><p class="mt-2 text-2xl font-semibold text-amber-950">{{ number(transactions.pending) }}</p></div>
            <div class="rounded-xl bg-violet-50 p-4"><p class="text-xs font-semibold text-violet-700">Rezerve işlem</p><p class="mt-2 text-2xl font-semibold text-violet-950">{{ number(transactions.reserved) }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Mesaj görüşmesi</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number(transactions.inquiries) }}</p></div>
            <div class="rounded-xl bg-emerald-50 p-4"><p class="text-xs font-semibold text-emerald-700">Bugün tamamlanan</p><p class="mt-2 text-2xl font-semibold text-emerald-950">{{ number(transactions.completedToday) }}</p></div>
          </div>
          <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 text-sm"><span class="font-semibold text-slate-700">Son 7 günde {{ number(transactions.completedLast7Days) }} tamamlanan · {{ number(transactions.cancelledLast7Days) }} iptal</span><span :class="['font-semibold', deltaClass(transactionsDelta)]">{{ deltaText(transactionsDelta) }}</span></div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Edinme</p><h2 class="mt-1 text-xl font-semibold text-slate-950">İndirme bağlantısı</h2></div><button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-800" @click="showDownloadDetails = true">Ayrıntı</button></div>
          <p class="mt-1 text-sm text-slate-600">Botlar hariç gerçek <code>/indir</code> ziyaretleri.</p>
          <div class="mt-5 grid grid-cols-3 gap-2">
            <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Bugün</p><p class="mt-2 text-xl font-semibold text-slate-950">{{ number(downloadClicks.today) }}</p></div>
            <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">7 gün</p><p class="mt-2 text-xl font-semibold text-slate-950">{{ number(downloadClicks.last7Days) }}</p></div>
            <div class="rounded-xl bg-slate-950 p-3 text-white"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-300">Tümü</p><p class="mt-2 text-xl font-semibold">{{ number(downloadClicks.total) }}</p></div>
          </div>
        </article>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">İletişim takvimi</p><h2 class="mt-1 text-xl font-semibold text-slate-950">Duyuru sağlığı</h2><p class="mt-1 text-sm text-slate-600">Dünkü sonuç, bugünkü akış ve yarının planı İstanbul saatine göre.</p></div><Link href="/admin/announcements" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-800">Tüm duyurular →</Link></div>
        <div class="mt-5 grid gap-4 lg:grid-cols-3">
          <article v-for="day in announcements.days" :key="day.key" :class="['rounded-2xl border p-4', day.key === 'today' ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/60']">
            <div class="flex items-center justify-between"><h3 class="font-semibold text-slate-950">{{ day.label }}</h3><span class="text-xs font-medium text-slate-500">{{ dayDate(day.date) }}</span></div>
            <div v-if="day.items.length" class="mt-4 space-y-3">
              <div v-for="item in day.items" :key="item.key" class="rounded-xl border border-white bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-900">{{ item.title }}</p><p class="mt-1 text-xs font-medium text-slate-500">{{ time(item.scheduledFor) }}</p></div><span :class="['shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset', stateMeta[item.state].classes]">{{ stateMeta[item.state].label }}</span></div>
                <div v-if="item.state === 'completed'" class="mt-3 grid grid-cols-3 gap-2 text-xs"><p><span class="block font-semibold text-slate-900">{{ number(item.recipients) }}</span><span class="text-slate-500">Uygulama içi</span></p><p><span class="block font-semibold text-slate-900">{{ number(item.pushAccepted) }}</span><span class="text-slate-500">Push kabul</span></p><p><span class="block font-semibold text-slate-900">{{ number(item.pushFailed) }}</span><span class="text-slate-500">Push hata</span></p></div>
                <p v-if="item.error" class="mt-3 line-clamp-2 text-xs font-medium text-red-700">{{ item.error }}</p>
              </div>
            </div>
            <div v-else class="mt-4 rounded-xl border border-dashed border-slate-200 bg-white/70 px-4 py-7 text-center"><p class="text-sm font-semibold text-slate-600">Plan bulunmuyor</p><p class="mt-1 text-xs text-slate-500">Bu gün için kayıtlı gönderim yok.</p></div>
          </article>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Yönetim kuyruğu</p><h2 class="mt-1 text-xl font-semibold text-slate-950">Bekleyen incelemeler</h2><p class="mt-1 text-sm text-slate-600">Her bildirim türü kendi yönetim alanına gider.</p></div><span :class="['rounded-full px-3 py-1.5 text-xs font-bold', moderationTotal ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-800']">{{ moderationTotal ? `${number(moderationTotal)} bekliyor` : 'Kuyruk temiz' }}</span></div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><Link v-for="item in moderation" :key="item.key" :href="item.href" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-4 transition hover:border-emerald-300 hover:bg-emerald-50/30"><span class="text-sm font-semibold text-slate-800">{{ item.label }}</span><span :class="['rounded-full px-2.5 py-1 text-xs font-bold', item.count ? 'bg-red-50 text-red-800' : 'bg-slate-100 text-slate-600']">{{ number(item.count) }}</span></Link></div>
      </section>
    </main>

    <Teleport to="body">
      <div v-if="showDownloadDetails" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="showDownloadDetails = false">
        <section class="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
          <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Son 30 gün</p><h2 class="mt-1 text-xl font-semibold text-slate-950">İndirme bağlantısı ayrıntıları</h2><p class="mt-1 text-sm text-slate-600">Platform ve kaynak adları birleştirilmiş gerçek ziyaret dağılımı.</p></div><button type="button" class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200" @click="showDownloadDetails = false">Kapat</button></div>
          <div class="mt-6 grid gap-6 md:grid-cols-2">
            <div><h3 class="text-sm font-semibold text-slate-900">Platformlar</h3><div class="mt-3 space-y-2"><div v-for="item in downloadClicks.platforms" :key="item.name" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3"><span class="text-sm font-medium text-slate-700">{{ platformLabels[item.name] || item.name }}</span><span class="font-semibold text-slate-950">{{ number(item.clicks) }}</span></div><p v-if="!downloadClicks.platforms.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Henüz veri yok.</p></div></div>
            <div><h3 class="text-sm font-semibold text-slate-900">Kaynaklar</h3><div class="mt-3 space-y-2"><div v-for="item in downloadClicks.sources" :key="item.name" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3"><span class="text-sm font-medium text-slate-700">{{ sourceLabels[item.name] || item.name }}</span><span class="font-semibold text-slate-950">{{ number(item.clicks) }}</span></div><p v-if="!downloadClicks.sources.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Henüz veri yok.</p></div></div>
          </div>
        </section>
      </div>
    </Teleport>
  </AdminLayout>
</template>
