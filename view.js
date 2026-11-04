(() => {
  let client = false;
  try { client = localStorage.getItem('staticpilot-view') === 'client'; } catch {}
  const apply = () => {
    document.documentElement.dataset.view = client ? 'client' : 'developer';
    const button = document.getElementById('view-mode-toggle');
    if (button) { button.textContent = client ? 'Developer view' : 'Client view'; button.setAttribute('aria-pressed', String(client)); }
  };
  apply();
  document.addEventListener('DOMContentLoaded', apply);
  document.addEventListener('click', event => {
    if (!event.target.closest('#view-mode-toggle')) return;
    client = !client;
    try { localStorage.setItem('staticpilot-view', client ? 'client' : 'developer'); } catch {}
    apply();
  });
})();
