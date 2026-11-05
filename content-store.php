<?php
require_once __DIR__ . '/field-schema.php';
$settings = require __DIR__ . '/setup.php';
define('CONTENT_STORAGE', $settings['storage_path']);
unset($settings);

function content_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function content_seo_groups($section) {
    if (!in_array($section, ['pages', 'services'], true)) { return []; }
    return [
        'Primary metadata' => ['seo_title','seo_description','seo_keywords','seo_robots','canonical_url'],
        'Open Graph / Facebook' => ['og_type','og_url','og_title','og_description','og_image'],
        'Twitter card' => ['twitter_card','twitter_url','twitter_title','twitter_description','twitter_image'],
    ];
}

function content_fields($section) {
    if (strpos($section, 'custom:') === 0) {
        return ['name'=>['Title',180,'text',true], 'description'=>['Description',20000,'textarea',false]];
    }
    $schemas = [
        'custom_sections' => ['name'=>['Section name',180,'text',true], 'slug'=>['Section key',80,'text',true], 'description'=>['Section introduction',20000,'textarea',false]],
        'company' => ['name'=>['Company name',180,'text',true], 'email'=>['Email',254,'email',false], 'phone'=>['Phone numbers (one per line)',1000,'textarea',false], 'whatsapp'=>['WhatsApp number with country code',25,'tel',false], 'address'=>['Full address',2000,'textarea',false], 'address_short'=>['Short address',250,'text',false], 'base_url'=>['Website URL',500,'url',false], 'google_analytics_code'=>['Google Analytics JavaScript code',20000,'textarea',false]],
        'social' => array_fill_keys(['facebook','twitter','instagram','linkedin','youtube'], ['',500,'url',false]),
        'menu' => ['name'=>['Link label',100,'text',true], 'link'=>['Link URL or relative path',500,'text',true]],
        'faqs' => ['name'=>['Question',500,'text',true], 'description'=>['Answer',20000,'textarea',true]],
        'team' => ['name'=>['Name',180,'text',true], 'position'=>['Position',250,'text',false], 'description'=>['Biography',20000,'textarea',false]],
        'testimonials' => ['name'=>['Name',180,'text',true], 'position'=>['Position',250,'text',false], 'review'=>['Review',20000,'textarea',true]],
    ];
    foreach ($schemas['social'] as $key => &$spec) { $spec[0] = ucfirst($key) . ' URL'; } unset($spec);
    $page = ['name'=>['Name',180,'text',true], 'slug'=>['Page address',180,'text',true], 'description'=>['Description',20000,'textarea',true], 'seo_title'=>['Title',180,'text',false], 'seo_description'=>['Description',500,'textarea',false]];
    if (in_array($section, ['pages', 'services'], true)) {
        $page += [
            'seo_keywords'=>['Keywords',500,'text',false], 'seo_robots'=>['Robots',40,'text',false], 'canonical_url'=>['Canonical URL',500,'text',false],
            'og_type'=>['Type',40,'text',false], 'og_url'=>['URL',500,'text',false], 'og_title'=>['Title',180,'text',false], 'og_description'=>['Description',500,'textarea',false], 'og_image'=>['Image URL',500,'text',false],
            'twitter_card'=>['Card type',40,'text',false], 'twitter_url'=>['URL',500,'text',false], 'twitter_title'=>['Title',180,'text',false], 'twitter_description'=>['Description',500,'textarea',false], 'twitter_image'=>['Image URL',500,'text',false],
        ];
    }
    return $schemas[$section] ?? $page;
}
function content_defaults() {
    $data = ['schema_version'=>1];
    foreach (['company','social'] as $section) { $data[$section] = array_fill_keys(array_keys(content_fields($section)), ''); }
    foreach (['services','products','pages','menu','testimonials','custom_sections','faqs','team'] as $section) { $data[$section] = []; }
    return $data;
}
function content_read() {
    $path = CONTENT_STORAGE . '/content.json';
    if (!file_exists($path)) { return content_defaults(); }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data) || ($data['schema_version'] ?? null) !== 1) { throw new RuntimeException('Website content is unreadable. Restore a valid content.json backup.'); }
    if (isset($data['company']) && is_array($data['company']) && !array_key_exists('google_analytics_code', $data['company'])) { $data['company']['google_analytics_code'] = ''; }
    foreach (['faqs','team'] as $newSection) { if (!array_key_exists($newSection, $data)) { $data[$newSection] = []; } if (!is_array($data[$newSection])) { throw new RuntimeException('Invalid content collection.'); } }
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
    foreach (['pages','services'] as $seoSection) {
        if (!isset($data[$seoSection]) || !is_array($data[$seoSection])) { continue; }
        foreach ($data[$seoSection] as &$seoRow) {
            foreach (content_fields($seoSection) as $field=>$spec) { if (!array_key_exists($field, $seoRow)) { $seoRow[$field] = ''; } }
        }
        unset($seoRow);
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
        if (in_array($field, ['canonical_url','og_url','og_image','twitter_url','twitter_image'], true) && $value !== '' && !preg_match('~^(https?://[^\\s]+|/[a-zA-Z0-9][a-zA-Z0-9/_.?=&%#-]*|[a-zA-Z0-9][a-zA-Z0-9/_.?=&%#-]*)$~D', $value)) { throw new RuntimeException($spec[0] . ' must be an absolute or website-relative URL.'); }
        if ($field === 'seo_robots' && $value !== '' && !preg_match('/^(?:index|noindex), ?(?:follow|nofollow)$/D', $value)) { throw new RuntimeException('Use index, follow; index, nofollow; noindex, follow; or noindex, nofollow.'); }
        if ($field === 'og_type' && $value !== '' && !preg_match('/^[a-z][a-z0-9._:-]{0,39}$/D', $value)) { throw new RuntimeException('Enter a valid Open Graph type such as website or article.'); }
        if ($field === 'twitter_card' && $value !== '' && !in_array($value, ['summary','summary_large_image','app','player'], true)) { throw new RuntimeException('Use summary, summary_large_image, app, or player.'); }
        if ($field === 'link' && !preg_match('~^(https?://[^\s]+|mailto:[^\s]+|tel:[+0-9 -]+|/?[a-zA-Z0-9][a-zA-Z0-9/_.?#=&%-]*|/|\#[a-zA-Z0-9_-]+)$~D', $value)) { throw new RuntimeException('Enter a safe website link, relative path, email or telephone link.'); }
        if (in_array($field, ['phone','whatsapp'], true) && $value !== '' && !preg_match('/^[+0-9(). \r\n-]+$/D', $value)) { throw new RuntimeException('Phone numbers may contain digits, spaces, parentheses, + and hyphens.'); }
        $row[$field] = $value;
        } catch (RuntimeException $ex) { $errors[$field] = $ex->getMessage(); }
    }
    if (in_array($section, ['pages', 'services', 'products'], true) && isset($row['slug'])) {
        $slug = $row['slug'];
        if (in_array($slug, [strtolower(basename(__DIR__)), 'assets', 'includes', 'services', 'products', 'index'], true) || file_exists(__DIR__ . '/../' . $slug) || file_exists(__DIR__ . '/../' . $slug . '.php')) {
            $errors['slug'] = 'This page address is reserved by a website file or folder. Choose another.';
        }
        foreach (['pages', 'services', 'products'] as $collection) {
            if ($collection === $section) { continue; }
            foreach ($data[$collection] as $existing) {
                if ($existing['slug'] === $slug) { $errors['slug'] = 'This page address is already used by another page, service or product. Choose another.'; break 2; }
            }
        }
    }
    try { $row['custom_fields'] = content_custom_fields($input); } catch (RuntimeException $ex) { $errors['custom-fields'] = $ex->getMessage(); }
    try {
        $shared = content_validate_shared(content_definitions($data, $section), $input);
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
