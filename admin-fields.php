<?php
function field_error($key, $errors) {
    if (isset($errors[$key])) { echo '<p class="field-error" id="error-' . e($key) . '">' . e($errors[$key]) . '</p>'; }
}
function field_error_attributes($key, $errors) {
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="error-' . e($key) . '"' : '';
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
