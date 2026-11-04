<?php
class ContentValidationException extends RuntimeException {
    public $errors;
    function __construct($errors) { $this->errors = $errors; parent::__construct(implode(' ', array_values($errors))); }
}
function content_definitions($data, $section) {
    if (strpos($section, 'custom:') !== 0) { return []; }
    foreach ($data['custom_sections'] as $custom) {
        if ($custom['slug'] === substr($section, 7)) { return $custom['fields'] ?? []; }
    }
    return [];
}
function content_validate_definitions($input, $existing = []) {
    if (!isset($input['definitions_present'])) { return $existing; }
    $rows = $input['field_defs'] ?? [];
    if (!is_array($rows) || count($rows) > 50) { throw new ContentValidationException(['definitions'=>'Use at most 50 shared fields.']); }
    $types = ['text','textarea','number','url','image','checkbox','select'];
    $result = []; $errors = []; $old = array_column($existing, null, 'key');
    foreach ($rows as $index => $row) {
        $errorKey = 'definition-' . $index;
        if (!is_array($row)) { $errors[$errorKey] = 'Invalid field definition.'; continue; }
        foreach (['key','label','type','options','original_key'] as $part) {
            if (!is_string($row[$part] ?? '')) { $errors[$errorKey] = 'Invalid field definition.'; continue 2; }
        }
        $key = trim($row['key'] ?? ''); $label = trim($row['label'] ?? ''); $type = $row['type'] ?? 'text';
        if ($key === '' && $label === '') { continue; }
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key)) { $errors[$errorKey] = 'Field keys must start with a lowercase letter and contain only lowercase letters, numbers, and underscores (up to 64).'; continue; }
        if ($label === '' || strlen($label) > 180 || !in_array($type, $types, true)) { $errors[$errorKey] = 'Enter a label of up to 180 characters and a supported type.'; continue; }
        if (isset($result[$key])) { $errors[$errorKey] = 'Shared field keys must be unique.'; continue; }
        $original = $row['original_key'] ?? '';
        if ($original !== '' && (!isset($old[$original]) || $original !== $key)) { $errors[$errorKey] = 'A saved field key cannot be changed. Rename its label instead.'; continue; }
        if (isset($old[$key]) && $old[$key]['type'] !== $type) { $errors[$errorKey] = 'A saved field type cannot be changed. Create a new field instead.'; continue; }
        $options = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $row['options'] ?? '')), 'strlen')));
        if ($type === 'select' && (!$options || count($options) > 100 || strlen($row['options'] ?? '') > 10000)) { $errors[$errorKey] = 'Dropdowns need 1–100 options, one per line (up to 10,000 characters total).'; continue; }
        $result[$key] = ['key'=>$key,'label'=>$label,'type'=>$type,'required'=>isset($row['required']),'options'=>$type === 'select' ? $options : []];
    }
    if ($errors) { throw new ContentValidationException($errors); }
    return array_values($result);
}
function content_validate_shared($definitions, $input, $media) {
    $values = $input['shared'] ?? [];
    if (!is_array($values)) { throw new ContentValidationException(['shared'=>'Invalid shared fields.']); }
    $result = []; $errors = [];
    foreach ($definitions as $field) {
        $key = $field['key']; $type = $field['type']; $value = $values[$key] ?? ($type === 'checkbox' ? '0' : '');
        if (!is_string($value)) { $errors['shared-' . $key] = 'Enter a valid ' . $field['label'] . '.'; continue; }
        $value = trim(str_replace("\r\n", "\n", $value));
        $message = null;
        if ($field['required'] && ($value === '' || ($type === 'checkbox' && $value !== '1'))) { $message = $field['label'] . ' is required.'; }
        elseif (strlen($value) > ($type === 'textarea' ? 20000 : 2000)) { $message = $field['label'] . ' is too long.'; }
        elseif ($type === 'number' && $value !== '' && (!is_numeric($value) || !is_finite((float)$value))) { $message = 'Enter a valid number.'; }
        elseif ($type === 'url' && $value !== '' && (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $value))) { $message = 'Enter a URL beginning with https:// or http://.'; }
        elseif ($type === 'checkbox' && !in_array($value, ['0','1'], true)) { $message = 'Choose checked or unchecked.'; }
        elseif ($type === 'select' && $value !== '' && !in_array($value, $field['options'], true)) { $message = 'Choose one of the available options.'; }
        elseif ($type === 'image' && $value !== '' && !isset($media[$value])) { $message = 'Choose an image from the media library.'; }
        if ($message) { $errors['shared-' . $key] = $message; } else { $result[$key] = $value; }
    }
    if ($errors) { throw new ContentValidationException($errors); }
    return $result;
}
