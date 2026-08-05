<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: true });
const submit = () => form.post('/destekci/giris');
</script>

<template>
  <Head title="Destekçi girişi" />
  <main class="flex min-h-screen items-center justify-center bg-[#f5f7f2] px-5 py-10 text-slate-900">
    <section class="w-full max-w-md overflow-hidden rounded-3xl border border-emerald-900/10 bg-white shadow-xl shadow-emerald-950/5">
      <div class="bg-[#0f5b3b] px-7 py-8 text-white">
        <p class="text-2xl font-semibold tracking-[-.04em]">döngü<span class="text-lime-300">.</span></p>
        <p class="mt-8 text-[11px] font-semibold uppercase tracking-[.18em] text-emerald-200">Döngü Destekçisi</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-[-.03em]">İstatistik paneli</h1>
        <p class="mt-3 text-sm leading-6 text-emerald-100">İşletmenizin bölgenizde kaç kişiye ulaştığını güvenli ve anlaşılır şekilde takip edin.</p>
      </div>
      <form class="space-y-4 p-7" @submit.prevent="submit">
        <label class="block text-sm font-semibold">E-posta adresi
          <input v-model="form.email" type="email" autocomplete="email" required class="mt-2 min-h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-50" />
          <span v-if="form.errors.email" class="mt-1.5 block text-xs font-semibold text-red-600">{{ form.errors.email }}</span>
        </label>
        <label class="block text-sm font-semibold">Şifre
          <input v-model="form.password" type="password" autocomplete="current-password" required class="mt-2 min-h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-50" />
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-600"><input v-model="form.remember" type="checkbox" /> Oturumu açık tut</label>
        <button :disabled="form.processing" class="min-h-12 w-full rounded-xl bg-[#0f5b3b] px-5 text-sm font-semibold text-white transition hover:bg-[#0b4b31] disabled:opacity-50">Panele giriş yap</button>
        <p class="text-center text-xs leading-5 text-slate-500">Hesap bilgileriniz Döngü yöneticisi tarafından oluşturulur.</p>
      </form>
    </section>
  </main>
</template>
