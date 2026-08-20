<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ campaigns: Object, audience: Object, limits: Object });
const composeOpen = ref(false);
const editingCampaign = ref(null);
const viewingCampaign = ref(null);
const sendCandidate = ref(null);
const deleteCandidate = ref(null);
const actionPending = ref(false);
const deleteForm = useForm({});

const form = useForm({
  type: 'marketing',
  title: '',
  body: '',
  audience: 'all_active',
  targetUserIdsText: '',
  targetUserIds: [],
  pushEnabled: true,
  recurrence: 'none',
  scheduledAt: '',
  endsAt: '',
  submitAction: 'draft',
  action: '',
});

const statuses = {
  draft: 'Taslak',
  scheduled: 'Planlandı',
  sending: 'Gönderiliyor',
  completed: 'Tamamlandı',
  paused: 'Duraklatıldı',
  cancelled: 'İptal edildi',
};
const statusClasses = {
  draft: 'bg-slate-100 text-slate-700',
  scheduled: 'bg-sky-50 text-sky-800',
  sending: 'bg-amber-50 text-amber-900',
  completed: 'bg-emerald-50 text-emerald-800',
  paused: 'bg-violet-50 text-violet-800',
  cancelled: 'bg-red-50 text-red-800',
};
const types = { marketing: 'Duyuru / kampanya', system: 'Sistem duyurusu' };
const recurrences = { none: 'Tek gönderim', daily: 'Her gün', weekly: 'Her hafta' };
const dispatchStatuses = { processing: 'İşleniyor', completed: 'Tamamlandı', failed: 'Başarısız' };

const submit = action => {
  form.targetUserIds = form.audience === 'selected'
    ? [...new Set(form.targetUserIdsText.split(/[\s,;]+/).map(Number).filter(Number.isInteger))]
    : [];
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      editingCampaign.value = null;
      composeOpen.value = false;
    },
  };
  if (editingCampaign.value) {
    form.action = 'edit';
    form.patch(`/admin/announcements/${editingCampaign.value.id}`, options);
    return;
  }

  form.submitAction = action;
  form.post('/admin/announcements', options);
};

const act = (campaign, action) => router.patch(
  `/admin/announcements/${campaign.id}`,
  { action },
  { preserveScroll: true },
);

const audienceLabel = campaign => campaign.audience === 'selected'
  ? `${campaign.target_user_ids?.length || 0} seçili kullanıcı`
  : 'Tüm aktif kullanıcılar';

const creatorName = campaign => campaign.created_by?.name || campaign.createdBy?.name || '—';
const turkeyDateParts = value => {
  if (!value) return null;
  return Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Istanbul',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(new Date(value)).filter(part => part.type !== 'literal').map(part => [part.type, part.value]));
};
const turkeyDateTimeInput = value => {
  const parts = turkeyDateParts(value);
  return parts ? parts.year + '-' + parts.month + '-' + parts.day + 'T' + parts.hour + ':' + parts.minute : '';
};
const turkeyDateInput = value => {
  const parts = turkeyDateParts(value);
  return parts ? parts.year + '-' + parts.month + '-' + parts.day : '';
};
const turkeyDateLabel = value => value
  ? new Intl.DateTimeFormat('tr-TR', { timeZone: 'Europe/Istanbul', dateStyle: 'short' }).format(new Date(value))
  : 'Tek gönderim';
