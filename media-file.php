<?php
require_once __DIR__ . '/content-store.php';
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
$id = $_GET['id'] ?? '';
try { $data = content_read(); } catch (Throwable $ex) { http_response_code(503); exit; }
$image = is_string($id) ? ($data['media'][$id] ?? null) : null;
if (!$image || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $image['file'] ?? '') || !in_array($image['mime'] ?? '', ['image/jpeg','image/png','image/webp'], true)) { http_response_code(404); exit; }
$path = CONTENT_STORAGE . '/media/' . $image['file'];
if (!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: ' . $image['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $image['file'] . '"');
header('Cache-Control: public, max-age=86400');
readfile($path);
