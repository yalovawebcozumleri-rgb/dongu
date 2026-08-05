<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/admin/login');
</script>

<template>
    <Head title="Yönetici girişi" />
    <main class="min-h-screen bg-cream-50 px-5 py-10 grid place-items-center">
        <section class="w-full max-w-md overflow-hidden rounded-[28px] bg-white shadow-[0_24px_80px_rgba(16,37,26,.12)]">
            <div class="bg-forest-950 px-8 py-9 text-white">
                <p class="text-xs font-bold tracking-[.22em] text-emerald-300">DÖNGÜ YÖNETİM</p>
                <h1 class="mt-3 text-3xl font-black">Kontrol paneli</h1>
                <p class="mt-2 text-sm leading-6 text-emerald-100/75">Kullanıcıları ve ilanları tek Laravel panelinden yönetin.</p>
            </div>
            <form class="space-y-5 p-8" @submit.prevent="submit">
                <label class="block">
                    <span class="text-sm font-bold text-slate-700">E-posta</span>
                    <input v-model="form.email" type="email" autocomplete="email" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 outline-none transition focus:border-forest-600 focus:ring-4 focus:ring-emerald-100" />
                    <span v-if="form.errors.email" class="mt-2 block text-sm text-red-600">{{ form.errors.email }}</span>
                </label>
                <label class="block">
                    <span class="text-sm font-bold text-slate-700">Şifre</span>
                    <input v-model="form.password" type="password" autocomplete="current-password" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 outline-none transition focus:border-forest-600 focus:ring-4 focus:ring-emerald-100" />
                    <span v-if="form.errors.password" class="mt-2 block text-sm text-red-600">{{ form.errors.password }}</span>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="size-4 accent-forest-700" /> Oturumu açık tut
                </label>
                <button :disabled="form.processing" class="h-12 w-full rounded-xl bg-forest-700 font-extrabold text-white transition hover:bg-forest-900 disabled:opacity-50">
                    {{ form.processing ? 'Giriş yapılıyor…' : 'Yönetim paneline gir' }}
                </button>
            </form>
        </section>
    </main>
</template>
