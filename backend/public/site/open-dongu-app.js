// A web preview promotes the nearby home, never the linked listing.
(() => {
  const buttons = document.querySelectorAll('[data-open-dongu]');
  if (!buttons.length) return;
  let timer = null;
  const cancel = () => {
    if (timer !== null) window.clearTimeout(timer);
    timer = null;
  };
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) cancel();
  });
  window.addEventListener('pagehide', cancel);
  // Browser permission prompts may blur the page; do not redirect behind them.
  window.addEventListener('blur', cancel);
  buttons.forEach(button => {
    button.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
      const fallback = new URL(button.dataset.downloadUrl, window.location.href);
      if (fallback.origin !== window.location.origin) return;
      event.preventDefault();
      cancel();
      const mobile = /android|iphone|ipad|ipod/i.test(navigator.userAgent)
        || (/macintosh/i.test(navigator.userAgent) && navigator.maxTouchPoints > 1);
      if (!mobile) {
        window.location.assign(fallback.href);
        return;
      }
      const startedAt = Date.now();
      timer = window.setTimeout(() => {
        timer = null;
        // Never redirect when returning from the app after a suspended timer.
        if (!document.hidden && document.hasFocus() && Date.now() - startedAt < 5000) {
          window.location.assign(fallback.href);
        }
      }, 2200);
      window.location.assign('dongu://home');
    });
  });
})();
