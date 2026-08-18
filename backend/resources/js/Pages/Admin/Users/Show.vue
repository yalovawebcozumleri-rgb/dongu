<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ profile: Object, actions: Array });
const actionOpen = ref(false);
const actionForm = useForm({ action: 'account_suspension_24h', reason: '' });
const listingStatuses = { active: 'Aktif', reserved: 'Rezerve', completed: 'Tamamlandı', cancelled: 'İptal' };
const stateClasses = { active: 'bg-emerald-50 text-emerald-800', suspended: 'bg-amber-50 text-amber-900', closed: 'bg-red-50 text-red-800', inactive: 'bg-slate-100 text-slate-700' };
const defaultReasons = {
  record_only: 'Kullanıcının hesap hareketleri yönetim incelemesi kapsamında kayıt altına alındı. Şu aşamada kullanıcıya herhangi bir kısıtlama uygulanmadı.',
  warning: 'Topluluk kurallarına aykırı olduğu değerlendirilen davranış nedeniyle kullanıcı resmi olarak uyarıldı. Benzer davranışların tekrarlanması hâlinde hesaba geçici veya kalıcı kısıtlama uygulanabilir.',
  message_restriction_24h: 'Topluluk kurallarına aykırı mesajlaşma davranışı nedeniyle kullanıcının mesaj gönderme yetkisi 24 saat süreyle kısıtlandı.',
  message_restriction_7d: 'Tekrarlanan veya ciddi topluluk kuralı ihlalleri nedeniyle kullanıcının mesaj gönderme yetkisi 7 gün süreyle kısıtlandı.',
  message_restriction_30d: 'Süregelen ve ciddi mesajlaşma ihlalleri nedeniyle kullanıcının mesaj gönderme yetkisi 30 gün süreyle kısıtlandı.',
  account_suspension_24h: 'Şüpheli hesap hareketlerinin incelenmesi ve platform güvenliğinin korunması amacıyla kullanıcı hesabı 24 saat süreyle askıya alındı.',
  account_suspension_7d: 'Tekrarlanan kural ihlalleri nedeniyle kullanıcı hesabı 7 gün süreyle askıya alındı. Bu süre boyunca hesaba giriş ve platform işlemleri engellendi.',
  account_suspension_30d: 'Ciddi veya tekrarlanan platform ihlalleri nedeniyle kullanıcı hesabı 30 gün süreyle askıya alındı.',
  account_suspension_indefinite: 'Hesap güvenliği ve topluluk kurallarına ilişkin ciddi ihlaller nedeniyle kullanıcı hesabı süresiz olarak askıya alındı. Hesap yalnızca yönetici incelemesi sonucunda yeniden açılabilir.',
  account_closed: 'Platform güvenliğini veya kullanıcı topluluğunu ciddi biçimde etkileyen ihlaller nedeniyle hesap kullanıma kapatıldı. Geçmiş işlem ve denetim kayıtları korunacaktır.',
  restore: 'Yönetim incelemesi tamamlandı. Hesabın yeniden kullanıma açılmasına engel bir durum bulunmadığından aktif kısıtlamalar kaldırıldı.',
};
const selectedAction = computed(() => actionForm.action === 'restore' ? 'Hesabı yeniden aç' : (props.actions.find(item => item.value === actionForm.action)?.label || 'Hesap işlemi'));
const isDangerous = computed(() => ['account_suspension_indefinite', 'account_closed'].includes(actionForm.action));
const openAction = (action = 'account_suspension_24h') => { actionForm.reset(); actionForm.clearErrors(); actionForm.action = action; actionForm.reason = defaultReasons[action] || ''; actionOpen.value = true; };
watch(() => actionForm.action, (action, previousAction) => {
  if (!actionOpen.value || action === previousAction) return;
  actionForm.reason = defaultReasons[action] || '';
});
const closeAction = () => { if (!actionForm.processing) { actionOpen.value = false; actionForm.reset(); } };
const submitAction = () => actionForm.patch(`/admin/users/${props.profile.id}/account`, { preserveScroll: true, onSuccess: closeAction });
</script>