const campaignNumber = campaign => (props.campaigns.from || 1) + props.campaigns.data.findIndex(item => item.id === campaign.id);
const canEdit = campaign => campaign.runs_count === 0 && ['draft', 'scheduled', 'paused'].includes(campaign.status);
const openComposer = () => {
  form.reset();
  form.clearErrors();
  editingCampaign.value = null;
  composeOpen.value = true;
};
const openEditor = campaign => {
  form.reset();
  form.clearErrors();
  editingCampaign.value = campaign;
  form.type = campaign.type;
  form.title = campaign.title;
  form.body = campaign.body;
  form.audience = campaign.audience;
  form.targetUserIds = campaign.target_user_ids || [];
  form.targetUserIdsText = (campaign.target_user_ids || []).join(', ');
  form.pushEnabled = Boolean(campaign.push_enabled);
  form.recurrence = campaign.recurrence;
  form.scheduledAt = turkeyDateTimeInput(campaign.scheduled_at);
  form.endsAt = turkeyDateInput(campaign.ends_at);
  composeOpen.value = true;
};
const closeComposer = () => { if (!form.processing) { composeOpen.value = false; editingCampaign.value = null; } };
const openDetails = campaign => { viewingCampaign.value = campaign; };
const closeDetails = () => { viewingCampaign.value = null; };
const askSendNow = campaign => { sendCandidate.value = campaign; };
const closeSendConfirmation = () => { if (!actionPending.value) sendCandidate.value = null; };
const confirmSendNow = () => {
  if (!sendCandidate.value || actionPending.value) return;
  const campaign = sendCandidate.value;
  actionPending.value = true;
  router.patch(`/admin/announcements/${campaign.id}`, { action: 'send_now' }, {
    preserveScroll: true,
    onSuccess: () => { sendCandidate.value = null; },
    onFinish: () => { actionPending.value = false; },
  });
};
const askDelete = campaign => { deleteCandidate.value = campaign; };
const closeDeleteConfirmation = () => { if (!deleteForm.processing) deleteCandidate.value = null; };
const confirmDelete = () => {
  if (!deleteCandidate.value || deleteForm.processing) return;
  const campaignId = deleteCandidate.value.id;
  deleteForm.delete(`/admin/announcements/${campaignId}`, {
    preserveScroll: true,
    onSuccess: () => {
      if (viewingCampaign.value?.id === campaignId) viewingCampaign.value = null;
      deleteCandidate.value = null;
    },
  });
};
</script>

