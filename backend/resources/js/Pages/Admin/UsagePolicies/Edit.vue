<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ policy: Object, meta: Object });
const flash = computed(() => usePage().props.flash?.success);
const form = useForm({ ...props.policy });

const groups = [
  {
    key: 'account',
    eyebrow: 'HESAP VE İLAN',
    title: 'Hesap ve ilan sınırları',
    description: 'Yeni ve normal hesapların ilan oluşturma kapasitesini belirler.',
    fields: [
      { key: 'new_account_hours', label: 'Yeni hesap dönemi', unit: 'saat', help: 'Hesap açıldıktan sonra yeni kullanıcı kurallarının uygulanacağı süre.' },
      { key: 'new_account_listing_limit', label: 'Yeni hesap ilan limiti', unit: 'ilan / 24 saat', help: 'Yeni hesap dönemindeki kullanıcının 24 saatte yayınlayabileceği ilan.' },
      { key: 'listing_24h_limit', label: 'Normal hesap ilan limiti', unit: 'ilan / 24 saat', help: 'Yeni hesap dönemini tamamlayan kullanıcının günlük ilan hakkı.' },
      { key: 'active_listing_limit', label: 'Aktif ilan kontenjanı', unit: 'aktif ilan', help: 'Bir kullanıcının aynı anda yayında tutabileceği en fazla ilan.' },
    ],
  },
  {
    key: 'pickup',
    eyebrow: 'ALIM TALEPLERİ',
    title: 'Alım talebi sınırları',
    description: 'Talep oluşturma yoğunluğunu ve ilan başına bekleyen alıcı sayısını kontrol eder.',
    fields: [
      { key: 'new_account_pickup_limit', label: 'Yeni hesap talep limiti', unit: 'talep / 24 saat', help: 'Yeni kullanıcının bir günde oluşturabileceği alım talebi.' },
      { key: 'pickup_24h_limit', label: 'Normal hesap talep limiti', unit: 'talep / 24 saat', help: 'Normal kullanıcının bir günde oluşturabileceği alım talebi.' },
      { key: 'active_pickup_limit', label: 'Aktif talep kontenjanı', unit: 'aktif talep', help: 'Bir kullanıcının aynı anda bekleyen veya rezerve talep sayısı.' },
      { key: 'listing_pending_pickup_limit', label: 'İlan başına bekleyen alıcı', unit: 'alıcı', help: 'Tek bir ilana aynı anda talep gönderebilecek en fazla alıcı.' },
    ],
  },
  {
    key: 'contact',
    eyebrow: 'YENİ GÖRÜŞMELER',
    title: 'Görüşme başlatma sınırları',
    description: 'Farklı ilan ve satıcılarla gereksiz veya otomatik iletişim kurulmasını sınırlar.',
    fields: [
      { key: 'new_account_contact_limit', label: 'Yeni hesap toplam görüşme limiti', unit: 'görüşme', help: 'Yeni kullanıcının talep ve mesaj dahil başlatabileceği toplam görüşme.' },
      { key: 'contact_24h_limit', label: 'Normal hesap toplam görüşme limiti', unit: 'görüşme / 24 saat', help: 'Normal kullanıcının 24 saatte başlatabileceği toplam yeni görüşme.' },
      { key: 'new_account_message_conversation_limit', label: 'Yeni hesap mesaj görüşmesi', unit: 'görüşme', help: 'Yeni hesabın yalnızca mesaj amacıyla başlatabileceği görüşme.' },
      { key: 'message_conversation_24h_limit', label: 'Normal hesap mesaj görüşmesi', unit: 'görüşme / 24 saat', help: 'Normal hesabın mesaj amacıyla başlatabileceği günlük görüşme.' },
      { key: 'same_seller_contact_24h_limit', label: 'Aynı satıcıyla yeni görüşme', unit: 'görüşme / 24 saat', help: 'Bir kullanıcının aynı satıcıyla farklı ilanlarda başlatabileceği görüşme.' },
      { key: 'contact_cooldown_seconds', label: 'Görüşmeler arası bekleme', unit: 'saniye', help: 'Arka arkaya yeni görüşme açılmasını yavaşlatan bekleme süresi.', min: 0 },
    ],
  },
  {
    key: 'message',
    eyebrow: 'MESAJLAŞMA',
    title: 'Mesaj gönderme sınırları',
    description: 'Mevcut sohbetlerde spam ve toplu mesaj davranışını kontrol eder.',
    fields: [
      { key: 'messages_per_minute', label: 'Dakikalık mesaj limiti', unit: 'mesaj / dakika', help: 'Kısa sürede otomatik veya art arda mesaj gönderimini sınırlar.' },
      { key: 'messages_per_hour', label: 'Saatlik mesaj limiti', unit: 'mesaj / saat', help: 'Bir kullanıcının bir saat içinde gönderebileceği toplam mesaj.' },
      { key: 'messages_per_24h', label: 'Günlük mesaj limiti', unit: 'mesaj / 24 saat', help: 'Bir kullanıcının son 24 saatte gönderebileceği toplam mesaj.' },
      { key: 'unanswered_message_limit', label: 'Yanıtsız mesaj limiti', unit: 'mesaj', help: 'Karşı taraf cevap vermeden art arda gönderilebilecek mesaj.' },
    ],
  },
];

