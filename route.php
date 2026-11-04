<?php
require_once __DIR__ . '/template-data.php';
header('X-Content-Type-Options: nosniff');
$type = null;
$slug = null;
$item = null;
$base = content_site_path();
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$route = substr($requestPath, 0, strlen($base)) === $base ? substr($requestPath, strlen($base)) : $requestPath;
$route = trim($route, '/');
$home = $route === '' || $route === 'index.php';
// Retain old bookmarks, but redirect valid query URLs to their clean address.
if ($home && (isset($_GET['type']) || isset($_GET['slug']))) {
    $legacyType = $_GET['type'] ?? null;
    $legacySlug = $_GET['slug'] ?? null;
    if (is_string($legacyType) && in_array($legacyType, ['pages', 'services', 'products'], true) && is_string($legacySlug)) {
        foreach ($website_content[$legacyType] as $candidate) {
            if ($candidate['slug'] === $legacySlug) {
                header('Location: ' . content_url($legacyType, $legacySlug), true, 301);
                exit;
            }
        }
    }
    $home = false;
} elseif (preg_match('~^(services|products)/([a-z0-9]+(?:-[a-z0-9]+)*)$~D', $route, $match)) {
    $type = $match[1]; $slug = $match[2];
} elseif (!$home && preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~D', $route)) {
    $type = 'pages'; $slug = $route;
}
if ($type !== null) {
    foreach ($website_content[$type] as $candidate) {
        if ($candidate['slug'] === $slug) { $item = $candidate; break; }
    }
}
$notFound = !$home && !$item;
if ($notFound) { http_response_code(404); }
$Name = $item['name'] ?? '';
$Slug = $item['slug'] ?? '';
$Desc = $item['description'] ?? '';
