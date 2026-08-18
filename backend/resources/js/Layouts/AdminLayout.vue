<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
const page = usePage();
const header = ref(null);
const mobileMenuOpen = ref(false);
const openMenu = ref(null);
const currentPath = computed(() => page.url.split('?')[0]);
const navigationCounts = computed(() => page.props.adminNavigationCounts || {});
const toast = ref(null);
let toastTimer = null;

const toastPresentation = {
    success: { title: 'İşlem tamamlandı', icon: '✓', accent: 'bg-emerald-500', iconClass: 'bg-emerald-100 text-emerald-800' },
    error: { title: 'İşlem tamamlanamadı', icon: '!', accent: 'bg-red-500', iconClass: 'bg-red-100 text-red-800' },
    warning: { title: 'Dikkat', icon: '!', accent: 'bg-amber-500', iconClass: 'bg-amber-100 text-amber-800' },
    info: { title: 'Bilgi', icon: 'i', accent: 'bg-sky-500', iconClass: 'bg-sky-100 text-sky-800' },
};

const dismissToast = () => {
    toast.value = null;
    if (toastTimer) {
        window.clearTimeout(toastTimer);
        toastTimer = null;
    }
};

const showToast = (type, message) => {
    dismissToast();
    if (!message) return;

    toast.value = { type, message, ...toastPresentation[type] };
    toastTimer = window.setTimeout(dismissToast, 5000);
};

watch(
    () => page.props.flash,
    flash => {
        if (flash?.error) return showToast('error', flash.error);
        if (flash?.warning) return showToast('warning', flash.warning);
        if (flash?.success) return showToast('success', flash.success);
        if (flash?.info) showToast('info', flash.info);
    },
    { deep: true, immediate: true },
);

const securityItems = [
    { label: 'Mesaj bildirimleri', description: 'Bildirilen konuşmaları incele', href: '/admin/message-reports', countKey: 'messageReports' },
    { label: 'İlan bildirimleri', description: 'Şüpheli ilanları değerlendir', href: '/admin/listing-reports', countKey: 'listingReports' },
    { label: 'Kullanıcı bildirimleri', description: 'Hesap bildirimlerini yönet', href: '/admin/user-reports', countKey: 'userReports' },
    { label: 'Puan denetimi', description: 'Şüpheli puan hareketlerini incele', href: '/admin/cycle-risk-cases', countKey: 'cycleRiskCases' },
];

const systemItems = [
    { label: 'Kullanım limitleri', description: 'İlan, talep ve mesaj sınırları', href: '/admin/usage-policies' },
];

const advertisingItems = [
    { label: 'AdMob yönetimi', description: 'Çalışma modu, reklam alanları ve ödüllü reklamlar', href: '/admin/advertisements/admob' },
    { label: 'Sponsorlu bannerlar', description: 'Bağımsız marka kampanyaları ve performans', href: '/admin/advertisements/sponsors' },
];

const isActive = href => href === '/admin'
    ? currentPath.value === href
    : currentPath.value.startsWith(href);
const isSectionActive = items => items.some(item => isActive(item.href));
const countFor = item => Number(navigationCounts.value[item.countKey] || 0);
const securityPendingCount = computed(() => securityItems.reduce((total, item) => total + countFor(item), 0));

const closeMenus = () => {
    openMenu.value = null;
    mobileMenuOpen.value = false;
};
const toggleDropdown = name => { openMenu.value = openMenu.value === name ? null : name; };
const logout = () => router.post('/admin/logout');
const handleOutsideClick = event => {
    if (header.value && !header.value.contains(event.target)) openMenu.value = null;
};
const handleEscape = event => {
    if (event.key === 'Escape') {
        openMenu.value = null;
        mobileMenuOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', handleOutsideClick);
    document.addEventListener('keydown', handleEscape);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleOutsideClick);
    document.removeEventListener('keydown', handleEscape);
    if (toastTimer) window.clearTimeout(toastTimer);
});
</script>