const consistencyIssues = computed(() => {
  const issues = [];
  if (form.new_account_pickup_limit > form.new_account_contact_limit) issues.push('Yeni hesap talep limiti, yeni hesap toplam görüşme limitinden büyük olamaz.');
  if (form.pickup_24h_limit > form.contact_24h_limit) issues.push('Normal hesap talep limiti, normal hesap toplam görüşme limitinden büyük olamaz.');
  if (form.new_account_message_conversation_limit > form.new_account_contact_limit) issues.push('Yeni hesap mesaj görüşmesi limiti, yeni hesap toplam görüşme limitinden büyük olamaz.');
  if (form.message_conversation_24h_limit > form.contact_24h_limit) issues.push('Normal hesap mesaj görüşmesi limiti, normal hesap toplam görüşme limitinden büyük olamaz.');
  if (form.messages_per_minute > form.messages_per_hour) issues.push('Dakikalık mesaj limiti, saatlik mesaj limitinden büyük olamaz.');
  if (form.messages_per_hour > form.messages_per_24h) issues.push('Saatlik mesaj limiti, günlük mesaj limitinden büyük olamaz.');
  return issues;
});
const canSave = computed(() => form.isDirty && !form.processing && consistencyIssues.value.length === 0);
const save = () => {
  if (!canSave.value) return;
  form.patch('/admin/usage-policies', { preserveScroll: true, onSuccess: () => form.defaults() });
};
const resetChanges = () => { form.reset(); form.clearErrors(); };
</script>

<template>
  <Head title="Kullanım Limitleri" />
  <AdminLayout eyebrow="Sistem Ayarları" title="Kullanım limitleri" description="İlan, talep, görüşme ve mesajlaşma sınırlarını merkezi ve denetlenebilir biçimde yönet.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <div v-if="flash" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ flash }}</div>

      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-700">Yeni hesap dönemi</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ form.new_account_hours }} saat</p><p class="mt-1 text-xs text-slate-600">Kısıtlı başlangıç süresi</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-700">Normal hesap talep hakkı</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ form.pickup_24h_limit }}</p><p class="mt-1 text-xs text-slate-600">Son 24 saatte oluşturulabilir</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-700">Yeni görüşme hakkı</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ form.contact_24h_limit }}</p><p class="mt-1 text-xs text-slate-600">Normal hesap · son 24 saat</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-700">Günlük mesaj hakkı</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ form.messages_per_24h }}</p><p class="mt-1 text-xs text-slate-600">Kullanıcı başına · son 24 saat</p></article>
      </section>

      <section class="mt-5 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
        <div><h2 class="text-sm font-semibold text-amber-950">Değişiklikler anında uygulanır</h2><p class="mt-1 max-w-4xl text-sm leading-6 text-amber-900">Kaydettiğin değerler mobil uygulamadaki ilan, talep, yeni görüşme ve mesaj kontrollerinde hemen kullanılmaya başlanır. Çok düşük değerler gerçek kullanıcıları engelleyebilir; çok yüksek değerler spam korumasını zayıflatabilir.</p></div>
        <div class="text-sm text-amber-900"><p class="font-semibold">Son güncelleme</p><p class="mt-1">{{ meta.updatedBy?.name || 'Sistem' }} · {{ $adminDate(meta.updatedAt) }}</p></div>
      </section>

      <form class="mt-5 space-y-5" @submit.prevent="save">
        <section v-for="group in groups" :key="group.key" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-5 py-4"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">{{ group.eyebrow }}</p><h2 class="mt-1 text-lg font-semibold text-slate-950">{{ group.title }}</h2><p class="mt-1 text-sm text-slate-600">{{ group.description }}</p></div>
          <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
            <label v-for="field in group.fields" :key="field.key" class="rounded-xl border border-slate-200 p-4 transition focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-100">
              <span class="text-sm font-semibold text-slate-950">{{ field.label }} <span class="text-red-600">*</span></span>
              <span class="mt-1 block min-h-10 text-xs leading-5 text-slate-600">{{ field.help }}</span>
              <div class="mt-3 flex h-11 overflow-hidden rounded-xl border border-slate-300 bg-white">
                <input v-model.number="form[field.key]" type="number" inputmode="numeric" :min="field.min ?? 1" max="10000" required class="min-w-0 flex-1 px-3 text-sm font-semibold text-slate-950 outline-none" />
                <span class="flex items-center border-l border-slate-200 bg-slate-50 px-3 text-xs font-medium text-slate-600">{{ field.unit }}</span>
              </div>
              <p v-if="form.errors[field.key]" class="mt-2 text-xs font-semibold text-red-700">{{ form.errors[field.key] }}</p>
            </label>
          </div>
        </section>

        <section v-if="consistencyIssues.length" class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4"><h2 class="text-sm font-semibold text-red-900">Birbiriyle çelişen limitler var</h2><ul class="mt-2 space-y-1 text-sm leading-6 text-red-800"><li v-for="issue in consistencyIssues" :key="issue">• {{ issue }}</li></ul></section>
        <section v-if="Object.keys(form.errors).length && !consistencyIssues.length" class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4"><h2 class="text-sm font-semibold text-red-900">Bazı değerler kaydedilemedi</h2><p class="mt-1 text-sm text-red-800">Kırmızı uyarı gösterilen alanları kontrol edip tekrar dene.</p></section>

        <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 shadow-xl backdrop-blur">
          <div><p class="text-sm font-semibold text-slate-950">{{ form.isDirty ? 'Kaydedilmemiş değişiklikler var' : 'Tüm değişiklikler kaydedildi' }}</p><p class="mt-1 text-xs text-slate-600">Kaydetmeden önce limit ilişkileri otomatik olarak kontrol edilir.</p></div>
          <div class="flex gap-2"><button type="button" :disabled="!form.isDirty || form.processing" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" @click="resetChanges">Değişiklikleri geri al</button><button type="submit" :disabled="!canSave" class="h-11 rounded-xl bg-forest-700 px-6 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">{{ form.processing ? 'Kaydediliyor…' : 'Limitleri kaydet' }}</button></div>
        </div>
      </form>
    </main>
  </AdminLayout>
</template>