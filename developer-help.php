<?php
require_once __DIR__ . '/urls.php';
function developer_copy_button($id) {
    echo '<button type="button" class="copy-code" data-copy="' . e($id) . '" aria-label="Copy PHP code" title="Copy PHP code"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg></button>';
}
function developer_detail_example($section, $names = []) {
    if (!in_array($section, ['services', 'products'], true)) { throw new InvalidArgumentException('Unknown template collection.'); }
    $backend = basename(__DIR__);
    $code = "<?php\nrequire_once __DIR__ . " . var_export('/' . $backend . '/route.php', true) . ";\n";
    $code .= 'if ($notFound || $type !== ' . var_export($section, true) . ") {\n    http_response_code(404);\n    echo '<h1>Page not found</h1>';\n    exit;\n}\n?>\n";
    $code .= <<<'PHP'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= $Primary_meta ?>
  <?= $Facebook_og ?>
  <?= $Twitter_card ?>
</head>
<body>
<main>
  <h1><?= content_escape($Name) ?></h1>
  <p><?= nl2br(content_escape($Desc)) ?></p>
PHP;
    foreach (array_unique($names) as $name) {
        $code .= "\n  <p><?= content_escape(\$custom_fields[" . var_export($name, true) . "] ?? '') ?></p>";
    }
    return $code . "\n</main>\n</body>\n</html>";
}
function developer_template_rules() {
    return <<<'APACHE'
# Add in the WEBSITE ROOT .htaccess, before existing catch-all rules.
Options -MultiViews
RewriteEngine On
# Existing website files and directories keep working.
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^[a-z0-9]+(?:-[a-z0-9]+)*/?$ content-router.php [END]
APACHE;
}
function developer_router_example() {
    $backend = basename(__DIR__);
    return "<?php\nrequire_once __DIR__ . " . var_export('/' . $backend . '/route.php', true) . ";\n"
        . "if (!\$notFound && \$type === 'services') { require __DIR__ . '/service-template.php'; exit; }\n"
        . "if (!\$notFound && \$type === 'products') { require __DIR__ . '/product-template.php'; exit; }\n"
        . "// Let your existing website router handle normal pages and unknown URLs.\n"
        . "require __DIR__ . '/index.php';\n";
}
function developer_template_panel($section, $rows) {
    $file = $section === 'services' ? 'service-template.php' : 'product-template.php';
    $names = [];
    foreach ($rows as $row) { $names = array_merge($names, array_keys($row['custom_fields'] ?? [])); }
    echo '<details class="developer-example template-guide"><summary>Page template — one file for all ' . e($section) . '</summary>';
    echo '<p>Create <code>' . e($file) . '</code> in your website root, beside the <code>' . e(basename(__DIR__)) . '</code> folder. Copy this code and adapt the HTML to your design. Every saved ' . ($section === 'services' ? 'service' : 'product') . ' uses this same file; no per-item files or manual values are needed.</p>';
    echo '<div class="code-toolbar">'; developer_copy_button('detail-template-code');
    echo '</div><pre><code id="detail-template-code">' . e(developer_detail_example($section, $names)) . '</code></pre>';
    echo '<p>Create <code>content-router.php</code> once in the website root. It selects the service or product template from the flat URL and passes normal pages to your existing <code>index.php</code>.</p>';
    echo '<div class="code-toolbar">'; developer_copy_button('content-router-code');
    echo '</div><pre><code id="content-router-code">' . e(developer_router_example()) . '</code></pre>';
    echo '<p>Add these rules to the website root <code>.htaccess</code> (not the backend folder). They require Apache 2.4 with mod_rewrite and AllowOverride enabled. Keep your existing rules and place these before any catch-all rule. Create both template files when using both collections.</p>';
    echo '<div class="code-toolbar">'; developer_copy_button('template-routing-code');
    echo '</div><pre><code id="template-routing-code">' . e(developer_template_rules()) . '</code></pre><span class="copy-status" role="status"></span>';
    echo '<p>Use the Developer integration example below to loop through listing links. Use root-relative asset URLs in the detail template, for example <code>&lt;?= content_escape(content_site_path()) ?&gt;assets/style.css</code>. Saved changes appear automatically; unknown or deleted items return 404.</p>';
    if ($rows) { echo '<p>Example URL: <a href="' . e(content_url($section, $rows[0]['slug'])) . '" target="_blank" rel="noopener">' . e(content_url($section, $rows[0]['slug'])) . '</a> (available after setup).</p>'; }
    echo '</details>';
}
function developer_field_help($section, $key, $label, $example = null) {
    echo '<details class="code-help"><summary aria-label="PHP example for ' . e($label) . '" aria-controls="help-' . e($key) . '" title="View PHP example"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 6-6 6 6 6m8-12 6 6-6 6M14 3l-4 18"/></svg></summary><div class="code-popover" id="help-' . e($key) . '"><div class="code-toolbar"><strong>PHP example</strong>';
    developer_copy_button('code-' . $key);
    echo '</div><code id="code-' . e($key) . '">' . e($example ?? developer_field_example($section, $key)) . '</code><span class="copy-status" role="status"></span></div></details>';
}
function developer_field_example($section, $key) {
    if (in_array($section, ['faqs','team'], true)) { return "<?= content_escape(\$item['" . $key . "'] ?? '') ?>"; }
    if ($section === 'custom_sections') { return "<?= content_escape(\$custom_section['" . $key . "'] ?? '') ?>"; }
    if (strpos($section, 'custom:') === 0) { return "<?= content_escape(\$item['" . $key . "'] ?? '') ?>"; }
    if ($section === 'company') {
        if ($key === 'google_analytics_code') { return '<?= $google_analytics_code ?>'; }
        $variables = ['name'=>'$company_name', 'email'=>'$email', 'address'=>'$address', 'address_short'=>'$address_short', 'whatsapp'=>'$whatsapp_number', 'base_url'=>'BASE_URL'];
        if ($key === 'phone') { return '<?php foreach ($phone as $number): ?> <span><?= content_escape($number) ?></span> <?php endforeach; ?>'; }
        return '<?php echo content_escape(' . $variables[$key] . '); ?>';
    }
    if ($section === 'social') { return '<?php echo content_escape($' . $key . '); ?>'; }
    if ($section === 'menu') { return '<?php echo content_escape($link[\'' . $key . '\']); ?>'; }
    if ($section === 'testimonials') { return '<?php echo content_escape($testimonial[\'' . $key . '\']); ?>'; }
    if (in_array($key, ['seo_title','seo_description','seo_keywords','seo_robots','canonical_url'], true)) { return '<?= $Primary_meta ?>'; }
    if (strpos($key, 'og_') === 0) { return '<?= $Facebook_og ?>'; }
    if (strpos($key, 'twitter_') === 0) { return '<?= $Twitter_card ?>'; }
    $keys = ['name'=>'Name', 'slug'=>'URL', 'description'=>'Desc', 'seo_title'=>'SEO_Title', 'seo_description'=>'SEO_Description'];
    return '<?php echo content_escape($item[\'' . $keys[$key] . '\']); ?>';
}
function developer_base_section_example($section) {
    $start = "<?php require_once __DIR__ . '/StaticPilot/template-data.php'; ?>\n";
    if (in_array($section, ['faqs','team'], true)) {
        return $start . '<?php foreach ($' . $section . ' as $item): ?>' . "\n" . ($section === 'faqs' ?
            "  <article>\n    <details><summary><?= content_escape(\$item['name']) ?></summary>\n    <p><?= nl2br(content_escape(\$item['description'])) ?></p></details>\n  </article>\n" :
            "  <article>\n    <h3><?= content_escape(\$item['name']) ?></h3>\n    <p><?= content_escape(\$item['position']) ?></p>\n    <p><?= nl2br(content_escape(\$item['description'])) ?></p>\n  </article>\n") . '<?php endforeach; ?>';
    }
    if ($section === 'custom_sections') {
        return $start . <<<'PHP'
<?php foreach ($custom_sections as $custom_section): ?>
<section>
  <h2><?= content_escape($custom_section['name']) ?></h2>
  <p><?= nl2br(content_escape($custom_section['description'])) ?></p>
<?php foreach ($custom_section['items'] as $item): ?>
  <article>
    <h3><?= content_escape($item['name']) ?></h3>
    <p><?= nl2br(content_escape($item['description'])) ?></p>
  </article>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
PHP;
    }
    if (strpos($section, 'custom:') === 0) {
        return $start . '<?php $custom_section = $custom_sections[' . var_export(substr($section, 7), true) . '] ?? []; ?>' . "\n" . <<<'PHP'
<?php if ($custom_section): ?>
<section>
  <h2><?= content_escape($custom_section['name']) ?></h2>
  <p><?= nl2br(content_escape($custom_section['description'])) ?></p>
<?php foreach ($custom_section['items'] as $item): ?>
  <article>
    <h3><?= content_escape($item['name']) ?></h3>
    <p><?= nl2br(content_escape($item['description'])) ?></p>
  </article>
<?php endforeach; ?>
</section>
<?php endif; ?>
PHP;
    }
    if ($section === 'company') {
        return $start . <<<'PHP'
<h1><?= content_escape($company_name) ?></h1>
<p><?= nl2br(content_escape($address)) ?></p>
<?php foreach ($phone as $number): ?>
  <p><?= content_escape($number) ?></p>
<?php endforeach; ?>

<!-- Put this inside your website template's <head>. -->
<?= $google_analytics_code ?>
PHP;
    }
    if ($section === 'social') {
        return $start . <<<'PHP'
<?php foreach (array_keys(content_fields('social')) as $network): ?>
  <?php $url = $website_content['social'][$network]; ?>
  <?php if ($url !== ''): ?>
    <a href="<?= content_escape($url) ?>" target="_blank" rel="noopener">
      <?= content_escape(ucfirst($network)) ?>
    </a>
  <?php endif; ?>
<?php endforeach; ?>
PHP;
    }
    if ($section === 'menu') {
        return $start . <<<'PHP'
<nav aria-label="Main navigation">
<?php foreach ($menu as $link): ?>
  <a href="<?= content_escape($link['link']) ?>"><?= content_escape($link['name']) ?></a>
<?php endforeach; ?>
</nav>
PHP;
    }
    if ($section === 'testimonials') {
        return $start . <<<'PHP'
<?php foreach ($testimonials as $testimonial): ?>
  <figure>
    <blockquote><?= nl2br(content_escape($testimonial['review'])) ?></blockquote>
    <figcaption><?= content_escape($testimonial['name']) ?> — <?= content_escape($testimonial['position']) ?></figcaption>
  </figure>
<?php endforeach; ?>
PHP;
    }
    return $start . '<?php foreach ($' . $section . ' as $item): ?>' . "\n" . <<<'PHP'
  <article>
    <h2><a href="<?= content_escape($item['URL']) ?>"><?= content_escape($item['Name']) ?></a></h2>
    <p><?= nl2br(content_escape($item['Desc'])) ?></p>
  </article>
<?php endforeach; ?>

<!-- On the shared detail template, load StaticPilot/route.php before any HTML.
     It sets $item (raw fields), $Name, $Desc and $notFound, and sends 404s.
     Render these variables in your own website template. -->
PHP;
}
function developer_custom_source($section) {
    if ($section === 'custom_sections') { return "\$custom_section['custom_fields']"; }
    return ['company'=>'$company_custom_fields', 'social'=>'$social_custom_fields', 'menu'=>"\$link['custom_fields']", 'testimonials'=>"\$testimonial['custom_fields']"][$section] ?? "\$item['custom_fields']";
}
function developer_custom_example($section, $name, $type = 'text') {
    if (trim($name) === '') { return 'Enter a field name to generate its PHP example.'; }
    $value = developer_custom_source($section) . '[' . var_export(trim($name), true) . "] ?? ''";
    if ($type === 'checkbox') { return "<?= (" . $value . ") === '1' ? 'Yes' : 'No' ?>"; }
    return "<?= content_escape(" . developer_custom_source($section) . '[' . var_export(trim($name), true) . "] ?? '') ?>";
}
function developer_section_example($section, $names = [], $template = false, $definitions = []) {
    $example = developer_base_section_example($section);
    $lines = [];
    foreach (array_unique(array_map('trim', $names)) as $name) {
        $types = array_column($definitions, 'type', 'key');
        if ($name !== '') { $lines[] = '  <p>' . developer_custom_example($section, $name, $types[$name] ?? 'text') . '</p>'; }
    }
    $custom = $template ? '__CUSTOM_FIELDS__' : implode("\n", $lines);
    if (in_array($section, ['company', 'social'], true)) { return $example . "\n" . $custom; }
    if ($section === 'custom_sections') { return str_replace("<?php foreach (\$custom_section['items'] as \$item): ?>", $custom . "\n<?php foreach (\$custom_section['items'] as \$item): ?>", $example); }
    $anchor = $section === 'testimonials' ? '  </figure>' : ($section === 'menu' ? '<?php endforeach; ?>' : '  </article>');
    return str_replace($anchor, $custom . "\n" . $anchor, $example);
}
