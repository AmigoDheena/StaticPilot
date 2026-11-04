<?php
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/media-store.php';
if (!$loggedIn) { header('Location: login.php'); exit; }
$errors = []; $stagedFile = null; $oldFile = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) { throw new RuntimeException('Your session expired. Refresh and try again.'); }
        content_save($_POST['revision'] ?? null, function($data) use (&$stagedFile, &$oldFile) {
            $action = $_POST['action'] ?? ''; $id = $_POST['media_id'] ?? '';
            if (!in_array($action, ['upload','update','replace'], true)) { throw new RuntimeException('Unknown action.'); }
            if (!is_string($id) || ($action !== 'upload' && !isset($data['media'][$id]))) { throw new RuntimeException('Image not found.'); }
            $errors = [];
            foreach (['title'=>180,'alt'=>500] as $field=>$limit) {
                if (!is_string($_POST[$field] ?? null) || strlen(trim($_POST[$field])) > $limit || ($field === 'title' && trim($_POST[$field]) === '')) { $errors[$field] = ucfirst($field) . ' is required or too long.'; }
            }
            if ($errors) { throw new ContentValidationException($errors); }
            if ($action === 'upload' || $action === 'replace') {
                $image = media_upload($_FILES['file'] ?? null, $stagedFile);
                if ($action === 'replace') { $oldFile = $data['media'][$id]['file']; }
                else { $id = bin2hex(random_bytes(16)); }
            } else { $image = $data['media'][$id]; }
            $image['title'] = trim($_POST['title']); $image['alt'] = trim($_POST['alt']);
            $data['media'][$id] = $image;
            return $data;
        });
        if ($oldFile && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $oldFile)) { @unlink(CONTENT_STORAGE . '/media/' . $oldFile); }
        $_SESSION['notice'] = 'Image saved. Replacements update every place that uses this image.';
        header('Location: media.php'); exit;
    } catch (Throwable $ex) {
        if ($stagedFile) { @unlink($stagedFile); }
        $error = $ex instanceof RuntimeException ? $ex->getMessage() : 'Unable to save image.';
        if ($ex instanceof ContentValidationException) { $errors = $ex->errors; }
    }
}
$data = content_read(); $revision = content_revision($data);
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json');
    $images = [];
    foreach ($data['media'] as $id=>$image) { $images[] = ['id'=>$id,'title'=>$image['title'],'alt'=>$image['alt'],'url'=>content_media_url($id, $data)]; }
    echo json_encode($images); exit;
}
$edit = is_string($_GET['edit'] ?? null) ? $_GET['edit'] : '';
if ($error && is_string($_POST['media_id'] ?? null)) { $edit = $_POST['media_id']; }
$draft = $data['media'][$edit] ?? ['title'=>'','alt'=>''];
if ($error) { foreach (['title','alt'] as $field) { if (is_string($_POST[$field] ?? null)) { $draft[$field] = $_POST[$field]; } } }
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Media library | StaticPilot</title><script src="theme.js"></script><link rel="stylesheet" href="admin.css"></head>
<body class="media-page"><header class="workspace-header"><a href="index.php">← Content workspace</a><div class="theme-switch" role="group" aria-label="Appearance"><button type="button" data-theme-choice="light">Light</button><button type="button" data-theme-choice="dark">Dark</button><button type="button" data-theme-choice="system">Auto</button></div></header><main>
<header class="page-header"><div><h1>Media library</h1><p>Upload, select, and replace images for your website.</p></div><a class="button primary" href="media.php#upload">Upload image</a></header>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif ?><?php if ($notice): ?><p class="alert" role="status"><?= e($notice) ?></p><?php endif ?>
<div class="layout"><section class="list-panel"><div class="panel-heading"><h2>Images</h2><span><?= count($data['media']) ?> images</span></div><form class="collection-search" method="get"><label for="q">Search images</label><input id="q" name="q" value="<?= e($search) ?>" type="search"><button>Search</button></form><div class="media-grid">
<?php $matches = 0; foreach ($data['media'] as $id=>$image): if ($search !== '' && stripos($image['title'] . ' ' . $image['alt'], $search) === false) { continue; } $matches++; ?>
<article><a href="?edit=<?= e($id) ?>#upload"><img src="<?= e(content_media_url($id, $data)) ?>" alt="<?= e($image['alt']) ?>" loading="lazy"><strong><?= e($image['title']) ?></strong></a><p><?= (int)$image['width'] ?> × <?= (int)$image['height'] ?></p><a href="?edit=<?= e($id) ?>#upload">Edit / replace</a></article>
<?php endforeach ?><?php if (!$matches): ?><p class="empty-state">No images found. Upload a JPEG, PNG, or WebP image.</p><?php endif ?></div></section>
<section class="editor-panel" id="upload"><div class="panel-heading"><h2><?= $edit !== '' ? 'Edit image' : 'Upload image' ?></h2></div><div class="editor-body"><form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="revision" value="<?= e($revision) ?>"><input type="hidden" name="media_id" value="<?= e($edit) ?>">
<?php if ($edit !== '' && isset($data['media'][$edit])): ?><img class="image-preview" src="<?= e(content_media_url($edit, $data)) ?>" alt="<?= e($draft['alt']) ?>"><?php endif ?>
<?php foreach (['title'=>'Image title (required)','alt'=>'Alt text'] as $key=>$label): ?><label for="<?= $key ?>"><?= $label ?></label><input id="<?= $key ?>" name="<?= $key ?>" value="<?= e($draft[$key]) ?>" maxlength="<?= $key === 'title' ? 180 : 500 ?>" <?= $key === 'title' ? 'required' : '' ?> <?= isset($errors[$key]) ? 'aria-invalid="true" aria-describedby="error-' . $key . '"' : '' ?>><?php if (isset($errors[$key])): ?><p class="field-error" id="error-<?= $key ?>"><?= e($errors[$key]) ?></p><?php endif ?><?php endforeach ?>
<p class="field-note">Describe the image for people who cannot see it. Leave alt text empty only for decorative images.</p><label for="file"><?= $edit !== '' ? 'Replacement image (optional)' : 'Image file (required)' ?></label><input id="file" name="file" type="file" accept="image/jpeg,image/png,image/webp" <?= $edit === '' ? 'required' : '' ?>><?php if (isset($errors['file'])): ?><p class="field-error"><?= e($errors['file']) ?></p><?php endif ?><p class="field-note">JPEG, PNG, WebP. Maximum 5 MB, 12 megapixels, and 8,000 pixels per side. Replacement affects all entries using this image.</p>
<div class="editor-actions"><?php if ($edit !== ''): ?><button class="primary" name="action" value="update">Save details</button><button name="action" value="replace">Replace image</button><a href="media.php">Cancel</a><?php else: ?><button class="primary" name="action" value="upload">Upload image</button><?php endif ?></div></form></div></section></div></main></body></html>
