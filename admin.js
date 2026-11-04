'use strict';

const customList = document.getElementById('custom-field-list');
const addCustom = document.getElementById('add-custom-field');
let nextCustomId = 0;
function updateCustomExamples() {
  if (!customList) return;
  const source = customList.dataset.customSource;
  const examples = new Map();
  const typedExamples = JSON.parse(customList.dataset.fieldExamples || '{}');
  const snippet = name => {
    if (Object.prototype.hasOwnProperty.call(typedExamples, name)) return typedExamples[name];
    const key = name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    return `<?= content_escape(${source}['${key}'] ?? '') ?>`;
  };
  JSON.parse(customList.dataset.savedFieldNames).forEach(name => examples.set(name, `  <p>${snippet(name)}</p>`));
  customList.querySelectorAll('.custom-field-row').forEach(row => {
    const name = row.querySelector('input').value.trim();
    // PHP single-quoted array keys escape backslashes and apostrophes only.
    const example = name ? snippet(name) : 'Enter a field name to generate its PHP example.';
    row.querySelector('code').textContent = example;
    row.querySelector('summary').setAttribute('aria-label', `PHP example for ${name || 'custom field'}`);
    row.querySelector('[data-copy]').disabled = !name;
    row.querySelector('.remove-custom-field').setAttribute('aria-label', `Remove ${name || 'custom field'}`);
    if (name) examples.set(name, `  <p>${example}</p>`);
  });
  const code = document.getElementById('section-code');
  code.textContent = code.dataset.exampleBase.replace('__CUSTOM_FIELDS__', () => [...examples.values()].join('\n'));
}

const slugInput = document.getElementById('slug');
const urlPreview = document.getElementById('public-url-preview');
if (slugInput && urlPreview) {
  slugInput.addEventListener('input', () => {
    urlPreview.textContent = urlPreview.dataset.urlPattern.replace('__SLUG__', encodeURIComponent(slugInput.value.trim() || 'example'));
  });
}

const definitionList = document.getElementById('definition-list');
if (definitionList) {
  let index = Math.max(-1, ...[...definitionList.children].map(row => Number(row.dataset.definitionIndex)).filter(Number.isFinite)) + 1;
  document.getElementById('add-definition').addEventListener('click', () => {
    const status = document.getElementById('definition-status');
    if (definitionList.children.length >= 50) { status.textContent = 'You can define up to 50 shared fields.'; return; }
    const fragment = document.getElementById('definition-template').content.cloneNode(true);
    fragment.querySelectorAll('*').forEach(node => {
      [...node.attributes].forEach(attr => { if (attr.value.includes('__INDEX__')) node.setAttribute(attr.name, attr.value.replaceAll('__INDEX__', String(index))); });
    });
    index++;
    definitionList.append(fragment);
    definitionList.lastElementChild.querySelector('input:not([type="hidden"])').focus();
    status.textContent = 'Field added. Save the section to apply it to every entry.';
  });
  definitionList.addEventListener('change', event => {
    if (event.target.matches('select')) event.target.closest('.definition-row').querySelector('.definition-options').hidden = event.target.value !== 'select';
  });
  definitionList.addEventListener('click', event => {
    if (!event.target.closest('.remove-definition')) return;
    event.target.closest('.definition-row').remove();
    document.getElementById('add-definition').focus();
    document.getElementById('definition-status').textContent = 'Definition removed. Existing entry values are retained. Save to apply.';
  });
}

function updateImagePreview(select) {
  const preview = select.parentElement.querySelector('.image-preview');
  const option = select.selectedOptions[0];
  preview.hidden = !option?.dataset.url;
  if (option?.dataset.url) { preview.src = option.dataset.url; preview.alt = option.dataset.alt || ''; }
  else preview.removeAttribute('src');
}
document.querySelectorAll('.image-picker').forEach(select => select.addEventListener('change', () => updateImagePreview(select)));
document.addEventListener('click', async event => {
  const button = event.target.closest('.refresh-media');
  if (!button) return;
  button.disabled = true;
  try {
    const response = await fetch('media.php?format=json', {headers:{Accept:'application/json'}});
    if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw Error();
    const images = await response.json();
    document.querySelectorAll('.image-picker').forEach(select => {
      const selected = select.value;
      select.replaceChildren(new Option('No image', ''));
      images.forEach(image => {
        const option = new Option(image.title, image.id);
        option.dataset.url = image.url; option.dataset.alt = image.alt; select.append(option);
      });
      select.value = selected;
      updateImagePreview(select);
    });
    button.textContent = 'Images refreshed';
  } catch { button.textContent = 'Unable to refresh — sign in and retry'; }
  finally { button.disabled = false; }
});

