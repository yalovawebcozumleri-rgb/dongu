if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

const resetListingPreviewScroll = () => {
    requestAnimationFrame(() => window.scrollTo(0, 0));
};

resetListingPreviewScroll();
window.addEventListener('load', resetListingPreviewScroll, { once: true });
window.addEventListener('pageshow', resetListingPreviewScroll);
