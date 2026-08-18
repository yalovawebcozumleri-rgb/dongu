<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({ report: { type: Object, required: true } });
const user = computed(() => usePage().props.auth.user);
const form = useForm({ resolution: props.report.status, note: props.report.resolution_note || '', enforcement_action: props.report.enforcement_action || 'warning', remove_message: !!props.report.remove_message });
const reasons = { spam: 'Spam veya reklam', harassment: 'Taciz veya hakaret', fraud: 'Dolandırıcılık şüphesi', personal_data: 'Kişisel bilgi paylaşımı', other: 'Diğer' };
const statuses = { pending: 'İncelenecek', confirmed: 'İhlal doğrulandı', dismissed: 'Bildirim reddedildi' };
const actions = [
  ['warning', 'Yalnızca uyarı ver'],
  ['message_restriction_24h', 'Mesajlaşmayı 24 saat kısıtla'],
  ['message_restriction_7d', 'Mesajlaşmayı 7 gün kısıtla'],
  ['message_restriction_30d', 'Mesajlaşmayı 30 gün kısıtla'],
  ['account_suspension_24h', 'Hesabı 24 saat askıya al'],
  ['account_suspension_7d', 'Hesabı 7 gün askıya al'],
  ['account_suspension_30d', 'Hesabı 30 gün askıya al'],
  ['account_suspension_indefinite', 'Hesabı süresiz askıya al'],
  ['record_only', 'Yaptırım uygulamadan kayıt oluştur'],
];
const actionLabel = value => actions.find(item => item[0] === value)?.[1] || value;
const actionNotes = {
  warning: 'Bildirilen mesaj ve konuşma bağlamı incelendi. İçeriğin topluluk kurallarını ihlal ettiği doğrulandığından kullanıcı resmi olarak uyarıldı. Benzer davranışların tekrarlanması hâlinde daha ağır yaptırım uygulanabilir.',
  message_restriction_24h: 'Bildirilen mesaj ve konuşma bağlamında topluluk kurallarına aykırı davranış doğrulandı. Kullanıcının mesaj gönderme yetkisi 24 saat süreyle kısıtlandı.',
  message_restriction_7d: 'Tekrarlanan veya ciddi mesajlaşma ihlali doğrulandığından kullanıcının mesaj gönderme yetkisi 7 gün süreyle kısıtlandı.',
  message_restriction_30d: 'Süregelen ve ciddi mesajlaşma ihlalleri doğrulandığından kullanıcının mesaj gönderme yetkisi 30 gün süreyle kısıtlandı.',
  account_suspension_24h: 'Bildirilen davranışın platform güvenliğini etkilediği doğrulandığından kullanıcı hesabı 24 saat süreyle askıya alındı.',
  account_suspension_7d: 'Ciddi veya tekrarlanan topluluk kuralı ihlalleri doğrulandığından kullanıcı hesabı 7 gün süreyle askıya alındı.',
  account_suspension_30d: 'Süregelen ve ciddi platform ihlalleri doğrulandığından kullanıcı hesabı 30 gün süreyle askıya alındı.',
  account_suspension_indefinite: 'Platform güvenliğini ve kullanıcı topluluğunu ciddi biçimde etkileyen ihlaller doğrulandığından hesap süresiz olarak askıya alındı.',
  record_only: 'Bildirim ve konuşma bağlamı yönetim incelemesinden geçirildi. Karar denetim amacıyla kayıt altına alındı; kullanıcıya ek bir yaptırım uygulanmadı.',
};
const resolutionNotes = {
  dismissed: 'Bildirilen mesaj ve konuşma bağlamı incelendi. Mevcut kanıtlar topluluk kuralı ihlalini doğrulamak için yeterli bulunmadığından bildirim reddedildi.',
  pending: 'Yeni bilgi veya yeniden değerlendirme gereksinimi nedeniyle bildirim tekrar inceleme sırasına alındı. Önceki karar ve aktif yaptırım geri alındı.',
};
let lastAutoNote = '';
const applyAutoNote = (note, force = false) => {
  if (force || !form.note.trim() || form.note === lastAutoNote) {
    form.note = note;
    lastAutoNote = note;
  }
};
if (!form.note) applyAutoNote(actionNotes[form.enforcement_action]);
watch(() => form.enforcement_action, action => applyAutoNote(actionNotes[action], true));
const submit = resolution => {
  form.resolution = resolution;
  if (resolution === 'confirmed') applyAutoNote(actionNotes[form.enforcement_action]);
  else applyAutoNote(resolutionNotes[resolution], true);
  form.patch(`/admin/message-reports/${props.report.id}`, { preserveScroll: true });
};
const logout = () => router.post('/admin/logout');
</script>