<template>
  <Head title="Duyurular" />
  <AdminLayout eyebrow="İletişim" title="Duyuru ve kampanyalar" description="Uygulama içi ve push bildirimlerini planla, yayınla ve gönderim sonuçlarını tek ekrandan izle.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">

      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-sm font-semibold text-slate-700">Aktif kullanıcı</p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ Number(audience.activeUsers).toLocaleString('tr-TR') }}</p>
          <p class="mt-1 text-xs text-slate-600">Uygulama içi bildirime uygun hesap</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-sm font-semibold text-slate-700">Push izni veren</p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ Number(audience.marketingOptIns).toLocaleString('tr-TR') }}</p>
          <p class="mt-1 text-xs text-slate-600">Kampanya push bildirimi alabilir</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-sm font-semibold text-slate-700">Yönetici gönderimi</p>
          <p class="mt-2 text-3xl font-semibold text-emerald-700">Serbest</p>
          <p class="mt-1 text-xs text-slate-600">Acil durumlarda günlük gönderim sınırı yok</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-sm font-semibold text-slate-700">Kayıtlı kampanya</p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ Number(campaigns.total).toLocaleString('tr-TR') }}</p>
          <p class="mt-1 text-xs text-slate-600">Taslaklar ve geçmiş gönderimler dahil</p>
        </article>
      </section>

      <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-950">Duyuru kayıtları</h2>
            <p class="mt-1 text-sm text-slate-600">Toplam {{ Number(campaigns.total).toLocaleString('tr-TR') }} sonuç · Sayfa {{ campaigns.current_page }} / {{ campaigns.last_page }}</p>
          </div>
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white transition hover:bg-forest-800" @click="openComposer">
            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>
            Yeni duyuru oluştur
          </button>
        </div>

        <div v-if="campaigns.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[960px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-700">
              <tr>
                <th class="px-5 py-3.5">Duyuru</th>
                <th class="px-5 py-3.5">Hedef kitle</th>
                <th class="px-5 py-3.5">Durum</th>
                <th class="px-5 py-3.5">Son gönderim</th>
                <th class="px-5 py-3.5 text-right">İşlemler</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(campaign, index) in campaigns.data" :key="campaign.id" class="text-slate-800 transition hover:bg-slate-50/80">
                <td class="max-w-[430px] px-5 py-4">
                  <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">#{{ (campaigns.from || 1) + index }} · {{ types[campaign.type] }}</p>
                  <p class="mt-1 truncate font-semibold text-slate-950">{{ campaign.title }}</p>
                </td>
                <td class="px-5 py-4">
                  <p class="font-medium text-slate-900">{{ audienceLabel(campaign) }}</p>
                  <p class="mt-1 text-xs text-slate-600">{{ campaign.push_enabled ? 'Uygulama içi + push' : 'Yalnızca uygulama içi' }}</p>
                </td>
                <td class="px-5 py-4">
                  <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', statusClasses[campaign.status]]">{{ statuses[campaign.status] }}</span>
                </td>
                <td class="px-5 py-4 text-slate-700">{{ $adminDate(campaign.last_sent_at, 'Henüz gönderilmedi') }}</td>
                <td class="px-5 py-4">
                  <div class="flex justify-end gap-2">
                    <button type="button" title="Duyuru ayrıntılarını görüntüle" aria-label="Duyuru ayrıntılarını görüntüle" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 transition hover:border-emerald-500 hover:text-emerald-700" @click="openDetails(campaign)">
                      <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </button>
                    <button v-if="canEdit(campaign)" type="button" title="Duyuruyu düzenle" aria-label="Duyuruyu düzenle" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 transition hover:border-sky-500 hover:bg-sky-50 hover:text-sky-700" @click="openEditor(campaign)">
                      <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></svg>
                    </button>
                    <button v-if="['draft', 'paused', 'completed'].includes(campaign.status)" type="button" title="Şimdi gönder" aria-label="Şimdi gönder" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 transition hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-700" @click="askSendNow(campaign)">
                      <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 11 18-8-8 18-2.5-7.5L3 11Z"/><path d="m10.5 13.5 4-4"/></svg>
                    </button>
                    <button v-if="campaign.status === 'scheduled'" type="button" title="Duraklat" aria-label="Duraklat" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 transition hover:border-amber-400 hover:bg-amber-50 hover:text-amber-800" @click="act(campaign, 'pause')">
                      <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5v14M15 5v14"/></svg>
                    </button>
                    <button v-if="campaign.status === 'paused'" type="button" title="Devam ettir" aria-label="Devam ettir" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-700 transition hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-700" @click="act(campaign, 'resume')">
                      <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 5 11 7-11 7V5Z"/></svg>
                    </button>
                    <button v-if="!['completed', 'cancelled'].includes(campaign.status)" type="button" title="İptal et" aria-label="İptal et" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700" @click="act(campaign, 'cancel')">
                      <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>                    <button type="button" :disabled="campaign.status === 'sending'" :title="campaign.status === 'sending' ? 'Gönderim sürerken silinemez' : 'Duyuruyu sil'" :aria-label="campaign.status === 'sending' ? 'Duyuru şu anda silinemez' : 'Duyuruyu sil'" class="grid size-9 place-items-center rounded-lg border border-slate-300 text-slate-600 transition hover:border-red-400 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-35" @click="askDelete(campaign)">
                      <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="px-5 py-16 text-center">
          <p class="font-semibold text-slate-900">Henüz duyuru kampanyası bulunmuyor</p>
          <p class="mt-1 text-sm text-slate-600">İlk duyuruyu “Yeni duyuru oluştur” düğmesinden ekleyebilirsin.</p>
        </div>
      </section>

      <nav v-if="campaigns.last_page > 1" aria-label="Duyuru sayfaları" class="mt-5 flex flex-wrap gap-2">
        <Link v-for="link in campaigns.links" :key="link.label" :href="link.url || ''" preserve-scroll :class="['rounded-lg border px-3 py-2 text-sm font-semibold', link.active ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-700', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" />
      </nav>
    </main>

    <div v-if="viewingCampaign" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="closeDetails" @keydown.esc.window="closeDetails">
      <section role="dialog" aria-modal="true" aria-labelledby="announcement-detail-title" class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
          <div><p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">#{{ campaignNumber(viewingCampaign) }} · {{ types[viewingCampaign.type] }}</p><h2 id="announcement-detail-title" class="mt-1 text-xl font-semibold text-slate-950">{{ viewingCampaign.title }}</h2><p class="mt-2 text-xs text-slate-600">{{ creatorName(viewingCampaign) }} tarafından {{ $adminDate(viewingCampaign.created_at) }} tarihinde oluşturuldu.</p></div>
          <button type="button" title="Kapat" aria-label="Duyuru ayrıntılarını kapat" class="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50" @click="closeDetails"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </header>

        <div class="overflow-y-auto p-6">
          <section>
            <h3 class="text-sm font-semibold text-slate-950">Duyuru mesajı</h3>
            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"><p class="whitespace-pre-wrap text-sm leading-6 text-slate-800">{{ viewingCampaign.body }}</p></div>
          </section>

          <section class="mt-6 border-t border-slate-200 pt-5">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-sm font-semibold text-slate-950">Yayın ayarları</h3><p class="mt-1 text-xs text-slate-600">Hedefleme, kanal ve zamanlama bilgileri.</p></div><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusClasses[viewingCampaign.status]]">{{ statuses[viewingCampaign.status] }}</span></div>
            <dl class="mt-4 grid gap-x-8 sm:grid-cols-2">
              <div class="border-b border-slate-100 py-3"><dt class="text-xs font-medium text-slate-600">Hedef kitle</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ audienceLabel(viewingCampaign) }}</dd></div>
              <div class="border-b border-slate-100 py-3"><dt class="text-xs font-medium text-slate-600">Bildirim kanalı</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ viewingCampaign.push_enabled ? 'Uygulama içi ve push' : 'Yalnızca uygulama içi' }}</dd></div>
              <div class="border-b border-slate-100 py-3"><dt class="text-xs font-medium text-slate-600">Tekrar düzeni</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ recurrences[viewingCampaign.recurrence] }}</dd></div>
              <div class="border-b border-slate-100 py-3"><dt class="text-xs font-medium text-slate-600">Tekrar bitişi</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ turkeyDateLabel(viewingCampaign.ends_at) }}</dd></div>
              <div class="border-b border-slate-100 py-3"><dt class="text-xs font-medium text-slate-600">Planlanan gönderim</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $adminDate(viewingCampaign.scheduled_at, 'Planlanmadı') }}</dd></div>
              <div class="border-b border-slate-100 py-3"><dt class="text-xs font-medium text-slate-600">Sonraki gönderim</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $adminDate(viewingCampaign.next_send_at, 'Planlanmadı') }}</dd></div>
            </dl>
          </section>

          <section class="mt-6 border-t border-slate-200 pt-5">
            <div><h3 class="text-sm font-semibold text-slate-950">Gönderim özeti</h3><p class="mt-1 text-xs text-slate-600">Tüm gönderimlerin toplam sonucu. Son gönderim: {{ $adminDate(viewingCampaign.last_sent_at, 'Henüz gönderilmedi') }}</p></div>
            <dl class="mt-4 grid grid-cols-3 overflow-hidden rounded-xl border border-slate-200 text-center">
              <div class="p-4"><dt class="text-xs font-medium text-slate-600">Gönderim</dt><dd class="mt-2 text-2xl font-semibold text-slate-950">{{ viewingCampaign.runs_count }}</dd></div>
              <div class="border-x border-slate-200 p-4"><dt class="text-xs font-medium text-slate-600">Uygulama içi</dt><dd class="mt-2 text-2xl font-semibold text-slate-950">{{ viewingCampaign.total_in_app_deliveries }}</dd></div>
              <div class="p-4"><dt class="text-xs font-medium text-slate-600">Push kuyruğu</dt><dd class="mt-2 text-2xl font-semibold text-slate-950">{{ viewingCampaign.total_push_eligible }}</dd></div>
            </dl>
          </section>

          <details v-if="viewingCampaign.dispatches?.length" class="group mt-6 rounded-xl border border-slate-200">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3.5"><div><p class="text-sm font-semibold text-slate-950">Gönderim geçmişi</p><p class="mt-0.5 text-xs text-slate-600">Son {{ viewingCampaign.dispatches.length }} gönderimin teknik sonucu</p></div><svg viewBox="0 0 24 24" class="size-4 text-slate-500 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6"/></svg></summary>
            <div class="overflow-x-auto border-t border-slate-200"><table class="w-full min-w-[560px] text-left text-sm"><thead class="bg-slate-50 text-xs font-semibold text-slate-700"><tr><th class="px-4 py-3">Zaman</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3">Uygulama içi</th><th class="px-4 py-3">Push</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="dispatch in viewingCampaign.dispatches" :key="dispatch.id"><td class="px-4 py-3 text-slate-700">{{ $adminDate(dispatch.completed_at || dispatch.scheduled_for) }}</td><td class="px-4 py-3 font-medium text-slate-900">{{ dispatchStatuses[dispatch.status] || dispatch.status }}</td><td class="px-4 py-3 text-slate-700">{{ dispatch.recipients_count }}</td><td class="px-4 py-3 text-slate-700">{{ dispatch.push_eligible_count }}</td></tr></tbody></table></div>
          </details>
        </div>
      </section>
    </div>
    <div v-if="sendCandidate" class="fixed inset-0 z-[60] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="closeSendConfirmation" @keydown.esc.window="closeSendConfirmation">
      <section role="alertdialog" aria-modal="true" aria-labelledby="send-confirm-title" class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-emerald-700"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 11 18-8-8 18-2.5-7.5L3 11Z"/><path d="m10.5 13.5 4-4"/></svg></span><div><h2 id="send-confirm-title" class="text-xl font-semibold text-slate-950">Duyuru şimdi gönderilsin mi?</h2><p class="mt-2 text-sm leading-6 text-slate-600"><strong class="font-semibold text-slate-900">{{ sendCandidate.title }}</strong> başlıklı duyuru {{ audienceLabel(sendCandidate).toLocaleLowerCase('tr-TR') }} için gönderim kuyruğuna alınacak.</p></div></div>
        <div class="mt-6 flex justify-end gap-2"><button type="button" :disabled="actionPending" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 disabled:opacity-50" @click="closeSendConfirmation">Vazgeç</button><button type="button" :disabled="actionPending" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white disabled:opacity-50" @click="confirmSendNow">{{ actionPending ? 'Gönderiliyor…' : 'Duyuruyu gönder' }}</button></div>
      </section>
    </div>

    <div v-if="deleteCandidate" class="fixed inset-0 z-[60] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="closeDeleteConfirmation" @keydown.esc.window="closeDeleteConfirmation">
      <section role="alertdialog" aria-modal="true" aria-labelledby="delete-announcement-title" class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-700"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5"/></svg></span><div><h2 id="delete-announcement-title" class="text-xl font-semibold text-slate-950">Duyuru silinsin mi?</h2><p class="mt-2 text-sm leading-6 text-slate-600"><strong class="font-semibold text-slate-900">#{{ campaignNumber(deleteCandidate) }} · {{ deleteCandidate.title }}</strong> yönetim listesinden kaldırılacak.</p></div></div>
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">Kullanıcılara daha önce teslim edilen uygulama içi ve push bildirimleri silinmez. Kampanya ve gönderim kayıtları denetim amacıyla sunucuda korunur.</div>
        <div class="mt-6 flex justify-end gap-2"><button type="button" :disabled="deleteForm.processing" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 disabled:opacity-50" @click="closeDeleteConfirmation">Vazgeç</button><button type="button" :disabled="deleteForm.processing" class="h-11 rounded-xl bg-red-700 px-5 text-sm font-semibold text-white disabled:opacity-50" @click="confirmDelete">{{ deleteForm.processing ? 'Siliniyor…' : 'Duyuruyu sil' }}</button></div>
      </section>
    </div>

    <div v-if="composeOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="closeComposer" @keydown.esc.window="closeComposer">
      <section role="dialog" aria-modal="true" aria-labelledby="announcement-modal-title" class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                  <div><h2 id="announcement-modal-title" class="text-lg font-semibold text-slate-950">{{ editingCampaign ? 'Duyuruyu düzenle' : 'Yeni duyuru oluştur' }}</h2><p class="mt-1 text-sm text-slate-600">{{ editingCampaign ? 'Henüz gönderilmemiş duyurunun içeriğini ve zamanlamasını güncelle.' : 'Mesajı taslak olarak kaydet, ileri bir zamana planla veya hemen gönder.' }}</p></div>
                  <button type="button" :disabled="form.processing" title="Kapat" aria-label="Yeni duyuru penceresini kapat" class="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-300 text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 disabled:opacity-50" @click="closeComposer"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </div>
        
                <div class="overflow-y-auto p-5">
                  <div class="grid gap-4 lg:grid-cols-2">
                    <label class="text-xs font-semibold text-slate-700">
                      <span>Duyuru türü <span class="text-red-600">*</span></span>
                      <select v-model="form.type" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                        <option value="marketing">Duyuru / kampanya</option>
                        <option value="system">Sistem duyurusu</option>
                      </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-700">
                      <span>Hedef kitle <span class="text-red-600">*</span></span>
                      <select v-model="form.audience" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                        <option value="all_active">Tüm aktif kullanıcılar ({{ audience.activeUsers }})</option>
                        <option value="selected">Belirli kullanıcı numaraları</option>
                      </select>
                    </label>
        
                    <label class="text-xs font-semibold text-slate-700 lg:col-span-2">
                      <span>Başlık <span class="text-red-600">*</span></span>
                      <input v-model="form.title" maxlength="100" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" placeholder="Duyuru başlığını yaz" />
                    </label>
                    <label class="text-xs font-semibold text-slate-700 lg:col-span-2">
                      <span>Mesaj <span class="text-red-600">*</span></span>
                      <textarea v-model="form.body" maxlength="500" rows="4" class="mt-1.5 w-full rounded-xl border border-slate-300 p-3 text-sm leading-6 text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" placeholder="Kullanıcının göreceği açıklamayı yaz" />
                      <span class="mt-1 block text-right text-xs font-medium text-slate-500">{{ form.body.length }} / 500</span>
                    </label>
        
                    <label v-if="form.audience === 'selected'" class="text-xs font-semibold text-slate-700 lg:col-span-2">
                      <span>Kullanıcı numaraları <span class="text-red-600">*</span></span>
                      <textarea v-model="form.targetUserIdsText" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 p-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" placeholder="Örnek: 12, 38, 94" />
                    </label>
        
                    <label class="text-xs font-semibold text-slate-700">
                      <span>Tekrar düzeni <span class="text-red-600">*</span></span>
                      <select v-model="form.recurrence" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                        <option value="none">Tek gönderim</option>
                        <option value="daily">Her gün (en fazla {{ limits.dailyMaximumDays }} gün)</option>
                        <option value="weekly">Her hafta</option>
                      </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-700">
                      Planlanan tarih ve saat <span class="font-medium text-slate-500">(planlama için zorunlu)</span>
                      <input v-model="form.scheduledAt" type="datetime-local" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                    </label>
                    <label v-if="form.recurrence !== 'none'" class="text-xs font-semibold text-slate-700">
                      <span>Tekrar bitiş tarihi <span class="text-red-600">*</span></span>
                      <input v-model="form.endsAt" type="date" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                    </label>
                    <label class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-800">
                      <input v-model="form.pushEnabled" type="checkbox" class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" />
                      Telefon push bildirimi de gönder
                    </label>
                  </div>
        
                  <div v-if="form.type === 'marketing'" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                    Pazarlama push bildirimi yalnızca izin veren kullanıcılara gönderilir. Yönetici gönderimleri günlük adet veya 24 saat bekleme sınırına tabi değildir.
                  </div>
                  <div v-if="Object.keys(form.errors).length" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                    <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                  </div>
        
                  <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-5">
                    <button v-if="editingCampaign" type="button" :disabled="form.processing" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white transition hover:bg-forest-800 disabled:opacity-50" @click="submit('edit')">{{ form.processing ? 'Kaydediliyor…' : 'Değişiklikleri kaydet' }}</button>
                    <template v-else>
                      <button type="button" :disabled="form.processing" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50" @click="submit('draft')">Taslak kaydet</button>
                      <button type="button" :disabled="form.processing" class="h-11 rounded-xl border border-sky-300 bg-sky-50 px-5 text-sm font-semibold text-sky-800 transition hover:bg-sky-100 disabled:opacity-50" @click="submit('schedule')">Planla</button>
                      <button type="button" :disabled="form.processing" class="h-11 rounded-xl bg-forest-700 px-5 text-sm font-semibold text-white transition hover:bg-forest-800 disabled:opacity-50" @click="submit('send_now')">Şimdi gönder</button>
                    </template>
                  </div>
                </div>
      </section>
    </div>
  </AdminLayout>
</template>