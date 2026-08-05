<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({ riskCase: Object });
const flash = computed(() => usePage().props.flash?.success);

const actions = { clear: 'Puanları onayla', revoke: 'Puanları iptal et', restore: 'Puanları iade et', reopen: 'Yeniden incele' };
const availableActions = computed(() => {
  if (props.riskCase.status === 'pending') return ['clear', 'revoke'];
  if (props.riskCase.status === 'confirmed') return ['restore', 'reopen'];
  return ['revoke', 'reopen'];
});
const decisionNotes = {
  clear: 'Otomatik risk sinyalleri, işlem kanıtları ve tarafların geçmişi birlikte incelendi. Teslimatın gerçek ve puan hareketinin kurallara uygun olduğu değerlendirildiğinden satıcı döngü puanı onaylandı.',
  revoke: 'Otomatik risk sinyalleri ve işlem kanıtları incelendi. Puan hareketinin olağan dışı veya kurallara aykırı olduğu doğrulandığından ilgili satıcı döngü puanı iptal edildi.',
  restore: 'Yeni kanıtlar ve yeniden inceleme sonucunda teslimatın gerçek olduğu doğrulandı. Daha önce iptal edilen satıcı döngü puanı yeniden hesaba eklendi.',
  reopen: 'Yeni bilgi, itiraz veya ek inceleme gereksinimi nedeniyle puan hareketi yeniden incelemeye alındı. Nihai karar verilene kadar ilgili puan beklemede tutulacaktır.',
};
const form = useForm({ action: availableActions.value[0], reason: decisionNotes[availableActions.value[0]] });
watch(() => form.action, action => { form.reason = decisionNotes[action] || ''; });

const statuses = { pending: 'İncelenecek', cleared: 'Temiz', confirmed: 'Hile doğrulandı' };
const statusClasses = { pending: 'bg-amber-50 text-amber-900', cleared: 'bg-emerald-50 text-emerald-800', confirmed: 'bg-red-50 text-red-800' };
const severities = { low: 'Düşük risk', medium: 'Orta risk', high: 'Yüksek risk' };
const severityClasses = { low: 'bg-slate-100 text-slate-700', medium: 'bg-orange-50 text-orange-800', high: 'bg-red-50 text-red-800' };
const riskBarClasses = { low: 'bg-slate-500', medium: 'bg-orange-500', high: 'bg-red-600' };
const entryStatuses = { active: 'Sıralamaya dahil', pending_review: 'İncelemede bekliyor', revoked: 'İptal edildi' };
const entryStatusClasses = { active: 'bg-emerald-50 text-emerald-800', pending_review: 'bg-amber-50 text-amber-900', revoked: 'bg-red-50 text-red-800' };
const materialNames = { pet: 'PET', glass: 'Cam', aluminum: 'Alüminyum' };

const ruleMeta = {
  same_pair_24h_high: { title: 'Çok sık tekrarlanan taraf eşleşmesi', period: 'Son 24 saat' },
  same_pair_24h: { title: 'Tekrarlanan taraf eşleşmesi', period: 'Son 24 saat' },
  same_pair_7d: { title: 'Sık tekrarlanan taraf eşleşmesi', period: 'Son 7 gün' },
  user_velocity_24h_high: { title: 'Çok yüksek işlem yoğunluğu', period: 'Son 24 saat' },
  user_velocity_24h: { title: 'Yüksek işlem yoğunluğu', period: 'Son 24 saat' },
  maximum_points: { title: 'Tek işlemde azami puan', period: 'İşlem puanı' },
  high_points: { title: 'Olağan dışı yüksek puan', period: 'İşlem puanı' },
  instant_completion: { title: 'Olağan dışı hızlı tamamlama', period: 'İşlem süresi' },
};
const ruleTitle = rule => ruleMeta[rule.code]?.title || 'Otomatik risk sinyali';
const rulePeriod = rule => ruleMeta[rule.code]?.period || 'Otomatik denetim';

