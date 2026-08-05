import './bootstrap';
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { formatAdminDate } from './lib/adminDate';

createInertiaApp({
    title: title => title ? `${title} · Döngü Yönetim` : 'Döngü Yönetim',
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.globalProperties.$adminDate = formatAdminDate;
        app.use(plugin).mount(el);
    },
});
