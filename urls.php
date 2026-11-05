<?php
// Derive the installation prefix from the document root, not the rewritten URL.
function content_site_path() {
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    if ($documentRoot !== '' && strpos(strtolower($root . '/'), strtolower(rtrim($documentRoot, '/') . '/')) === 0) {
        return '/' . trim(substr($root, strlen(rtrim($documentRoot, '/'))), '/') . (strcasecmp($root, rtrim($documentRoot, '/')) === 0 ? '' : '/');
    }
    // CLI or an aliased installation can configure its public URL in Company details.
    $data = content_read();
    return '/' . (($path = trim(parse_url($data['company']['base_url'], PHP_URL_PATH) ?? '', '/')) !== '' ? $path . '/' : '');
}
function content_url($collection, $slug) {
    if (!in_array($collection, ['pages', 'services', 'products'], true)) { throw new InvalidArgumentException('Unknown collection.'); }
    return content_site_path() . rawurlencode($slug);
}