<template>
  <Head :title="profile.name" />
  <AdminLayout eyebrow="Hesaplar" :title="profile.name" description="Kullanıcının hesap durumunu, pazaryeri hareketlerini ve denetlenebilir yaptırım geçmişini yönet.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <div class="flex flex-wrap items-center justify-between gap-3"><Link href="/admin/users" class="text-sm font-semibold text-emerald-700">← Kullanıcı yönetimine dön</Link><div class="flex flex-wrap gap-2"><button v-if="profile.has_active_restriction || profile.account_state !== 'active'" type="button" class="h-11 rounded-xl border border-emerald-300 bg-emerald-50 px-5 text-sm font-semibold text-emerald-900" @click="openAction('restore')">Hesabı yeniden aç</button><button type="button" class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white" @click="openAction()">Hesap işlemi</button></div></div>

      <section v-if="profile.account_state !== 'active'" class="mt-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-amber-800">!</span><div><p class="font-semibold text-amber-950">Bu hesabın erişimi sınırlandırılmış</p><p class="mt-1 text-sm leading-6 text-amber-900">Durum: {{ profile.account_state_label }}. Ayrıntı ve uygulayan yönetici yaptırım geçmişinde görülebilir.</p></div></section>

      <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-700">Hesap durumu</p><span :class="['mt-3 inline-flex rounded-full px-3 py-1 text-sm font-semibold', stateClasses[profile.account_state]]">{{ profile.account_state_label }}</span></article>
        <article v-for="item in [['Tamamlanan teslimat', profile.completed_transactions], ['Değerlendirme puanı', profile.rating ?? '—'], ['Değerlendirme', profile.rating_count]]" :key="item[0]" class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-700">{{ item[0] }}</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ item[1] }}</p></article>
      </section>

      <div class="mt-5 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold text-slate-950">Son ilanlar</h2><p class="mt-1 text-sm text-slate-600">Kullanıcının son 10 ilanı</p></div></div><div v-if="profile.listings.length" class="mt-4 divide-y divide-slate-100"><Link v-for="listing in profile.listings" :key="listing.id" :href="`/admin/listings/${listing.id}`" class="flex items-center justify-between gap-4 py-4"><div><p class="font-semibold text-slate-950">İlan #{{ listing.id }}</p><p class="mt-1 text-sm text-slate-600">{{ listing.public_area }} · {{ $adminDate(listing.published_at, 'Taslak') }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800">{{ listingStatuses[listing.status] || listing.status }}</span></Link></div><p v-else class="mt-4 text-sm text-slate-600">İlan bulunmuyor.</p></section>
        <aside class="space-y-5"><section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="text-lg font-semibold text-slate-950">Hesap bilgileri</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-600">E-posta</dt><dd class="mt-1 break-all font-semibold text-slate-950">{{ profile.email }}</dd></div><div><dt class="text-slate-600">Telefon</dt><dd class="mt-1 font-semibold text-slate-950">{{ profile.phone || 'Kayıtlı değil' }}</dd></div><div><dt class="text-slate-600">Kayıt tarihi</dt><dd class="mt-1 text-slate-900">{{ $adminDate(profile.created_at) }}</dd></div></dl></section></aside>
      </div>

      <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5"><div><h2 class="text-lg font-semibold text-slate-950">Yaptırım ve yönetici notu geçmişi</h2><p class="mt-1 text-sm text-slate-600">Kararlar silinmez; uygulayan ve kaldıran yöneticiyle birlikte denetim kaydı olarak korunur.</p></div><div v-if="profile.sanctions.length" class="mt-5 grid gap-3 lg:grid-cols-2"><article v-for="sanction in profile.sanctions" :key="sanction.id" class="rounded-2xl border border-slate-200 p-4"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="font-semibold text-slate-950">{{ sanction.label }}</p><p class="mt-1 text-xs font-medium text-slate-600">{{ sanction.source }}</p></div><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', sanction.active ? 'bg-red-50 text-red-800' : sanction.revoked_at ? 'bg-slate-100 text-slate-700' : 'bg-sky-50 text-sky-800']">{{ sanction.active ? 'Aktif' : sanction.revoked_at ? 'Kaldırıldı' : 'Kayıt' }}</span></div><p class="mt-3 text-sm leading-6 text-slate-800">{{ sanction.reason }}</p><dl class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2"><div><dt>Uygulayan</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ sanction.applied_by }}</dd></div><div><dt>Süre</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ $adminDate(sanction.starts_at) }} – {{ $adminDate(sanction.ends_at, 'Süresiz') }}</dd></div><div v-if="sanction.revoked_at"><dt>Kaldıran</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ sanction.revoked_by }} · {{ $adminDate(sanction.revoked_at) }}</dd></div><div v-if="sanction.revoke_reason"><dt>Kaldırma gerekçesi</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ sanction.revoke_reason }}</dd></div></dl></article></div><p v-else class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Bu kullanıcı için yaptırım veya yönetici notu bulunmuyor.</p></section>
    </main>

    <div v-if="actionOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/45 p-5 backdrop-blur-sm" @click.self="closeAction">
      <section role="dialog" aria-modal="true" aria-labelledby="account-action-title" class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4"><span :class="['grid size-11 shrink-0 place-items-center rounded-2xl', isDangerous ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5m0 3h.01M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span><div><h2 id="account-action-title" class="text-xl font-semibold text-slate-950">{{ selectedAction }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">İşlem yönetici hesabın, tarih ve gerekçeyle birlikte kalıcı denetim kaydına yazılır.</p></div></div>
        <label v-if="actionForm.action !== 'restore'" class="mt-5 block text-sm font-semibold text-slate-800">Uygulanacak işlem<select v-model="actionForm.action" class="mt-2 h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950"><option v-for="action in actions" :key="action.value" :value="action.value">{{ action.label }}</option></select></label>
        <div v-if="actionForm.action === 'account_closed'" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-900">Hesap kapatıldığında giriş engellenir ve mevcut mobil oturumlar sonlandırılır. Geçmiş ilan, mesaj ve teslimat kayıtları silinmez.</div>
        <label class="mt-5 block text-sm font-semibold text-slate-800">Yönetici gerekçesi<textarea v-model="actionForm.reason" rows="4" maxlength="1000" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm text-slate-950 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" placeholder="İşleme uygun gerekçe otomatik oluşturulur; istersen düzenleyebilirsin." /></label><p v-if="actionForm.errors.reason" class="mt-2 text-sm font-semibold text-red-700">{{ actionForm.errors.reason }}</p><p v-if="actionForm.errors.action" class="mt-2 text-sm font-semibold text-red-700">{{ actionForm.errors.action }}</p>
        <div class="mt-6 flex justify-end gap-2"><button type="button" class="h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700" @click="closeAction">Vazgeç</button><button type="button" :disabled="actionForm.processing" :class="['h-11 rounded-xl px-5 text-sm font-semibold text-white disabled:opacity-50', isDangerous ? 'bg-red-700' : 'bg-slate-950']" @click="submitAction">İşlemi onayla</button></div>
      </section>
    </div>
  </AdminLayout>
</template>
