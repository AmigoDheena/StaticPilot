<?php
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/developer-help.php';
require_once __DIR__ . '/admin-fields.php';
if (!$loggedIn) { header('Location: login.php'); exit; }
$sections = ['company'=>'Company details','services'=>'Services','products'=>'Products','testimonials'=>'Testimonials','faqs'=>'FAQs','team'=>'Team members','pages'=>'Pages','menu'=>'Navigation','custom_sections'=>'Custom sections'];
if (($_GET['section'] ?? '') === 'social') { header('Location: ?section=company#social-links', true, 302); exit; }
$navigationData = content_defaults();
try { $navigationData = content_read(); } catch (Throwable $ex) { if ($ex instanceof ContentValidationException) { $fieldErrors = $ex->errors; } $error = $ex->getMessage(); }
foreach ($navigationData['custom_sections'] as $customSection) { $sections['custom:' . $customSection['slug']] = $customSection['name']; }
if (is_string($_GET['section'] ?? null) && strpos($_GET['section'], 'custom:') === 0 && !isset($sections[$_GET['section']])) {
    http_response_code(404); echo 'Custom section not found. <a href="?section=custom_sections">Return to custom sections</a>.'; exit;
}
$section = is_string($_GET['section'] ?? null) && isset($sections[$_GET['section']]) ? $_GET['section'] : 'company';
$singleton = $section === 'company';
$customEntries = strpos($section, 'custom:') === 0;
$sectionManager = $section === 'custom_sections';
$itemLabel = ['services'=>'Service', 'products'=>'Product', 'testimonials'=>'Testimonial', 'pages'=>'Page', 'menu'=>'Navigation Link', 'custom_sections'=>'Section', 'faqs'=>'FAQ', 'team'=>'Team Member'][$section] ?? 'Entry';
$page = max(1, (int) filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$base = '?section=' . $section . ($query !== '' ? '&q=' . rawurlencode($query) : '');
$editId = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT);
$failed = false; $fieldErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) { throw new RuntimeException('Your session expired. Refresh and try again.'); }
        $action = $_POST['action'] ?? '';
        if ($action === 'logout') { $_SESSION = []; session_destroy(); header('Location: login.php'); exit; }
        content_save($_POST['revision'] ?? null, function ($data) use ($section, $singleton, $action) {
            if ($singleton) {
                if ($action !== 'save') { throw new RuntimeException('Unknown action.'); }
                $data[$section] = content_validate($section, $_POST, $data);
                $socialInput = $_POST['social'] ?? [];
                if (!is_array($socialInput)) { throw new ContentValidationException(['social'=>'Invalid social links.']); }
                try {
                    $social = content_validate('social', $socialInput, $data);
                    $social['custom_fields'] = $data['social']['custom_fields'] ?? [];
                    $data['social'] = $social;
                } catch (ContentValidationException $ex) {
                    $errors = []; foreach ($ex->errors as $key=>$message) { $errors['social-' . $key] = $message; }
                    throw new ContentValidationException($errors);
                }
                return $data;
            }
            $rows = content_collection($data, $section);
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            if ($action !== 'create' && ($id === false || !isset($rows[$id]))) { throw new RuntimeException('Item not found. Reload the page.'); }
            if ($action === 'create' || $action === 'save') {
                $row = content_validate($section, $_POST);
                if ($section === 'custom_sections') {
                    if ($action === 'save' && $row['slug'] !== $rows[$id]['slug']) { throw new ContentValidationException(['slug'=>'The section key cannot be changed after creation because website templates use it.']); }
                    $row['items'] = $action === 'save' ? $rows[$id]['items'] : [];
                    $row['fields'] = content_validate_definitions($_POST, $action === 'save' ? ($rows[$id]['fields'] ?? []) : []);
                }
                if (isset($row['slug'])) {
                    foreach ($rows as $key => $existing) {
                        if (($action === 'create' || $key !== $id) && $existing['slug'] === $row['slug']) { throw new ContentValidationException(['slug'=>$section === 'custom_sections' ? 'This section key is already used. Choose another.' : 'This page address is already used. Choose another.']); }
                    }
                }
                if ($action === 'create') { array_unshift($rows, $row); } else { $rows[$id] = $row; }
            } elseif ($action === 'duplicate') {
                $copy = $rows[$id]; $copy['name'] = substr($copy['name'], 0, 170) . ' (copy)';
                if (isset($copy['slug'])) {
                    $limit = $section === 'custom_sections' ? 65 : 160;
                    $stem = rtrim(substr($copy['slug'], 0, $limit), '-') . '-copy'; $slug = $stem; $number = 2;
                    $used = array_column($rows, 'slug');
                    while (in_array($slug, $used, true) || ($section === 'pages' && (file_exists(__DIR__ . '/../' . $slug) || file_exists(__DIR__ . '/../' . $slug . '.php')))) { $slug = $stem . '-' . $number++; }
                    $copy['slug'] = $slug;
                }
                if ($section === 'custom_sections') { $copy['items'] = []; }
                array_unshift($rows, $copy);
            } elseif ($action === 'delete') { array_splice($rows, $id, 1);
            } elseif ($action === 'up' || $action === 'down') {
                $other = $id + ($action === 'up' ? -1 : 1);
                if (isset($rows[$other])) { [$rows[$id], $rows[$other]] = [$rows[$other], $rows[$id]]; }
            } else { throw new RuntimeException('Unknown action.'); }
            $target =& content_collection($data, $section);
            $target = array_values($rows);
            return $data;
        });
        $_SESSION['notice'] = 'Changes saved. Connected templates can now use this content.';
        header('Location: ' . $base . '&page=' . (in_array($action, ['create','duplicate'], true) ? 1 : $page)); exit;
    } catch (Throwable $ex) { if ($ex instanceof ContentValidationException) { $fieldErrors = $ex->errors; } $error = $ex instanceof RuntimeException ? $ex->getMessage() : 'Unable to save content. Check storage permissions.'; $failed = true; }
}
$data = content_defaults(); $readable = true;
try { $data = content_read(); } catch (Throwable $ex) { $error = $ex->getMessage(); $readable = false; }
$revision = $failed && is_string($_POST['revision'] ?? null) ? $_POST['revision'] : content_revision($data);
$rows = $singleton ? [] : content_collection($data, $section);
$editing = !$singleton && $editId !== false && isset($rows[$editId]);
$draft = $singleton ? $data[$section] : ($editing ? $rows[$editId] : array_fill_keys(array_keys(content_fields($section)), ''));
$definitions = content_definitions($data, $section);
$sharedValues = $draft['custom_fields'] ?? [];
$definitionDraft = $sectionManager ? ($draft['fields'] ?? []) : [];
$customDraft = [];
foreach ($draft['custom_fields'] ?? [] as $name => $value) { if (!in_array($name, array_column($definitions, 'key'), true)) { $customDraft[] = [$name, $value]; } }
if ($failed && in_array($_POST['action'] ?? '', ['create','save'], true)) {
    $sharedValues = is_array($_POST['shared'] ?? null) ? $_POST['shared'] : [];
    if ($sectionManager && isset($_POST['definitions_present'])) {
        $definitionDraft = [];
        foreach (is_array($_POST['field_defs'] ?? null) ? $_POST['field_defs'] : [] as $index=>$definition) {
            if (!is_array($definition)) { continue; }
            $clean = []; foreach (['key','label','type','options','original_key'] as $part) { $clean[$part] = is_string($definition[$part] ?? null) ? $definition[$part] : ''; }
            $clean['required'] = isset($definition['required']); $definitionDraft[$index] = $clean;
        }
    }
    foreach (content_fields($section) as $key => $spec) { $draft[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : ''; }
    $customDraft = [];
    foreach (is_array($_POST['custom_names'] ?? null) ? $_POST['custom_names'] : [] as $index => $name) {
        $value = $_POST['custom_values'][$index] ?? '';
        $customDraft[] = [is_string($name) ? $name : '', is_string($value) ? $value : ''];
    }
}
$perPage = max(1, (int) $account['per_page']);
$integrationNames = array_column($definitions, 'key');
$fieldExamples = []; foreach ($definitions as $definition) { $fieldExamples[$definition['key']] = developer_custom_example($section, $definition['key'], $definition['type']); }
foreach ($rows as $id => $row) {
    if ($editing && $id === $editId) { continue; }
    $integrationNames = array_merge($integrationNames, array_keys($row['custom_fields'] ?? []));
}
$integrationNames = array_values(array_unique($integrationNames));
$visibleRows = $query === '' ? $rows : array_filter($rows, function($row) use ($query) { return stripos(json_encode($row, JSON_UNESCAPED_UNICODE), $query) !== false; });
$totalPages = max(1, (int) ceil(count($visibleRows) / $perPage)); $page = min($page, $totalPages);
function hidden_fields($csrf, $revision, $id = '') {
    foreach (['csrf'=>$csrf,'revision'=>$revision,'id'=>$id] as $name=>$value) { echo '<input type="hidden" name="' . $name . '" value="' . e($value) . '">'; }
}
function custom_field_row($section, $name = '', $value = '') {
    static $index = 0;
    $key = 'custom-' . $index++;
    echo '<div class="custom-field-row"><div class="field-heading"><label for="' . $key . '-name">Field name</label>';
    developer_field_help($section, $key, $name ?: 'custom field', developer_custom_example($section, $name));
    echo '<button type="button" class="remove-custom-field" aria-label="Remove custom field" title="Remove field">' . admin_icon('remove') . '</button></div><input id="' . $key . '-name" name="custom_names[]" value="' . e($name) . '" maxlength="80" placeholder="e.g. Rating"><label for="' . $key . '-value">Value</label><textarea id="' . $key . '-value" name="custom_values[]" rows="2" maxlength="20000">' . e($value) . '</textarea></div>';
}
function admin_icon($name) {
    if (in_array($name, ['faqs','team'], true)) { $name = $name === 'faqs' ? 'testimonials' : 'company'; }
    if (strpos($name, 'custom:') === 0) { $name = 'custom_sections'; }
    $paths = [
        'custom_sections'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17h8m-4-4v8"/>',
        'company'=>'<path d="M4 21V3h12v18M16 9h4v12M2 21h20M8 7h4M8 11h4M8 15h4M9 21v-3h2v3"/>',
        'social'=>'<circle cx="6" cy="12" r="3"/><circle cx="18" cy="5" r="3"/><circle cx="18" cy="19" r="3"/><path d="m9 10 6-3m-6 7 6 3"/>',
        'services'=>'<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v3h4v-3"/>',
        'products'=>'<path d="m12 3 9 5v9l-9 5-9-5V8l9-5ZM3 8l9 5 9-5M12 13v9M8 5l9 5"/>',
        'testimonials'=>'<path d="M21 11a8 8 0 0 1-8 8H7l-4 3V5a2 2 0 0 1 2-2h8a8 8 0 0 1 8 8Z M7 8h9M7 12h6"/>',
        'pages'=>'<path d="M14 2H5v20h14V7l-5-5ZM14 2v6h5M8 12h8M8 16h6"/>',
        'menu'=>'<path d="M9 5h12M9 12h12M9 19h12"/><circle cx="4" cy="5" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="19" r="1"/>',
        'external'=>'<path d="M14 3h7v7M21 3 10 14M10 3H3v18h18v-7"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'remove'=>'<path d="m6 6 12 12M18 6 6 18"/>',
        'logout'=>'<path d="M9 3H3v18h6M8 12h13m-5-5 5 5-5 5"/>',
    ];
    return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($sections[$section]) ?> | StaticPilot</title><meta name="color-scheme" content="light dark"><script src="theme.js"></script><script src="view.js"></script><link rel="stylesheet" href="admin.css"><script src="admin.js" defer></script></head>
<body class="workspace"><a class="skip-link" href="#main-content">Skip to content</a><aside class="sidebar"><a class="brand" href="./"><svg class="brand-symbol" viewBox="0 0 32 32" aria-hidden="true"><path d="m5 25 10-19h12L17 25H5Z"/><path d="m11 16 10 0"/></svg>StaticPilot</a><div class="rail-workspace"><span class="workspace-avatar">W</span><div><strong><?= e($data['company']['name'] ?: 'Your website') ?></strong><small>Website workspace</small></div></div><nav aria-label="Website content">
<?php foreach ($sections as $key=>$label): if (strpos($key, 'custom:') === 0) { continue; } ?><?php if ($key === 'company' || $key === 'services'): ?><span class="nav-group"><?= $key === 'company' ? 'Workspace' : 'Content' ?></span><?php endif ?><a class="nav-item <?= strpos($key, 'custom:') === 0 ? 'nav-child' : '' ?> <?= ($key === $section || ($key === 'custom_sections' && $customEntries)) ? 'active' : '' ?>" href="?section=<?= e($key) ?>" <?= ($key === $section || ($key === 'custom_sections' && $customEntries)) ? 'aria-current="page"' : '' ?>><?= admin_icon($key) ?><span><?= e($label) ?></span><?php if (!in_array($key, ['company','social'], true)): ?><span class="nav-count"><?= count(content_collection($data, $key)) ?></span><?php endif ?></a><?php endforeach ?>
</nav><div class="sidebar-bottom"><p>Website workspace</p><span><?= e($data['company']['name'] ?: 'Set up your company') ?></span><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button name="action" value="logout" class="signout"><?= admin_icon('logout') ?>Sign out</button></form></div></aside>
<header class="workspace-header"><div class="workspace-identity"><span class="workspace-avatar"><?= e(strtoupper(substr($data['company']['name'] ?: 'W', 0, 1))) ?></span><div><strong>Workspace <span class="breadcrumb-divider">/</span> <?= e($sections[$section]) ?></strong></div></div><div class="header-tools"><button type="button" id="view-mode-toggle" aria-pressed="false">Client view</button><div class="theme-switch" role="group" aria-label="Appearance"><button type="button" data-theme-choice="light" aria-pressed="false" title="Light appearance"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2"/></svg><span>Light</span></button><button type="button" data-theme-choice="dark" aria-pressed="false" title="Dark appearance"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15A9 9 0 0 1 9 4a9 9 0 1 0 11 11Z"/></svg><span>Dark</span></button><button type="button" data-theme-choice="system" aria-pressed="false" title="Use device appearance"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg><span>Auto</span></button></div><a class="button website-link" href="<?= e($account['website_url']) ?>" target="_blank" rel="noopener">View website<?= admin_icon('external') ?></a></div></header>
<main id="main-content"><header class="page-header"><div><h1><?= e($sections[$section]) ?></h1><p><?= $sectionManager ? 'Create reusable groups of content for any part of your website.' : ($customEntries ? 'Add entries, set their order, and copy the section loop into your website.' : ($singleton ? 'Keep your website information up to date.' : 'Manage content and its display order on your website.')) ?></p></div><?php if (!$singleton): ?><a class="button primary" href="<?= e($base) ?>#editor"><?= admin_icon('plus') ?>Add <?= e($itemLabel) ?></a><?php endif ?></header>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if ($notice): ?><p class="alert" role="status"><?= e($notice) ?></p><?php endif ?>
<?php if ($customEntries): ?><p class="section-context"><a href="?section=custom_sections">All custom sections</a><span>Section key: <code><?= e(substr($section, 7)) ?></code></span></p><?php endif ?>
<?php if ($sectionManager && !$rows): ?><p class="section-guide">For example: create <strong>Our purpose</strong>, then add Mission, Vision, and Goals as entries. Or create <strong>Why choose us</strong> and add one entry for each reason. Each entry supports extra custom fields.</p><?php endif ?>
<div class="<?= $singleton ? 'single-layout' : 'layout' ?>">
<?php if (!$singleton): ?><section class="list-panel" aria-labelledby="list-title"><div class="panel-heading"><h2 id="list-title">Saved <?= $customEntries ? 'entries' : e(strtolower($sections[$section])) ?></h2><span><?= count($rows) ?> <?= e(count($rows) === 1 ? strtolower($itemLabel) : ($customEntries ? 'entries' : strtolower($sections[$section]))) ?></span></div>
<form method="get" class="collection-search"><input type="hidden" name="section" value="<?= e($section) ?>"><label for="collection-search">Search this collection</label><input type="search" id="collection-search" name="q" value="<?= e($query) ?>"><button>Search</button><?php if ($query !== ''): ?><a href="?section=<?= e($section) ?>">Clear</a><?php endif ?></form><?php if (!$visibleRows): ?><div class="empty-state"><span class="empty-icon"><?= admin_icon($section) ?></span><h3><?= $query !== '' ? 'No matching entries' : 'No ' . ($customEntries ? 'entries' : e(strtolower($sections[$section]))) . ' yet' ?></h3><p>Add your first <?= e(strtolower($itemLabel)) ?> using the form. You can edit or reorder it at any time.</p></div><?php endif ?>
<?php foreach (array_slice($visibleRows, ($page - 1) * $perPage, $perPage, true) as $id=>$row): ?>
<article class="content-item <?= $editing && $editId === $id ? 'selected' : '' ?>"><h3><a href="<?= e($base . '&page=' . $page . '&edit=' . $id) ?>#editor"><?= e($row['name']) ?></a></h3><p class="item-details"><?= e($row['slug'] ?? $row['link'] ?? $row['position'] ?? $row['description'] ?? '') ?><?php if ($sectionManager): ?> · <?= count($row['items']) ?> entries<?php endif ?></p>
<div class="actions"><?php if ($sectionManager): ?><a class="button primary" href="?section=<?= e('custom:' . $row['slug']) ?>">Manage entries</a><?php endif ?><a class="edit-link" href="<?= e($base . '&page=' . $page . '&edit=' . $id) ?>#editor">Edit</a><div class="row-tools"><form method="post"><?php hidden_fields($csrf, content_revision($data), $id); ?><button name="action" value="duplicate"><?= $sectionManager ? 'Duplicate structure' : 'Duplicate' ?></button></form><form method="post" class="reorder"><?php hidden_fields($csrf, content_revision($data), $id); ?><button name="action" value="up" aria-label="Move <?= e($row['name']) ?> up" <?= $id === 0 ? 'disabled' : '' ?>>Up</button><button name="action" value="down" aria-label="Move <?= e($row['name']) ?> down" <?= $id === count($rows)-1 ? 'disabled' : '' ?>>Down</button></form><details class="delete-control"><summary>Delete</summary><form method="post" class="delete-confirm"><?php hidden_fields($csrf, content_revision($data), $id); ?><p>Delete <?= e($row['name']) ?>? <?php if ($sectionManager): ?>This also deletes all <?= count($row['items']) ?> entries in this section. <?php endif ?>This cannot be undone.</p><button class="danger" name="action" value="delete">Delete <?= e($itemLabel) ?></button></form></details></div></div></article>
<?php endforeach ?>
<?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Content pages"><?php if ($page > 1): ?><a href="<?= e($base . '&page=' . ($page-1)) ?>">Previous</a><?php endif ?><span>Page <?= $page ?> of <?= $totalPages ?></span><?php if ($page < $totalPages): ?><a href="<?= e($base . '&page=' . ($page+1)) ?>">Next</a><?php endif ?></nav><?php endif ?>
</section><?php endif ?>
<section id="editor" class="editor-panel" aria-labelledby="editor-title"><div class="panel-heading"><span class="editor-heading-icon"><?= admin_icon($section) ?></span><h2 id="editor-title"><?= $singleton ? e($sections[$section]) : e(($editing ? 'Edit ' : 'Add ') . $itemLabel) ?></h2><span class="editor-mode"><?= $editing ? 'Editing' : ($singleton ? 'Settings' : 'New entry') ?></span></div><div class="editor-body">
<form method="post" action="<?= e($base . '&page=' . $page . ($editing ? '&edit=' . $editId : '')) ?>#editor">
<?php hidden_fields($csrf, $revision, $editing ? $editId : ''); ?><input type="hidden" name="action" value="<?= $singleton || $editing ? 'save' : 'create' ?>">
<div class="form-grid"><?php foreach (content_fields($section) as $key=>$spec): ?><div class="form-field <?= in_array($key, ['description','review','address','base_url','link'], true) ? 'full-width' : '' ?>"><div class="field-heading"><label for="<?= $key ?>"><?= e($spec[0]) ?><?= $spec[3] ? ' (required)' : '' ?></label><?php developer_field_help($section, $key, $spec[0]); ?></div>
<?php if ($spec[2] === 'textarea'): ?><textarea id="<?= $key ?>" name="<?= $key ?>" rows="<?= $key === 'description' ? 5 : 3 ?>" maxlength="<?= $spec[1] ?>" <?= field_error_attributes($key, $fieldErrors) ?> <?= $sectionManager && $editing && $key === 'slug' ? 'readonly' : '' ?> <?= $spec[3] ? 'required' : '' ?>><?= e($draft[$key]) ?></textarea><?php else: ?><input id="<?= $key ?>" name="<?= $key ?>" type="<?= $spec[2] ?>" value="<?= e($draft[$key]) ?>" <?= $sectionManager && $editing && $key === 'slug' ? 'readonly' : '' ?> maxlength="<?= $spec[1] ?>" <?= $spec[3] ? 'required' : '' ?>><?php endif ?>
<?php field_error($key, $fieldErrors); ?><?php if ($key === 'slug' && $sectionManager): ?><p class="field-note">A unique, permanent key such as <code>why-choose-us</code>. Use lowercase letters, numbers, and hyphens. Used in PHP templates; this does not create a page URL.</p><?php elseif ($key === 'slug'): ?><p class="field-note">Use lowercase words separated by hyphens. Public URL: <span id="public-url-preview" data-url-pattern="<?= e(content_url($section, '__SLUG__')) ?>"><?= e(content_url($section, $draft[$key] ?: 'example')) ?></span>. Real files and folders take precedence.</p><?php endif ?>
</div><?php endforeach ?></div>
<?php if ($singleton): ?><fieldset class="custom-fields" id="social-links"><legend>Social media links</legend><?php field_error('social', $fieldErrors); ?><div class="form-grid">
<?php foreach (content_fields('social') as $network=>$spec): $socialValue = $failed ? ($_POST['social'][$network] ?? '') : $data['social'][$network]; $socialValue = is_string($socialValue) ? $socialValue : ''; $socialId = 'social-' . $network; ?>
<div class="form-field"><div class="field-heading"><label for="<?= e($socialId) ?>"><?= e($spec[0]) ?></label><?php developer_field_help('social', $socialId, $spec[0], developer_field_example('social', $network)); ?></div><input type="url" id="<?= e($socialId) ?>" name="social[<?= e($network) ?>]" value="<?= e($socialValue) ?>" maxlength="500" <?= field_error_attributes($socialId, $fieldErrors) ?>><?php field_error($socialId, $fieldErrors); ?></div>
<?php endforeach ?></div></fieldset><?php endif ?>
<?php shared_field_controls($section, $definitions, $sharedValues, $data, $fieldErrors); ?>
<?php if ($sectionManager): ?><fieldset class="custom-fields shared-definition-editor"><legend>Shared entry fields</legend><p class="field-note">Title and Description are included. Define extra fields once for every entry. Labels can change; saved keys and types stay fixed. Removing a definition keeps existing values as custom fields.</p><input type="hidden" name="definitions_present" value="1"><?php field_error('definitions', $fieldErrors); ?><div id="definition-list"><?php foreach ($definitionDraft as $index=>$definition) { definition_row($index, $definition, $fieldErrors); } ?></div><button type="button" id="add-definition">Add shared field</button><span id="definition-status" role="status"></span><template id="definition-template"><?php definition_row('__INDEX__', ['original_key'=>'']); ?></template></fieldset><?php endif ?>
<fieldset class="custom-fields"><legend>Additional custom fields</legend><?php field_error('custom-fields', $fieldErrors); ?><p class="field-note">Extra details for this <?= $singleton ? 'section' : e(strtolower($itemLabel)) ?>. Use unique names and save to apply.</p><div id="custom-field-list" data-custom-source="<?= e(developer_custom_source($section)) ?>" data-saved-field-names="<?= e(json_encode($integrationNames)) ?>" data-field-examples="<?= e(json_encode($fieldExamples)) ?>"><?php foreach ($customDraft as [$name, $value]) { custom_field_row($section, $name, $value); } ?></div><button type="button" id="add-custom-field">Add custom field</button><span id="custom-field-status" class="field-note" role="status"></span><template id="custom-field-template"><?php custom_field_row($section); ?></template><noscript><p>Add a custom field below, then save to add another.</p><?php custom_field_row($section); ?></noscript></fieldset>
<div class="editor-actions"><button class="primary" <?= !$readable ? 'disabled' : '' ?>><?= $singleton || $editing ? 'Save changes' : 'Add ' . e($itemLabel) ?></button><?php if ($editing): ?><a href="<?= e($base) ?>">Cancel</a><?php endif ?></div></form><details class="developer-example"><summary>Developer integration example<?= $singleton ? '' : ' — loop this section' ?></summary><p>Load <code>StaticPilot/template-data.php</code> once in your website template. Field code icons use the variables shown below. All output is escaped as plain text.</p><div class="code-toolbar"><?php developer_copy_button('section-code'); ?></div><pre><code id="section-code" data-example-base="<?= e(developer_section_example($section, [], true, $definitions)) ?>"><?= e(developer_section_example($section, array_merge($integrationNames, array_column($customDraft, 0)), false, $definitions)) ?></code></pre><span class="copy-status" role="status"></span><p class="field-note">Custom fields use their exact names and appear in the example above as you add them. Save your changes before using the code on your website.</p></details></div></section></div></main></body></html>
