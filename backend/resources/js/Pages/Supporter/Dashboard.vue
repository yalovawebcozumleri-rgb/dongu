<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ business: Object, today: Object, totals: Object, daily: Array });
const maxImpressions = computed(() => Math.max(1, ...props.daily.map(row => Number(row.impressions || 0))));
const logout = () => router.post('/destekci/cikis');
const number = value => Number(value || 0).toLocaleString('tr-TR');
</script>

<template>
  <Head :title="`${business.name} · Destekçi Paneli`" />
  <div class="min-h-screen bg-[#f5f7f2] text-slate-950">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex min-h-16 max-w-[1280px] items-center justify-between px-5 lg:px-8">
        <p class="text-[22px] font-semibold tracking-[-.04em]">döngü<span class="text-emerald-600">.</span></p>
        <button class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="logout">Çıkış yap</button>
      </div>
    </header>
    <section class="border-b border-emerald-100 bg-gradient-to-r from-white via-emerald-50/60 to-[#f5f7f2]">
      <div class="mx-auto max-w-[1280px] px-5 py-8 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div><p class="text-[11px] font-semibold uppercase tracking-[.16em] text-emerald-700">Destekçi paneli</p><h1 class="mt-1.5 text-3xl font-semibold tracking-[-.03em]">{{ business.name }}</h1><p class="mt-2 text-sm text-slate-600">{{ business.area }} · {{ business.startsAt || 'Hemen' }} — {{ business.endsAt || 'Süresiz' }}</p></div>
          <span :class="['rounded-full px-3 py-1.5 text-xs font-semibold', business.isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700']">{{ business.isActive ? 'Yayında' : 'Yayında değil' }}</span>
        </div>
      </div>
    </section>
    <main class="mx-auto max-w-[1280px] space-y-6 px-5 py-8 lg:px-8">
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="item in [
          ['Bugünkü gösterim', today.impressions], ['Bugünkü tekil erişim', today.uniqueReach],
          ['Toplam gösterim', totals.impressions], ['Yönlendirme tıklaması', totals.ctaClicks]
        ]" :key="item[0]" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-[.08em] text-slate-600">{{ item[0] }}</p><p class="mt-3 text-3xl font-semibold tracking-[-.04em]">{{ number(item[1]) }}</p>
        </article>
      </section>
      <section class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div><p class="text-xs font-semibold uppercase tracking-[.1em] text-emerald-700">Son 30 gün</p><h2 class="mt-1 text-xl font-semibold">Günlük görünürlük</h2></div>
          <div v-if="daily.length" class="mt-7 flex h-64 items-end gap-1.5 overflow-x-auto border-b border-slate-200 pb-7">
            <div v-for="row in daily" :key="row.date" class="group flex min-w-5 flex-1 flex-col items-center justify-end self-stretch" :title="`${row.date}: ${number(row.impressions)} gösterim`">
              <div class="mt-auto w-full max-w-8 rounded-t-md bg-emerald-600 transition group-hover:bg-emerald-500" :style="{ height: `${Math.max(3, row.impressions / maxImpressions * 100)}%` }" />
              <span class="mt-2 -rotate-45 text-[9px] text-slate-500">{{ row.date }}</span>
            </div>
          </div>
          <p v-else class="mt-8 rounded-xl bg-slate-50 px-5 py-12 text-center text-sm text-slate-600">Henüz ölçülmüş bir gösterim bulunmuyor.</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-[.1em] text-emerald-700">Toplam performans</p>
          <dl class="mt-5 divide-y divide-slate-100">
            <div v-for="item in [['Tekil erişim', totals.uniqueReach], ['Profil inceleme', totals.detailViews], ['CTA tıklaması', totals.ctaClicks], ['Tıklanma oranı', `%${String(totals.ctr).replace('.', ',')}`]]" :key="item[0]" class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-600">{{ item[0] }}</dt><dd class="text-lg font-semibold">{{ typeof item[1] === 'number' ? number(item[1]) : item[1] }}</dd></div>
          </dl>
          <p class="mt-5 rounded-xl bg-emerald-50 p-4 text-xs leading-5 text-emerald-900">Bu panel yalnızca toplu istatistik gösterir. Ziyaretçilerin kimlik veya iletişim bilgileri paylaşılmaz.</p>
        </article>
      </section>
    </main>
  </div>
</template>