<template>
  <Head :title="`Mesaj Bildirimi #${report.id}`" />
  <AdminLayout eyebrow="Güvenlik" :title="`Mesaj bildirimi #${report.id}`" description="Bildirilen mesajı bağlamıyla değerlendir ve uygun yaptırımı belirle.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <div class="grid gap-6 lg:grid-cols-[1.35fr_.65fr]">
        <section>
          <div class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-extrabold uppercase tracking-wider text-forest-700">Bildirim #{{ report.id }}</p><h2 class="mt-1 text-2xl font-black text-forest-950">{{ reasons[report.reason] }}</h2><p class="mt-2 text-sm text-slate-500">{{ $adminDate(report.created_at) }} · Görüşme #{{ report.conversation_id }}</p></div><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-extrabold text-amber-800">{{ statuses[report.status] }}</span></div><p v-if="report.details" class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">{{ report.details }}</p></div>

          <div class="mt-5 rounded-2xl border border-black/5 bg-white p-5 shadow-sm"><h3 class="text-lg font-black text-forest-950">Konuşma bağlamı</h3><p class="mt-1 text-sm text-slate-500">Bildirilen mesajın öncesi ve sonrası; yalnızca karar için gereken sınırlı bağlam.</p><div class="mt-5 space-y-3"><article v-for="message in report.context" :key="message.id" :class="['rounded-2xl border p-4', message.is_reported ? 'border-red-300 bg-red-50' : 'border-slate-100 bg-slate-50']"><div class="flex justify-between gap-3"><p class="text-xs font-extrabold text-slate-500">{{ message.type === 'system' ? 'Sistem' : message.sender_id === report.conversation.buyer.id ? report.conversation.buyer.name : report.conversation.seller.name }}</p><p class="text-xs text-slate-400">{{ $adminDate(message.created_at) }}</p></div><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-800">{{ message.body }}</p><p v-if="message.is_reported" class="mt-2 text-xs font-black text-red-700">BİLDİRİLEN MESAJ</p></article></div></div>
        </section>

        <aside class="space-y-5">
          <section class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm"><h3 class="font-black text-forest-950">Kullanıcılar</h3><div class="mt-4 border-b border-slate-100 pb-4"><p class="text-xs font-bold text-red-700">BİLDİRİLEN</p><p class="mt-1 font-black">{{ report.reported_user?.name }}</p><p class="text-xs text-slate-500">{{ report.reported_user?.email }}</p></div><div class="pt-4"><p class="text-xs font-bold text-forest-700">BİLDİREN</p><p class="mt-1 font-black">{{ report.reporter.name }}</p><p class="text-xs text-slate-500">{{ report.reporter.email }}</p></div></section>
          <section class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm"><h3 class="font-black text-forest-950">İlan ve işlem</h3><p class="mt-3 text-sm font-bold">{{ report.conversation.listing?.public_area }}</p><p class="mt-1 text-sm text-slate-500">{{ report.conversation.listing?.description }}</p><p class="mt-3 text-xs font-bold uppercase text-slate-400">İşlem durumu: {{ report.conversation.status }}</p></section>
          <section class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm">
            <h3 class="font-black text-forest-950">Moderasyon kararı</h3>
            <label class="mt-4 block text-xs font-black text-slate-700">Uygulanacak işlem</label>
            <select v-model="form.enforcement_action" class="mt-2 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm font-bold">
              <option v-for="action in actions" :key="action[0]" :value="action[0]">{{ action[1] }}</option>
            </select>
            <p v-if="form.errors.enforcement_action" class="mt-2 text-xs font-bold text-red-600">{{ form.errors.enforcement_action }}</p>
            <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-red-100 bg-red-50 p-3">
              <input v-model="form.remove_message" type="checkbox" class="mt-1"/>
              <span><span class="block text-sm font-black text-red-800">İhlalli mesajı kaldır</span><span class="mt-1 block text-xs leading-5 text-red-700">Kullanıcıların sohbetinde mesaj içeriği yerine güvenli bir kaldırıldı bilgisi gösterilir.</span></span>
            </label>
            <textarea v-model="form.note" rows="5" maxlength="1000" placeholder="Seçilen işleme uygun yönetici notu otomatik oluşturulur; istersen düzenleyebilirsin." class="mt-4 w-full rounded-xl border border-slate-200 p-3 text-sm"/>
            <p v-if="form.errors.note" class="mt-2 text-xs font-bold text-red-600">{{ form.errors.note }}</p>
            <div class="mt-4 grid gap-2"><button :disabled="form.processing" class="rounded-xl bg-red-700 px-4 py-3 text-sm font-black text-white disabled:opacity-50" @click="submit('confirmed')">İhlali doğrula</button><button :disabled="form.processing" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-black text-slate-700 disabled:opacity-50" @click="submit('dismissed')">Bildirimi reddet</button><button v-if="report.status !== 'pending'" :disabled="form.processing" class="rounded-xl px-4 py-2 text-xs font-black text-forest-700" @click="submit('pending')">Yeniden incelemeye al</button></div>
            <div v-if="report.resolved_by" class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500"><p>{{ report.resolved_by.name }} · {{ $adminDate(report.resolved_at) }}</p></div>
          </section>
          <section v-if="report.sanctions?.length" class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm">
            <h3 class="font-black text-forest-950">Yaptırım geçmişi</h3>
            <article v-for="sanction in report.sanctions" :key="sanction.id" class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-600">
              <p class="font-black text-slate-900">{{ actionLabel(sanction.action) }}</p>
              <p class="mt-1">{{ $adminDate(sanction.starts_at) }}<span v-if="sanction.ends_at"> – {{ $adminDate(sanction.ends_at) }}</span><span v-else-if="sanction.action === 'account_suspension_indefinite'"> – Süresiz</span><span v-else> – Tek seferlik karar</span></p>
              <p class="mt-1">{{ sanction.applied_by }} · {{ sanction.reason }}</p>
              <p v-if="sanction.revoked_at" class="mt-2 font-bold text-amber-700">Geri alındı: {{ $adminDate(sanction.revoked_at) }} · {{ sanction.revoked_by }}</p>
            </article>
          </section>
        </aside>
      </div>
    </main>
  </AdminLayout>
</template>
