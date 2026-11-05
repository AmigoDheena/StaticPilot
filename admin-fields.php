<?php
function field_error($key, $errors) {
    if (isset($errors[$key])) { echo '<p class="field-error" id="error-' . e($key) . '">' . e($errors[$key]) . '</p>'; }
}
function field_error_attributes($key, $errors) {
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="error-' . e($key) . '"' : '';
}
function content_field_controls($section, $fields, $draft, $errors, $sectionManager, $editing, $seo = false) {
    $notes = [
        'seo_title'=>'The title shown in browser tabs and search results. Leave blank to use the page name.',
        'seo_description'=>'A short summary for search results. Describe what visitors will find on this page.',
        'seo_keywords'=>'Optional keywords separated by commas, for example website design, speed optimization.',
        'seo_robots'=>'Controls search indexing and link following. Leave blank for index, follow; use noindex, follow to request exclusion from search.',
        'canonical_url'=>'The preferred address for this content. Leave blank to use this page’s URL.',
        'og_type'=>'Describes the shared content, for example website or article. Defaults to website.',
        'og_url'=>'The address used when sharing on Facebook. Leave blank to use the canonical URL.',
        'og_title'=>'The headline in the Facebook preview. Leave blank to use the primary title.',
        'og_description'=>'The summary in the Facebook preview. Leave blank to use the primary description.',
        'og_image'=>'Image for the Facebook preview, for example https://example.com/images/share.jpg. Leave blank to omit the image tag.',
        'twitter_card'=>'Preview format: summary or summary_large_image. Defaults to summary_large_image.',
        'twitter_url'=>'The shared page address. Leave blank to use the Open Graph URL.',
        'twitter_title'=>'The headline in the Twitter preview. Leave blank to use the Open Graph title.',
        'twitter_description'=>'The summary in the Twitter preview. Leave blank to use the Open Graph description.',
        'twitter_image'=>'Image URL for the Twitter preview. Leave blank to use the Open Graph image.',
    ];
    foreach ($fields as $key=>$spec) {
        $wide = $spec[2] === 'textarea' || in_array($key, ['address','base_url','link','canonical_url','og_url','og_image','twitter_url','twitter_image'], true);
        echo '<div class="form-field ' . ($wide ? 'full-width' : '') . '"><div class="field-heading"><label for="' . e($key) . '">' . e($spec[0]) . ($spec[3] ? ' (required)' : '') . '</label>';
        if (!$seo) { developer_field_help($section, $key, $spec[0]); }
        echo '</div>';
        $attributes = field_error_attributes($key, $errors) . ($sectionManager && $editing && $key === 'slug' ? ' readonly' : '') . ($spec[3] ? ' required' : '');
        if ($seo) { $attributes = (isset($errors[$key]) ? ' aria-invalid="true"' : '') . ' aria-describedby="note-' . e($key) . (isset($errors[$key]) ? ' error-' . e($key) : '') . '"'; }
        if ($spec[2] === 'textarea') {
            echo '<textarea id="' . e($key) . '" name="' . e($key) . '" rows="' . ($key === 'description' ? '5' : '3') . '" maxlength="' . $spec[1] . '"' . $attributes . '>' . e($draft[$key] ?? '') . '</textarea>';
        } else {
            echo '<input id="' . e($key) . '" name="' . e($key) . '" type="' . e($spec[2]) . '" value="' . e($draft[$key] ?? '') . '" maxlength="' . $spec[1] . '"' . $attributes . '>';
        }
        field_error($key, $errors);
        if ($seo) { echo '<p class="field-note" id="note-' . e($key) . '">' . e($notes[$key] ?? '') . '</p>'; }
        elseif ($key === 'google_analytics_code') { echo '<p class="field-note">Paste the Google Analytics JavaScript snippet from your trusted analytics account. In your website template, output <code>&lt;?= $google_analytics_code ?&gt;</code> once inside <code>&lt;head&gt;</code>. This code is emitted as written so the tracking script can run.</p>'; }
        elseif ($key === 'slug' && $sectionManager) { echo '<p class="field-note">A unique, permanent key such as <code>why-choose-us</code>. Use lowercase letters, numbers, and hyphens. Used in PHP templates; this does not create a page URL.</p>'; }
        elseif ($key === 'slug') { echo '<p class="field-note">Use lowercase words separated by hyphens. Public URL: <span id="public-url-preview" data-url-pattern="' . e(content_url($section, '__SLUG__')) . '">' . e(content_url($section, ($draft[$key] ?? '') ?: 'example')) . '</span>. Real files and folders take precedence.</p>'; }
        elseif (in_array($key, ['canonical_url','og_url','og_image','twitter_url','twitter_image'], true)) { echo '<p class="field-note">Use an absolute URL or a website-relative path. Leave blank to use the available primary metadata fallback.</p>'; }
        elseif ($key === 'seo_robots') { echo '<p class="field-note">Leave blank for <code>index, follow</code>.</p>'; }
        elseif ($key === 'og_type') { echo '<p class="field-note">Leave blank for <code>website</code>.</p>'; }
        elseif ($key === 'twitter_card') { echo '<p class="field-note">Leave blank for <code>summary_large_image</code>.</p>'; }
        echo '</div>';
    }
}
function definition_row($index, $definition = [], $errors = []) {
    $key = $definition['key'] ?? ''; $label = $definition['label'] ?? ''; $type = $definition['type'] ?? 'text';
    $original = $definition['original_key'] ?? $key;
    $options = $definition['options'] ?? []; if (is_array($options)) { $options = implode("\n", $options); }
    $name = 'field_defs[' . $index . ']'; $id = 'definition-' . $index;
    echo '<div class="definition-row" data-definition-index="' . e($index) . '"><input type="hidden" name="' . e($name) . '[original_key]" value="' . e($original) . '"><div class="definition-grid">';
    foreach (['label'=>['Label',$label],'key'=>['Permanent key',$key]] as $part=>$spec) {
        echo '<label>' . $spec[0] . '<input name="' . e($name) . '[' . $part . ']" value="' . e($spec[1]) . '" maxlength="' . ($part === 'key' ? 64 : 180) . '" ' . ($part === 'key' && $original !== '' ? 'readonly' : '') . '></label>';
    }
    echo '<label>Type<select name="' . e($name) . '[type]" ' . ($original !== '' ? 'disabled' : '') . '>';
    foreach (['text'=>'Text','textarea'=>'Long text','number'=>'Number','url'=>'URL','checkbox'=>'Checkbox','select'=>'Dropdown'] as $value=>$title) { echo '<option value="' . $value . '" ' . ($type === $value ? 'selected' : '') . '>' . $title . '</option>'; }
    echo '</select></label>';
    if ($original !== '') { echo '<input type="hidden" name="' . e($name) . '[type]" value="' . e($type) . '">'; }
    echo '<label class="check-label"><input type="checkbox" name="' . e($name) . '[required]" value="1" ' . (!empty($definition['required']) ? 'checked' : '') . '>Required</label></div><label class="definition-options" ' . ($type !== 'select' ? 'hidden' : '') . '>Dropdown options (one per line)<textarea name="' . e($name) . '[options]" maxlength="10000" rows="3">' . e($options) . '</textarea></label>';
    field_error($id, $errors);
    echo '<button type="button" class="remove-definition">Remove field definition</button></div>';
}
function shared_field_controls($section, $definitions, $values, $data, $errors) {
    if (!$definitions) { return; }
    echo '<fieldset class="custom-fields"><legend>Section fields</legend><div class="form-grid">';
    foreach ($definitions as $field) {
        $key = $field['key']; $id = 'shared-' . $key; $type = $field['type']; $value = $values[$key] ?? '';
        if (!is_string($value)) { $value = ''; }
        $required = $field['required'] ? 'required' : '';
        echo '<div class="form-field ' . ($type === 'textarea' ? 'full-width' : '') . '"><div class="field-heading"><label for="' . e($id) . '">' . e($field['label']) . ($field['required'] ? ' (required)' : '') . '</label>';
        developer_field_help($section, $id, $field['label'], developer_custom_example($section, $key, $type));
        echo '</div>';
        $attrs = field_error_attributes($id, $errors); $name = 'shared[' . $key . ']';
        if ($type === 'textarea') { echo '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="3" maxlength="20000" ' . $required . $attrs . '>' . e($value) . '</textarea>'; }
        elseif ($type === 'select') {
            echo '<select id="' . e($id) . '" name="' . e($name) . '" ' . $required . $attrs . '><option value="">Choose an option</option>';
            foreach ($field['options'] as $option) { echo '<option value="' . e($option) . '" ' . ($value === $option ? 'selected' : '') . '>' . e($option) . '</option>'; } echo '</select>';
        } elseif ($type === 'checkbox') { echo '<input type="checkbox" class="shared-checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1" ' . ($value === '1' ? 'checked' : '') . ' ' . $required . $attrs . '>'; }
        else { echo '<input id="' . e($id) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '" ' . ($type === 'number' ? 'step="any"' : 'maxlength="2000"') . ' ' . $required . $attrs . '>'; }
        field_error($id, $errors); echo '</div>';
    }
    echo '</div></fieldset>';
}