const formatDuration = seconds => {
  if (seconds === null || seconds === undefined) return 'Ölçülemedi';
  const value = Math.max(0, Number(seconds));
  const minutes = Math.floor(value / 60);
  const remaining = value % 60;
  if (!minutes) return `${remaining} sn`;
  return remaining ? `${minutes} dk ${remaining} sn` : `${minutes} dk`;
};
const evidenceDefinitions = [
  { key: 'points', label: 'İşlemde oluşan puan', description: 'Bu teslimat tamamlandığında hesaplanan döngü puanı.', format: value => `${Number(value).toLocaleString('tr-TR')} puan` },
  { key: 'pairCompleted24hBefore', label: 'Aynı tarafların önceki 24 saatteki işlemleri', description: 'Bu işlemden önce aynı alıcı ve satıcının birlikte tamamladığı teslimatlar.', format: value => `${value} işlem` },
  { key: 'pairCompleted7dBefore', label: 'Aynı tarafların önceki 7 gündeki işlemleri', description: 'Bu işlemden önceki yedi günde aynı tarafların tamamladığı teslimatlar.', format: value => `${value} işlem` },
  { key: 'buyerCompleted24hBefore', label: 'Alıcının önceki 24 saatteki işlemleri', description: 'Alıcının bu işlemden önce tamamladığı diğer teslimatlar.', format: value => `${value} işlem` },
  { key: 'sellerCompleted24hBefore', label: 'Satıcının önceki 24 saatteki işlemleri', description: 'Satıcının bu işlemden önce tamamladığı diğer teslimatlar.', format: value => `${value} işlem` },
  { key: 'secondsFromAcceptToComplete', label: 'Kabulden tamamlamaya geçen süre', description: 'Rezervasyonun kabul edilmesi ile teslimatın tamamlanması arasındaki süre.', format: formatDuration },
];
const evidenceItems = computed(() => evidenceDefinitions
  .filter(item => Object.prototype.hasOwnProperty.call(props.riskCase.evidence || {}, item.key))
  .map(item => ({ ...item, value: item.format(props.riskCase.evidence[item.key]) })));

const submit = () => form.patch(`/admin/cycle-risk-cases/${props.riskCase.id}`, { preserveScroll: true });
</script>

