// Run before styles paint so saved appearance is applied on every page.
(() => {
  const key = 'staticpilot-theme';
  const system = window.matchMedia('(prefers-color-scheme: dark)');
  let preference = 'system';
  try { preference = localStorage.getItem(key) || 'system'; } catch {}
  const valid = value => ['light', 'dark', 'system'].includes(value) ? value : 'system';
  const apply = () => {
    preference = valid(preference);
    document.documentElement.dataset.theme = preference === 'system' ? (system.matches ? 'dark' : 'light') : preference;
    document.querySelectorAll('[data-theme-choice]').forEach(button => {
      button.setAttribute('aria-pressed', String(button.dataset.themeChoice === preference));
    });
  };
  apply();
  document.addEventListener('DOMContentLoaded', apply);
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-theme-choice]');
    if (!button) return;
    preference = button.dataset.themeChoice;
    try { localStorage.setItem(key, preference); } catch {}
    apply();
  });
  system.addEventListener('change', apply);
  window.addEventListener('storage', event => {
    if (event.key === key || event.key === null) { preference = event.newValue || 'system'; apply(); }
  });
})();
