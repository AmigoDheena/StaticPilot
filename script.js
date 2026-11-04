const demos = {
  company: {
    title: 'Company details', description: 'The little details that make your business, yours.',
    content: document.getElementById('demo-content').innerHTML
  },
  services: {
    title: 'Services', description: 'Your expertise, in your own words.',
    content: '<div class="demo-row"><div><h4>Brand strategy</h4><p>A clear direction for what comes next.</p></div><span>/services/brand-strategy</span></div><div class="demo-row"><div><h4>Website design</h4><p>Thoughtful websites with a point of view.</p></div><span>/services/website-design</span></div><div class="demo-row"><div><h4>Creative direction</h4><p>A consistent story, from start to finish.</p></div><span>/services/creative-direction</span></div><div class="demo-footer"><span><i></i> Titles, descriptions, and search details</span><span class="demo-tag">3 sample services</span></div>'
  },
  custom: {
    title: 'Custom sections', description: 'A place for the content that makes your website unique.',
    content: '<div class="demo-row"><div><h4>Our purpose</h4><p>Mission, vision, and the things we believe in.</p></div><span>3 entries</span></div><div class="demo-row"><div><h4>Why choose us</h4><p>The details that make working together different.</p></div><span>4 entries</span></div><div class="demo-row"><div><h4>Shared entry fields</h4><p>Text · Long text · Number · URL · Checkbox · Dropdown</p></div><span>6 field types</span></div><div class="demo-footer"><span><i></i> Permanent keys. Flexible content.</span><span class="demo-tag">Structured your way</span></div>'
  }
};
const tabs = [...document.querySelectorAll('[data-demo]')];
function activateTab(tab) {
  const demo = demos[tab.dataset.demo];
  tabs.forEach(item => { const active = item === tab; item.setAttribute('aria-selected', String(active)); item.tabIndex = active ? 0 : -1; });
  document.getElementById('demo-panel').setAttribute('aria-labelledby', tab.id);
  document.getElementById('demo-title').textContent = demo.title;
  document.getElementById('demo-breadcrumb').textContent = demo.title;
  document.getElementById('demo-description').textContent = demo.description;
  document.getElementById('demo-content').innerHTML = demo.content;
}
tabs.forEach((tab, index) => {
  tab.addEventListener('click', () => activateTab(tab));
  tab.addEventListener('keydown', event => {
    let next;
    if (['ArrowDown', 'ArrowRight'].includes(event.key)) next = (index + 1) % tabs.length;
    if (['ArrowUp', 'ArrowLeft'].includes(event.key)) next = (index - 1 + tabs.length) % tabs.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = tabs.length - 1;
    if (next !== undefined) { event.preventDefault(); activateTab(tabs[next]); tabs[next].focus(); }
  });
});
const mobileTabs = window.matchMedia('(max-width: 480px)');
function updateOrientation() { document.querySelector('[role="tablist"]').setAttribute('aria-orientation', mobileTabs.matches ? 'horizontal' : 'vertical'); }
updateOrientation();
mobileTabs.addEventListener('change', updateOrientation);
document.getElementById('copy-code').addEventListener('click', async () => {
  const code = document.getElementById('integration-code');
  const status = document.getElementById('copy-status');
  try { await navigator.clipboard.writeText(code.textContent); status.textContent = 'Copied to clipboard'; }
  catch {
    const range = document.createRange(); range.selectNodeContents(code);
    const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
    status.textContent = 'Code selected. Use your device’s copy command.';
  }
});
