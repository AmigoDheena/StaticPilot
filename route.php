<?php
require_once __DIR__ . '/template-data.php';
header('X-Content-Type-Options: nosniff');
$type = null;
$slug = null;
$item = null;
$base = content_site_path();
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$inSite = substr($requestPath, 0, strlen($base)) === $base;
$route = $inSite ? substr($requestPath, strlen($base)) : '!outside-site';
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
} elseif (!$home && preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~D', $route)) {
    $slug = $route;
    foreach (['pages', 'services', 'products'] as $collection) {
        foreach ($website_content[$collection] as $candidate) {
            if ($candidate['slug'] === $slug) {
                // Flat URLs require one unique slug across pages, services and products.
                if ($item !== null) { $item = null; $type = null; break 2; }
                $item = $candidate; $type = $collection;
            }
        }
    }
}
$notFound = !$home && !$item;
if ($notFound) { http_response_code(404); }
$Name = $item['name'] ?? '';
$Slug = $item['slug'] ?? '';
$Desc = $item['description'] ?? '';
$URL = $item ? content_url($type, $Slug) : '';
$SEO_Title = $item ? ($item['seo_title'] ?: $Name) : 'Page not found';
$SEO_Description = $item['seo_description'] ?? '';
$custom_fields = $item['custom_fields'] ?? [];
[$Primary_meta, $Facebook_og, $Twitter_card] = $item ? content_meta_markup($item, $type) : ['', '', ''];
