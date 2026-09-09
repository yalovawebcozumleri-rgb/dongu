<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const props = defineProps({ phone: { type: String, required: true } });
const form = useForm({ phone: props.phone });
const save = () => form.patch('/admin/support-settings', { preserveScroll: true });
</script>

<template>
    <Head title="Destek ayarları" />
    <AdminLayout eyebrow="Sistem" title="Destek ayarları" description="Uygulama, giriş e-postaları ve web sitesindeki WhatsApp bağlantılarında kullanılacak numarayı yönet.">
        <form @submit.prevent="save" class="max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <label for="support-phone" class="block text-sm text-slate-700">WhatsApp destek numarası</label>
            <input id="support-phone" v-model="form.phone" type="tel" autocomplete="tel" maxlength="30" required placeholder="+90 541 334 22 19" :aria-invalid="!!form.errors.phone" aria-describedby="phone-help phone-error" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" />
            <p id="phone-help" class="mt-3 text-sm leading-6 text-slate-500">Ülke koduyla girin. Bu numara kullanıcılara açık olacaktır. Uygulama, giriş e-postaları ve web sitesindeki WhatsApp bağlantıları güncel numaraya yönlendirilir.</p>
            <p id="phone-error" v-if="form.errors.phone" role="alert" class="mt-3 text-sm text-red-600">{{ form.errors.phone }}</p>
            <button type="submit" :disabled="form.processing" class="mt-6 rounded-xl bg-emerald-800 px-5 py-3 text-white disabled:opacity-50">{{ form.processing ? 'Kaydediliyor…' : 'Değişiklikleri kaydet' }}</button>
        </form>
    </AdminLayout>
</template>