<template>
    <div class="min-h-screen bg-cream-50 text-slate-900">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0 sm:translate-x-3 sm:translate-y-0"
            enter-to-class="translate-x-0 translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-3 opacity-0"
        >
            <aside
                v-if="toast"
                role="status"
                aria-live="polite"
                class="fixed right-4 top-20 z-[100] w-[calc(100%-2rem)] max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15 sm:right-6"
            >
                <div class="flex items-start gap-3 p-4 pr-3">
                    <span :class="['mt-0.5 grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold', toast.iconClass]" aria-hidden="true">{{ toast.icon }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-950">{{ toast.title }}</p>
                        <p class="mt-0.5 text-sm leading-5 text-slate-600">{{ toast.message }}</p>
                    </div>
                    <button type="button" class="grid size-8 shrink-0 place-items-center rounded-lg text-lg leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Bildirimi kapat" @click="dismissToast">×</button>
                </div>
                <div :class="['h-1 w-full', toast.accent]" />
            </aside>
        </Transition>

        <header ref="header" class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 shadow-sm backdrop-blur-xl">
            <div class="mx-auto grid min-h-16 max-w-[1600px] grid-cols-[1fr_auto] items-center gap-4 px-5 xl:grid-cols-[112px_minmax(0,1fr)_112px] xl:px-8">
                <Link href="/admin" class="w-fit text-[22px] font-semibold tracking-[-.04em] text-slate-900" @click="closeMenus">
                    döngü<span class="text-emerald-600">.</span>
                </Link>

                <nav aria-label="Yönetim bölümleri" class="hidden min-w-0 items-center justify-center gap-1.5 xl:flex">
                    <Link
                        href="/admin"
                        :aria-current="isActive('/admin') ? 'page' : undefined"
                        :class="[
                            'admin-nav-trigger inline-flex h-10 items-center justify-center border-b-2 border-transparent px-3 transition',
                            isActive('/admin') ? 'border-emerald-600 text-slate-950' : 'text-slate-900 hover:border-slate-300 hover:text-slate-950',
                        ]"
                        @click="closeMenus"
                    >
                        <span class="admin-nav-label">Genel Bakış</span>
                    </Link>
                    <Link href="/admin/users" :class="['admin-nav-trigger inline-flex h-10 items-center justify-center border-b-2 border-transparent px-3 transition', isActive('/admin/users') ? 'border-emerald-600 text-slate-950' : 'text-slate-900 hover:border-slate-300 hover:text-slate-950']" @click="closeMenus"><span class="admin-nav-label">Kullanıcılar</span></Link>
                    <Link href="/admin/listings" :class="['admin-nav-trigger inline-flex h-10 items-center justify-center border-b-2 border-transparent px-3 transition', isActive('/admin/listings') ? 'border-emerald-600 text-slate-950' : 'text-slate-900 hover:border-slate-300 hover:text-slate-950']" @click="closeMenus"><span class="admin-nav-label">İlanlar</span></Link>

                    <div class="relative">
                        <button
                            type="button"
                            :aria-expanded="openMenu === 'security'"
                            aria-haspopup="true"
                            :class="[
                                'admin-nav-trigger inline-flex h-10 items-center justify-center gap-1.5 border-b-2 border-transparent px-3 transition',
                                isSectionActive(securityItems) ? 'border-emerald-600 text-slate-950' : 'text-slate-900 hover:border-slate-300 hover:text-slate-950',
                            ]"
                            @click="toggleDropdown('security')"
                        >
                            <span class="admin-nav-label">Güvenlik</span>
                            <span v-if="securityPendingCount" class="rounded-full bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-red-700">({{ securityPendingCount }})</span>
                            <span :class="['text-[10px] transition-transform', openMenu === 'security' && 'rotate-180']" aria-hidden="true">▾</span>
                        </button>
                        <div v-if="openMenu === 'security'" class="absolute left-1/2 top-[calc(100%+.65rem)] w-72 -translate-x-1/2 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/10">
                            <Link
                                v-for="item in securityItems"
                                :key="item.href"
                                :href="item.href"
                                :class="['block rounded-xl px-3.5 py-3 transition', isActive(item.href) ? 'bg-emerald-50' : 'hover:bg-slate-50']"
                                @click="closeMenus"
                            >
                                <span class="flex items-center justify-between gap-3">
                                    <span :class="['block text-sm font-semibold', isActive(item.href) ? 'text-forest-700' : 'text-slate-900']">{{ item.label }}</span>
                                    <span v-if="countFor(item)" class="shrink-0 text-xs font-semibold text-red-600">({{ countFor(item) }})</span>
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-600">{{ item.description }}</span>
                            </Link>
                        </div>
                    </div>

                    <Link href="/admin/announcements" :class="['admin-nav-trigger inline-flex h-10 items-center justify-center border-b-2 border-transparent px-3 transition', isActive('/admin/announcements') ? 'border-emerald-600 text-slate-950' : 'text-slate-900 hover:border-slate-300 hover:text-slate-950']" @click="closeMenus"><span class="admin-nav-label">Duyurular</span></Link>
                    <div class="relative">
                        <button
                            type="button"
                            :aria-expanded="openMenu === 'advertising'"
                            aria-haspopup="true"
                            :class="[
                                'admin-nav-trigger inline-flex h-10 items-center justify-center gap-1.5 border-b-2 border-transparent px-3 transition',
                                isSectionActive(advertisingItems) ? 'border-emerald-600 text-slate-950' : 'text-slate-900 hover:border-slate-300 hover:text-slate-950',
                            ]"
                            @click="toggleDropdown('advertising')"
                        >
                            <span class="admin-nav-label">Reklamlar</span>
                            <span :class="['text-[10px] transition-transform', openMenu === 'advertising' && 'rotate-180']" aria-hidden="true">▾</span>
                        </button>
                        <div v-if="openMenu === 'advertising'" class="absolute left-1/2 top-[calc(100%+.65rem)] w-72 -translate-x-1/2 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/10">
                            <Link v-for="item in advertisingItems" :key="item.href" :href="item.href" :class="['block rounded-xl px-3.5 py-3 transition', isActive(item.href) ? 'bg-emerald-50' : 'hover:bg-slate-50']" @click="closeMenus">
                                <span :class="['block text-sm font-semibold', isActive(item.href) ? 'text-forest-700' : 'text-slate-900']">{{ item.label }}</span>
                                <span class="mt-0.5 block text-xs text-slate-600">{{ item.description }}</span>
                            </Link>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            :aria-expanded="openMenu === 'system'"
                            aria-haspopup="true"
                            :class="[
                                'admin-nav-trigger inline-flex h-10 items-center justify-center gap-1.5 border-b-2 border-transparent px-3 transition',
                                isSectionActive(systemItems) ? 'border-emerald-600 text-slate-950' : 'text-slate-900 hover:border-slate-300 hover:text-slate-950',
                            ]"
                            @click="toggleDropdown('system')"
                        >
                            <span class="admin-nav-label">Sistem Ayarları</span>
                            <span :class="['text-[10px] transition-transform', openMenu === 'system' && 'rotate-180']" aria-hidden="true">▾</span>
                        </button>
                        <div v-if="openMenu === 'system'" class="absolute right-0 top-[calc(100%+.65rem)] w-72 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/10">
                            <Link
                                v-for="item in systemItems"
                                :key="item.href"
                                :href="item.href"
                                :class="['block rounded-xl px-3.5 py-3 transition', isActive(item.href) ? 'bg-emerald-50' : 'hover:bg-slate-50']"
                                @click="closeMenus"
                            >
                                <span class="flex items-center justify-between gap-3">
                                    <span :class="['block text-sm font-semibold', isActive(item.href) ? 'text-forest-700' : 'text-slate-900']">{{ item.label }}</span>
                                    <span v-if="countFor(item)" class="shrink-0 text-xs font-semibold text-red-600">({{ countFor(item) }})</span>
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-600">{{ item.description }}</span>
                            </Link>
                        </div>
                    </div>
                </nav>

                <button type="button" class="hidden min-h-9 justify-self-end rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-semibold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 xl:block" @click="logout">
                    Çıkış yap
                </button>

                <div class="flex items-center justify-end gap-2 xl:hidden">
                    <button type="button" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" :aria-expanded="mobileMenuOpen" aria-controls="admin-mobile-navigation" @click="mobileMenuOpen = !mobileMenuOpen">
                        <span aria-hidden="true" class="text-base leading-none text-emerald-700">{{ mobileMenuOpen ? '×' : '☰' }}</span> Menü
                    </button>
                    <button type="button" class="min-h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700" @click="logout">Çıkış</button>
                </div>
            </div>

            <nav v-if="mobileMenuOpen" id="admin-mobile-navigation" aria-label="Mobil yönetim bölümleri" class="max-h-[calc(100vh-4rem)] overflow-y-auto border-t border-slate-100 bg-white px-5 pb-5 pt-3 xl:hidden">
                <div class="mx-auto max-w-[1600px] space-y-4">
                    <Link href="/admin" :class="['block rounded-xl px-4 py-3 text-sm font-semibold', isActive('/admin') ? 'bg-emerald-50 text-forest-700' : 'text-slate-900 hover:bg-slate-50']" @click="closeMenus">Genel Bakış</Link>
                    <section class="grid gap-1 sm:grid-cols-2"><Link href="/admin/users" :class="['rounded-xl px-4 py-3 text-sm font-semibold', isActive('/admin/users') ? 'bg-emerald-50 text-forest-700' : 'text-slate-900 hover:bg-slate-50']" @click="closeMenus">Kullanıcılar</Link><Link href="/admin/listings" :class="['rounded-xl px-4 py-3 text-sm font-semibold', isActive('/admin/listings') ? 'bg-emerald-50 text-forest-700' : 'text-slate-900 hover:bg-slate-50']" @click="closeMenus">İlanlar</Link></section>
                    <section>
                        <p class="px-4 pb-1.5 text-[10px] font-semibold uppercase tracking-[.16em] text-slate-600">Güvenlik</p>
                        <Link v-for="item in securityItems" :key="item.href" :href="item.href" :class="['flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold', isActive(item.href) ? 'bg-emerald-50 text-forest-700' : 'text-slate-900 hover:bg-slate-50']" @click="closeMenus"><span>{{ item.label }}</span><span v-if="countFor(item)" class="ml-auto text-xs font-semibold text-red-600">({{ countFor(item) }})</span></Link>
                    </section>
                    <section class="grid gap-1 sm:grid-cols-2">
                        <Link href="/admin/announcements" :class="['rounded-xl px-4 py-3 text-sm font-semibold', isActive('/admin/announcements') ? 'bg-emerald-50 text-forest-700' : 'text-slate-900 hover:bg-slate-50']" @click="closeMenus">Duyurular</Link>
                        </section>
                    <section>
                        <p class="px-4 pb-1.5 text-[10px] font-semibold uppercase tracking-[.16em] text-slate-600">Reklamlar</p>
                        <Link v-for="item in advertisingItems" :key="item.href" :href="item.href" :class="['block rounded-xl px-4 py-2.5 text-sm font-semibold', isActive(item.href) ? 'bg-emerald-50 text-forest-700' : 'text-slate-900 hover:bg-slate-50']" @click="closeMenus">{{ item.label }}</Link>
                    </section>
                    <section>
                        <p class="px-4 pb-1.5 text-[10px] font-semibold uppercase tracking-[.16em] text-slate-600">Sistem Ayarları</p>
                        <Link v-for="item in systemItems" :key="item.href" :href="item.href" :class="['flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold', isActive(item.href) ? 'bg-emerald-50 text-forest-700' : 'text-slate-900 hover:bg-slate-50']" @click="closeMenus"><span>{{ item.label }}</span><span v-if="countFor(item)" class="ml-auto text-xs font-semibold text-red-600">({{ countFor(item) }})</span></Link>
                    </section>
                </div>
            </nav>
        </header>

        <section v-if="title" class="border-b border-emerald-100/80 bg-gradient-to-r from-white via-emerald-50/45 to-cream-50">
            <div class="mx-auto max-w-[1600px] px-5 py-7 lg:px-8 lg:py-8">
                <p v-if="eyebrow" class="text-[11px] font-semibold uppercase tracking-[.16em] text-emerald-700">{{ eyebrow }}</p>
                <h1 class="mt-1.5 text-2xl font-semibold tracking-[-.025em] text-slate-900 sm:text-[28px]">{{ title }}</h1>
                <p v-if="description" class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">{{ description }}</p>
            </div>
        </section>

        <slot />
    </div>
</template>
<style scoped>
.admin-nav-trigger,
.admin-nav-label {
    font-family: var(--font-sans) !important;
    font-size: 14px !important;
    font-style: normal !important;
    font-weight: 600 !important;
    letter-spacing: 0 !important;
    line-height: 1 !important;
    text-transform: none !important;
}
</style>
