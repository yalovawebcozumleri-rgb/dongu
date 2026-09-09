(() => {
  const modal = document.querySelector('[data-welcome-modal]');
  if (!modal) return;

  const sessionKey = modal.dataset.sessionKey || 'dongu_welcome_seen_v1';
  try {
    if (window.sessionStorage.getItem(sessionKey) === '1') return;
  } catch (_) {
    // Storage may be unavailable in strict privacy modes; the modal can still work.
  }

  const dialog = modal.querySelector('[role="dialog"]');
  const closeButtons = modal.querySelectorAll('[data-welcome-close]');
  let closeTimer = null;

  const rememberVisit = () => {
    try {
      window.sessionStorage.setItem(sessionKey, '1');
    } catch (_) {}
  };

  const open = () => {
    rememberVisit();
    modal.hidden = false;
    document.documentElement.classList.add('welcome-modal-open');
    document.body.classList.add('welcome-modal-open');
    window.requestAnimationFrame(() => {
      modal.classList.add('is-visible');
      dialog.focus({ preventScroll: true });
    });
  };

  const close = () => {
    modal.classList.remove('is-visible');
    document.documentElement.classList.remove('welcome-modal-open');
    document.body.classList.remove('welcome-modal-open');
    closeTimer = window.setTimeout(() => {
      modal.hidden = true;
      closeTimer = null;
    }, 300);
  };

  closeButtons.forEach(button => button.addEventListener('click', close));
  document.addEventListener('keydown', event => {
    if (modal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...modal.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  window.setTimeout(open, 1000);
  window.addEventListener('pagehide', () => {
    if (closeTimer !== null) window.clearTimeout(closeTimer);
  });
})();