<template>
  <Head :title="`Puan Vakası #${riskCase.id}`" />
  <AdminLayout eyebrow="Güvenlik" :title="`Puan vakası #${riskCase.id}`" description="İşlem kanıtlarını değerlendir ve denetlenebilir bir yönetici kararı kaydet.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <div class="mb-5"><Link href="/admin/cycle-risk-cases" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 transition hover:text-emerald-700"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m15 18-6-6 6-6"/></svg>Puan denetimine dön</Link></div>
      <div v-if="flash" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ flash }}</div>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-5">
          <div><p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Vaka #{{ riskCase.id }}</p><h2 class="mt-1 text-2xl font-semibold text-slate-950">İşlem puanı güvenlik incelemesi</h2><p class="mt-2 text-sm text-slate-600">{{ $adminDate(riskCase.detectedAt) }} · {{ riskCase.listingArea }} · Talep #{{ riskCase.transaction.id }}</p></div>
          <div class="flex flex-wrap gap-2"><span :class="['rounded-full px-3 py-1.5 text-xs font-semibold', severityClasses[riskCase.severity]]">{{ severities[riskCase.severity] }}</span><span :class="['rounded-full px-3 py-1.5 text-xs font-semibold', statusClasses[riskCase.status]]">{{ statuses[riskCase.status] }}</span></div>
        </div>
        <div class="mt-5 grid gap-4 border-t border-slate-200 pt-5 md:grid-cols-[220px_1fr] md:items-center">
          <div><p class="text-sm font-semibold text-slate-700">Toplam risk puanı</p><p class="mt-1 text-3xl font-semibold text-slate-950">{{ riskCase.riskScore }}<span class="text-lg text-slate-500">/100</span></p></div>
          <div><div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div :class="['h-full rounded-full', riskBarClasses[riskCase.severity]]" :style="{ width: `${riskCase.riskScore}%` }" /></div><p class="mt-2 text-xs leading-5 text-slate-600">Bu puan otomatik sinyallerin toplamıdır; tek başına ihlal veya hile kanıtı sayılmaz.</p></div>
        </div>
      </section>

      <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(360px,.55fr)]">
        <div class="space-y-5">
          <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">Otomatik risk sinyalleri</h2><p class="mt-1 text-sm text-slate-600">Sistemin incelemeye gerekçe olarak işaretlediği durumlar.</p></div>
            <div class="grid gap-3 p-5 md:grid-cols-2">
              <article v-for="rule in riskCase.rules" :key="rule.code" class="rounded-xl border border-orange-200 bg-orange-50 p-4">
                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-orange-700">{{ rulePeriod(rule) }}</p><h3 class="mt-1 font-semibold text-orange-950">{{ ruleTitle(rule) }}</h3></div><span class="shrink-0 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-orange-900">+{{ rule.score }} risk</span></div>
                <p class="mt-3 text-sm leading-6 text-orange-900">{{ rule.label }}</p>
              </article>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">İşlem kanıtları</h2><p class="mt-1 text-sm text-slate-600">Karar verirken kullanılabilecek, işlem anında kaydedilmiş ölçümler.</p></div>
            <div class="grid gap-3 p-5 md:grid-cols-2">
              <article v-for="item in evidenceItems" :key="item.key" class="rounded-xl border border-slate-200 p-4"><p class="text-sm font-semibold text-slate-950">{{ item.label }}</p><p class="mt-2 text-2xl font-semibold text-emerald-800">{{ item.value }}</p><p class="mt-2 text-xs leading-5 text-slate-600">{{ item.description }}</p></article>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">
              <div class="grid gap-3 sm:grid-cols-2"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-600">Rezervasyon kabulü</p><p class="mt-2 text-sm font-semibold text-slate-950">{{ $adminDate(riskCase.transaction.acceptedAt, 'Kaydedilmedi') }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-600">Teslimat tamamlanması</p><p class="mt-2 text-sm font-semibold text-slate-950">{{ $adminDate(riskCase.transaction.completedAt, 'Kaydedilmedi') }}</p></div></div>
              <div class="mt-4 flex flex-wrap gap-2"><span v-for="item in riskCase.transaction.listing.materials" :key="item.type" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900">{{ materialNames[item.type] }} · {{ Number(item.quantity).toLocaleString('tr-TR') }} adet</span></div>
            </div>
          </section>

          <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">Yönetici karar geçmişi</h2><p class="mt-1 text-sm text-slate-600">Bu vaka için kaydedilmiş değiştirilemez denetim kayıtları.</p></div>
            <div v-if="riskCase.audits.length" class="divide-y divide-slate-100">
              <article v-for="audit in riskCase.audits" :key="audit.id" class="p-5"><div class="flex flex-wrap items-center justify-between gap-2"><p class="font-semibold text-slate-950">{{ actions[audit.action] }}</p><p class="text-xs font-medium text-slate-600">{{ $adminDate(audit.createdAt) }}</p></div><p class="mt-3 text-sm leading-6 text-slate-700">{{ audit.reason }}</p><p class="mt-3 text-xs text-slate-600">{{ audit.admin?.name }} · {{ audit.admin?.email }}</p></article>
            </div>
            <p v-else class="px-5 py-12 text-center text-sm font-medium text-slate-600">Henüz yönetici kararı bulunmuyor.</p>
          </section>
        </div>

        <aside class="space-y-5 xl:sticky xl:top-5 xl:self-start">
          <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">İşlemin tarafları</h2></div>
            <div class="divide-y divide-slate-100">
              <article v-for="(person, role) in { Alıcı: riskCase.transaction.buyer, Satıcı: riskCase.transaction.seller }" :key="role" class="p-5"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">{{ role }}</p><p class="mt-2 font-semibold text-slate-950">{{ person.name }}</p><p class="mt-1 text-sm text-slate-600">{{ person.email }}</p><p class="mt-3 text-xs font-medium text-slate-600">{{ person.completed_transactions }} tamamlanan işlem</p></article>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">Puan hareketleri</h2><p class="mt-1 text-sm text-slate-600">Taraflara ait mevcut puan kayıtları.</p></div>
            <div class="space-y-3 p-5"><article v-for="entry in riskCase.entries" :key="entry.id" class="rounded-xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-3"><p class="font-semibold text-slate-950">{{ entry.role === 'buyer' ? 'Alıcı' : 'Satıcı' }}</p><p class="font-semibold text-emerald-800">{{ Number(entry.points).toLocaleString('tr-TR') }} puan</p></div><span :class="['mt-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', entryStatusClasses[entry.status]]">{{ entryStatuses[entry.status] }}</span></article></div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">İnceleme kararı</h2><p class="mt-1 text-sm text-slate-600">Karar ve gerekçe denetim kaydında korunur.</p></div>
            <div class="p-5">
              <label class="block text-xs font-semibold text-slate-700">Uygulanacak işlem <span class="text-red-600">*</span><select v-model="form.action" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"><option v-for="action in availableActions" :key="action" :value="action">{{ actions[action] }}</option></select></label>
              <label class="mt-4 block text-xs font-semibold text-slate-700">Yönetici notu <span class="text-red-600">*</span><textarea v-model="form.reason" rows="7" maxlength="1000" placeholder="Seçilen işleme uygun not otomatik oluşturulur; istersen düzenleyebilirsin." class="mt-1.5 w-full rounded-xl border border-slate-300 p-3 text-sm leading-6 text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" /></label>
              <p v-if="form.errors.action" class="mt-2 text-sm font-semibold text-red-700">{{ form.errors.action }}</p><p v-if="form.errors.reason" class="mt-2 text-sm font-semibold text-red-700">{{ form.errors.reason }}</p>
              <button type="button" :disabled="form.processing" :class="['mt-4 h-11 w-full rounded-xl px-4 text-sm font-semibold text-white disabled:opacity-50', form.action === 'revoke' ? 'bg-red-700' : 'bg-forest-700']" @click="submit">{{ form.processing ? 'Kaydediliyor…' : 'Kararı kaydet' }}</button>
              <p class="mt-4 text-xs leading-5 text-slate-600">Her karar; önceki ve yeni durum, yönetici, IP adresi, zaman ve gerekçeyle değiştirilemez denetim kaydına yazılır.</p>
            </div>
          </section>
        </aside>
      </div>
    </main>
  </AdminLayout>
</template>