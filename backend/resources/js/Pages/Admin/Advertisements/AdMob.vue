<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AdMobRuntimeSettings from './AdMobRuntimeSettings.vue';
import PlacementSettings from './PlacementSettings.vue';

defineProps({
  placementSettings: { type: Array, required: true },
  adMob: { type: Object, required: true },
});
</script>

<template>
  <Head title="AdMob Yönetimi" />
  <AdminLayout eyebrow="Reklamlar" title="AdMob yönetimi" description="Reklam çalışma ortamını, yerleştirilmiş reklam alanlarını, ödüllü videoları ve geçiş reklamlarını tek yerden yönet.">
    <main class="mx-auto max-w-[1600px] px-5 py-8 lg:px-8">
      <AdMobRuntimeSettings :setting="adMob.runtime" />
      <PlacementSettings class="mt-5" :settings="placementSettings" />
    </main>
  </AdminLayout>
</template>