let validationId = 0;
document.addEventListener('invalid', event => {
  const input = event.target;
  if (!input.matches('input,textarea,select')) return;
  let error = document.getElementById(input.dataset.nativeError || '');
  if (!error) {
    error = document.createElement('p'); error.className = 'field-error'; error.id = `native-error-${validationId++}`;
    input.dataset.nativeError = error.id;
    input.insertAdjacentElement('afterend', error);
  }
  error.textContent = input.validationMessage;
  input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', error.id);
}, true);
document.addEventListener('input', event => {
  const input = event.target;
  if (input.dataset.nativeError && input.validity.valid) {
    document.getElementById(input.dataset.nativeError)?.remove();
    input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); delete input.dataset.nativeError;
  }
});
if (customList && addCustom) {
  const status = document.getElementById('custom-field-status');
  customList.addEventListener('input', () => {
    updateCustomExamples();
    document.querySelectorAll('.copy-status').forEach(node => { node.textContent = ''; });
  });
  addCustom.addEventListener('click', () => {
    if (customList.children.length >= 50) {
      status.textContent = 'You can add up to 50 custom fields.';
      return;
    }
    const fragment = document.getElementById('custom-field-template').content.cloneNode(true);
    const idMap = new Map();
    fragment.querySelectorAll('[id]').forEach(node => {
      const id = `dynamic-custom-${nextCustomId++}`;
      idMap.set(node.id, id);
      node.id = id;
    });
    fragment.querySelectorAll('[for], [aria-controls], [data-copy]').forEach(node => {
      for (const attr of ['for', 'aria-controls', 'data-copy']) {
        if (idMap.has(node.getAttribute(attr))) node.setAttribute(attr, idMap.get(node.getAttribute(attr)));
      }
    });
    customList.append(fragment);
    updateCustomExamples();
    customList.lastElementChild.querySelector('input').focus();
    status.textContent = 'Custom field added. Enter a name and value, then save.';
  });
  customList.addEventListener('click', event => {
    const button = event.target.closest('.remove-custom-field');
    if (!button) return;
    const row = button.closest('.custom-field-row');
    const next = row.nextElementSibling || row.previousElementSibling;
    row.remove();
    updateCustomExamples();
    (next ? next.querySelector('input') : addCustom).focus();
    status.textContent = 'Custom field removed. Save to apply this change.';
  });
  updateCustomExamples();
}

const helpPanels = () => document.querySelectorAll('.code-help');
document.addEventListener('toggle', event => {
  const panel = event.target;
  if (panel.matches('.code-help') && panel.open) {
    helpPanels().forEach(other => { if (other !== panel) other.open = false; });
  }
}, true);

document.addEventListener('click', async event => {
  helpPanels().forEach(panel => {
    if (!panel.contains(event.target)) panel.open = false;
  });
  const button = event.target.closest('[data-copy]');
  if (!button) return;
  const code = document.getElementById(button.dataset.copy);
  const status = button.closest('.code-popover, .developer-example').querySelector('.copy-status');
  try {
    await navigator.clipboard.writeText(code.textContent);
    status.textContent = 'Copied!';
  } catch {
    // Clipboard access may be unavailable on HTTP installations. Keep the code selectable.
    const range = document.createRange();
    range.selectNodeContents(code);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    status.textContent = 'Code selected. Press Ctrl+C or Command+C to copy.';
  }
});

document.addEventListener('keydown', event => {
  if (event.key !== 'Escape') return;
  helpPanels().forEach(panel => {
    if (!panel.open) return;
    if (panel.contains(document.activeElement)) panel.querySelector('summary').focus();
    panel.open = false;
  });
});
