<?php
require_once __DIR__ . '/field-schema.php';
$settings = require __DIR__ . '/setup.php';
define('CONTENT_STORAGE', $settings['storage_path']);
unset($settings);

function content_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function content_fields($section) {
    if (strpos($section, 'custom:') === 0) {
        return ['name'=>['Title',180,'text',true], 'description'=>['Description',20000,'textarea',false]];
    }
    $schemas = [
        'custom_sections' => ['name'=>['Section name',180,'text',true], 'slug'=>['Section key',80,'text',true], 'description'=>['Section introduction',20000,'textarea',false]],
        'company' => ['name'=>['Company name',180,'text',true], 'email'=>['Email',254,'email',false], 'phone'=>['Phone numbers (one per line)',1000,'textarea',false], 'whatsapp'=>['WhatsApp number with country code',25,'tel',false], 'address'=>['Full address',2000,'textarea',false], 'address_short'=>['Short address',250,'text',false], 'base_url'=>['Website URL',500,'url',false]],
        'social' => array_fill_keys(['facebook','twitter','instagram','linkedin','youtube'], ['',500,'url',false]),
        'menu' => ['name'=>['Link label',100,'text',true], 'link'=>['Link URL or relative path',500,'text',true]],
        'faqs' => ['name'=>['Question',500,'text',true], 'description'=>['Answer',20000,'textarea',true]],
        'team' => ['name'=>['Name',180,'text',true], 'position'=>['Position',250,'text',false], 'description'=>['Biography',20000,'textarea',false], 'image_id'=>['Photo',100,'image',false]],
        'testimonials' => ['image_id'=>['Photo',100,'image',false], 'name'=>['Name',180,'text',true], 'position'=>['Position',250,'text',false], 'review'=>['Review',20000,'textarea',true]],
    ];
    foreach ($schemas['social'] as $key => &$spec) { $spec[0] = ucfirst($key) . ' URL'; } unset($spec);
    $page = ['name'=>['Name',180,'text',true], 'slug'=>['Page address',180,'text',true], 'description'=>['Description',20000,'textarea',true], 'seo_title'=>['Search title',180,'text',false], 'seo_description'=>['Search description',500,'textarea',false]];
    if (in_array($section, ['services','products'], true)) { $page['image_id'] = ['Image',100,'image',false]; }
    return $schemas[$section] ?? $page;
}
function content_defaults() {
    $data = ['schema_version'=>1];
    foreach (['company','social'] as $section) { $data[$section] = array_fill_keys(array_keys(content_fields($section)), ''); }
    foreach (['services','products','pages','menu','testimonials','custom_sections','faqs','team','media'] as $section) { $data[$section] = []; }
    return $data;
}
function content_read() {
    $path = CONTENT_STORAGE . '/content.json';
    if (!file_exists($path)) { return content_defaults(); }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data) || ($data['schema_version'] ?? null) !== 1) { throw new RuntimeException('Website content is unreadable. Restore a valid content.json backup.'); }
    foreach (['faqs','team','media'] as $newSection) { if (!array_key_exists($newSection, $data)) { $data[$newSection] = []; } if (!is_array($data[$newSection])) { throw new RuntimeException('Invalid content collection.'); } }
    foreach (['services','products','testimonials','team'] as $imageSection) {
        if (isset($data[$imageSection]) && is_array($data[$imageSection])) { foreach ($data[$imageSection] as &$record) { if (is_array($record) && !array_key_exists('image_id', $record)) { $record['image_id'] = ''; } } unset($record); }
    }
    if (!array_key_exists('testimonials', $data)) { $data['testimonials'] = []; }
    if (!array_key_exists('custom_sections', $data)) { $data['custom_sections'] = []; }
    if (!is_array($data['custom_sections'])) { throw new RuntimeException('Website content has invalid custom sections.'); }
    $sectionKeys = [];
    foreach ($data['custom_sections'] as $custom) {
        content_check_record($custom, 'custom_sections');
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $custom['slug']) || isset($sectionKeys[$custom['slug']]) || !isset($custom['items']) || !is_array($custom['items'])) {
            throw new RuntimeException('Website content has an invalid custom section key or entries.');
        }
        $sectionKeys[$custom['slug']] = true;
        foreach ($custom['items'] as $item) { content_check_record($item, 'custom:' . $custom['slug']); }
    }
    foreach (['company','social','services','products','pages','menu','testimonials','faqs','team'] as $section) {
        if (!isset($data[$section]) || !is_array($data[$section])) { throw new RuntimeException('Website content has an invalid section.'); }
        $rows = in_array($section, ['company','social'], true) ? [$data[$section]] : $data[$section];
        foreach ($rows as $row) {
            foreach (content_fields($section) as $field => $spec) {
                if (!is_array($row) || !isset($row[$field]) || !is_string($row[$field])) { throw new RuntimeException('Website content has an invalid field.'); }
            }
            if (array_key_exists('custom_fields', $row)) {
                if (!is_array($row['custom_fields'])) { throw new RuntimeException('Website content has invalid custom fields.'); }
                foreach ($row['custom_fields'] as $name => $value) {
                    if (!is_string($name) || !is_string($value)) { throw new RuntimeException('Website content has an invalid custom field.'); }
                }
            }
        }
    }
    return $data;
}
function content_check_record($row, $section) {
    if (!is_array($row)) { throw new RuntimeException('Website content has an invalid custom record.'); }
    foreach (content_fields($section) as $field => $spec) {
        if (!isset($row[$field]) || !is_string($row[$field])) { throw new RuntimeException('Website content has an invalid custom record field.'); }
    }
    if (array_key_exists('custom_fields', $row)) {
        if (!is_array($row['custom_fields'])) { throw new RuntimeException('Website content has invalid custom fields.'); }
        foreach ($row['custom_fields'] as $name => $value) {
            if (!is_string($name) || !is_string($value)) { throw new RuntimeException('Website content has an invalid custom field.'); }
        }
    }
}
function &content_collection(&$data, $section) {
    if (strpos($section, 'custom:') === 0) {
        $slug = substr($section, 7);
        foreach ($data['custom_sections'] as &$custom) {
            if ($custom['slug'] === $slug) { return $custom['items']; }
        }
        unset($custom);
        throw new RuntimeException('This custom section no longer exists. Reload the page.');
    }
    return $data[$section];
}
function content_revision($data) { return hash('sha256', json_encode($data)); }
function content_custom_fields($input) {
    $names = $input['custom_names'] ?? [];
    $values = $input['custom_values'] ?? [];
    if (!is_array($names) || !is_array($values) || count($names) > 50 || array_keys($names) !== array_keys($values)) {
        throw new RuntimeException('Enter at most 50 custom fields with a name and value.');
    }
    $fields = [];
    foreach ($names as $index => $name) {
        $value = $values[$index];
        if (!is_string($name) || !is_string($value)) { throw new RuntimeException('Invalid custom field.'); }
        $name = trim($name); $value = trim(str_replace("\r\n", "\n", $value));
        if ($name === '' && $value === '') { continue; }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_ -]{0,79}$/D', $name)) {
            throw new RuntimeException('Custom field names must start with a letter and use up to 80 letters, numbers, spaces, hyphens or underscores.');
        }
        if (array_key_exists(strtolower($name), array_change_key_case($fields, CASE_LOWER))) { throw new RuntimeException('Custom field names must be unique.'); }
        if (strlen($value) > 20000) { throw new RuntimeException('Custom field values must be 20,000 characters or fewer.'); }
        $fields[$name] = $value;
    }
    return $fields;
}
function content_validate($section, $input, $data = null) {
    $data = $data ?? content_read();
    $row = []; $errors = [];
    foreach (content_fields($section) as $field => $spec) {
        try {
        $value = $input[$field] ?? '';
        if (!is_string($value)) { throw new RuntimeException('Invalid ' . $spec[0] . '.'); }
        $value = trim(str_replace("\r\n", "\n", $value));
        if (($spec[3] && $value === '') || strlen($value) > $spec[1]) { throw new RuntimeException($spec[0] . ' is required or exceeds its length limit.'); }
        if ($value !== '' && $spec[2] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid email address.'); }
        if ($value !== '' && $spec[2] === 'url' && (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $value))) { throw new RuntimeException($spec[0] . ' must start with https:// or http://.'); }
        if ($field === 'slug' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value)) { throw new RuntimeException($section === 'custom_sections' ? 'Use lowercase letters, numbers and hyphens for the section key, for example why-choose-us.' : 'Use lowercase letters, numbers and hyphens for the page address, for example wedding-photography.'); }
        if ($field === 'link' && !preg_match('~^(https?://[^\s]+|mailto:[^\s]+|tel:[+0-9 -]+|/?[a-zA-Z0-9][a-zA-Z0-9/_.?#=&%-]*|/|\#[a-zA-Z0-9_-]+)$~D', $value)) { throw new RuntimeException('Enter a safe website link, relative path, email or telephone link.'); }
        if (in_array($field, ['phone','whatsapp'], true) && $value !== '' && !preg_match('/^[+0-9(). \r\n-]+$/D', $value)) { throw new RuntimeException('Phone numbers may contain digits, spaces, parentheses, + and hyphens.'); }
        $row[$field] = $value;
        if ($spec[2] === 'image' && $value !== '' && !isset($data['media'][$value])) { throw new RuntimeException('Choose an image from the media library.'); }
        } catch (RuntimeException $ex) { $errors[$field] = $ex->getMessage(); }
    }
    if ($section === 'pages' && isset($row['slug'])) {
        $slug = $row['slug'];
        if (in_array($slug, [strtolower(basename(__DIR__)), 'assets', 'includes', 'services', 'products', 'index'], true) || file_exists(__DIR__ . '/../' . $slug) || file_exists(__DIR__ . '/../' . $slug . '.php')) {
            $errors['slug'] = 'This page address is reserved by a website file or folder. Choose another.';
        }
    }
    try { $row['custom_fields'] = content_custom_fields($input); } catch (RuntimeException $ex) { $errors['custom-fields'] = $ex->getMessage(); }
    try {
        $shared = content_validate_shared(content_definitions($data, $section), $input, $data['media']);
        $row['custom_fields'] = array_merge($row['custom_fields'] ?? [], $shared);
    } catch (ContentValidationException $ex) { $errors = array_merge($errors, $ex->errors); }
    if ($errors) { throw new ContentValidationException($errors); }
    return $row;
}
function content_save($expected, $change) {
    $lock = fopen(CONTENT_STORAGE . '/content.lock', 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) { throw new RuntimeException('Unable to lock content storage.'); }
    try {
        $data = content_read();
        if (!is_string($expected) || !hash_equals(content_revision($data), $expected)) { throw new RuntimeException('Content changed in another window. Copy your edits, reload, then try again.'); }
        $data = $change($data);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temp = tempnam(CONTENT_STORAGE, 'save-');
        if (!$temp) { throw new RuntimeException('Storage is not writable.'); }
        if (file_put_contents($temp, $json, LOCK_EX) === false || !rename($temp, CONTENT_STORAGE . '/content.json')) { @unlink($temp); throw new RuntimeException('Unable to save website content.'); }
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
