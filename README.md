# StaticPilot

A drop-in PHP backend for your own website. One installation manages one website, with no database or packages. Requires PHP 7.4+; the clean URL instructions use Apache 2.4 with mod_rewrite and `.htaccess` overrides enabled.

## Installation

Place this entire project folder inside your website directory:

```text
my-website/
  StaticPilot/           This project: backend, data adapter and protected storage
    index.php           Dashboard
    setup.php           Credentials and backend configuration
    template-data.php   Website content variables
    route.php           Optional clean-URL content resolver
    storage/            Saved content and sessions (protected from HTTP access)
  assets/               Your website assets
  includes/             Your website includes
  index.php             Your website template
  contact.php           Your own pages, if needed
  .htaccess             Your website routing rules
```

There is no bundled public website or sample design. Open `/StaticPilot/` to sign in (locally: http://localhost/StaticPilot/). Existing credentials are in `setup.php`, and existing content is retained in `storage/`. Change installation credentials before client deployment. `website_url` in `setup.php` defaults to `../` for the View website link.

The folder can be renamed; update PHP include paths accordingly. The URL helper derives the public website path from the backend folder's parent. For an Apache alias outside the document root, set Company details → Website URL to the full public website URL.

## Connect your PHP website

In your website's root PHP file:

```php
<?php require_once __DIR__ . '/StaticPilot/template-data.php'; ?>
<h1><?= content_escape($company_name) ?></h1>
<p><?= nl2br(content_escape($address)) ?></p>
```

Or in your website's `includes/data.php`:

```php
<?php
require_once __DIR__ . '/../StaticPilot/template-data.php';
```

Every admin content field has a code-help icon beside its label. Open it to view the PHP example and use the copy icon to copy it. Each section also has an expandable integration example, including collection loops. All values are plain text: escape output with `content_escape()`. Do not include old hardcoded data libraries afterward; they overwrite the saved values.

Available variables include `$company_name` (also `$company`), `$email`, `$phone` (array), `$address`, `$address_short`, `$whatsapp_number`, `BASE_URL`, `$facebook`, `$twitter`, `$instagram`, `$linkedin`, `$youtube`, `$menu`, `$services`, `$products`, and `$pages`. Legacy `$whatsapp`, `$phonenum`, `$footer_credit`, `$author` and `$currentYear` helpers remain available.

```php
<?php foreach ($services as $service): ?>
  <article>
    <h2><a href="<?= content_escape($service['URL']) ?>"><?= content_escape($service['Name']) ?></a></h2>
    <p><?= nl2br(content_escape($service['Desc'])) ?></p>
  </article>
<?php endforeach; ?>
```

Use the same pattern for `$products` or `$pages`. Each item has `Name`, `Slug`, `Desc`, `URL`, `SEO_Title`, `SEO_Description`, and `SEO` (already escaped HTML tags). Array keys remain `slug.php` for compatibility; use **`URL`** when creating links. Raw lowercase records are available in `$website_content`.

## Testimonials and custom fields

Testimonials supports Name, Position, and Review, with editing, deletion, and display ordering. Name and Review are required. Use `$testimonials` in your templates; each record has `name`, `position`, `review`, and optional `custom_fields`.

Every editor (Company details, Social links, Services, Products, Testimonials, Pages, and Navigation) includes **Add custom field**. Enter a unique field name and a plain-text value, then save the form. Fields belong to that section or individual item. You can edit either value or remove a field and save. Each record supports up to 50 fields; names start with a letter and allow letters, numbers, spaces, underscores, and hyphens (80 characters maximum).

Custom fields are available as `$company_custom_fields`, `$social_custom_fields`, or the item's `custom_fields` map. For example, inside a testimonials loop, use `content_escape($testimonial['custom_fields']['Rating'] ?? '')`. Services, products, pages, and navigation also expose this map on each item. Custom fields are plain text and must be escaped when displayed; they do not create PHP variables or appear automatically on your public website. Every custom field has the same PHP code-help and copy controls as built-in fields. Renaming a field updates its snippet immediately. The full developer integration example includes saved and draft custom fields in the appropriate loop, with empty fallbacks for records without that field. Save changes before using the generated code on your website. Existing saved content remains compatible.

## Custom sections

Use **Custom sections → Add Section** to create any reusable content collection. Give it a name, a permanent key (for example `our-purpose` or `why-choose-us`), and an optional introduction. Click **Manage entries** to add entries with a Title, Description, and optional custom fields such as Icon, Subtitle, or Image URL. These extra fields are stored as plain text; this is not an image uploader.

Examples:

- **Our purpose** (`our-purpose`): add Mission, Vision, and Goals as three entries.
- **Why choose us** (`why-choose-us`): add Experience, Quality, Support, and any additional reasons as entries.

You can create multiple independent sections, reorder sections and entries, rename section headings, and edit or delete entries. The section key stays fixed after creation so a heading change does not break template lookups. Deleting a section also deletes its entries; the confirmation shows that explicitly. Existing content files gain an empty custom-sections collection automatically.

Load `template-data.php` as usual. `$custom_sections` is keyed by the permanent section key. Each section contains `name`, `slug`, `description`, `custom_fields`, and `items`. Each item contains `name`, `description`, and `custom_fields`.

```php
<?php require_once __DIR__ . '/StaticPilot/template-data.php'; ?>
<?php $section = $custom_sections['why-choose-us'] ?? []; ?>
<?php if ($section): ?>
<section>
  <h2><?= content_escape($section['name']) ?></h2>
  <p><?= nl2br(content_escape($section['description'])) ?></p>
  <?php foreach ($section['items'] as $item): ?>
    <article>
      <h3><?= content_escape($item['name']) ?></h3>
      <p><?= nl2br(content_escape($item['description'])) ?></p>
      <p><?= content_escape($item['custom_fields']['Subtitle'] ?? '') ?></p>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>
```

Each collection editor generates its own copyable loop using the actual section key and custom field names. The Custom sections manager also provides a loop over all sections in display order. A custom section is reusable template content, not a public page or URL. Place its loop in any homepage, about page, or other PHP template. Escape plain text output; validate URL fields separately before using them as links or image sources.

## Clean website URLs

| Content | URL at a domain root |
| --- | --- |
| Page slug `about` | `/about` |
| Service slug `photography` | `/services/photography` |
| Product slug `camera` | `/products/camera` |
| Your root `contact.php` file | `/contact` |

For a website in `/my-website/`, links automatically include that prefix. Backend URLs stay under `/my-website/StaticPilot/`.

Merge these rules into **your website's root `.htaccess`**, before other catch-all rules. Do not replace the backend's own `.htaccess`; it protects internal PHP files. Adapt these rules if your website already has a router.

```apache
Options -Indexes -MultiViews
DirectoryIndex index.php
RewriteEngine On
# Existing assets, files and the StaticPilot folder keep working.
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
# Optional: open a custom root PHP file without the extension.
RewriteCond %{REQUEST_FILENAME}.php -f
RewriteRule ^([a-z0-9-]+)$ $1.php [END]
# Send CMS URLs and unknown paths to your own shared template.
RewriteRule ^ index.php [END]
```

In your website's shared `index.php`, load the resolver **before any output**:

```php
<?php
require_once __DIR__ . '/StaticPilot/route.php';
// $notFound: render your own 404 view (the HTTP status is already set).
// $item: a matching record, with name, slug, description and SEO fields.
// Otherwise render your homepage.
```

The resolver loads the data adapter and exposes `$type`, `$item`, `$Name`, `$Slug`, `$Desc`, `$base`, and `$notFound`. It renders no HTML and leaves all styling and layout to your website. Ordinary custom PHP pages can load only `template-data.php`.

Use `content_url('pages', 'about')` or `content_url('services', $record['slug'])` to generate links from raw records. Use `$base . 'assets/site.css'` for assets in routed templates. Valid old `?type=pages&slug=about` links redirect to clean URLs; unknown records return 404. Collection adapter records and route records use different keys: `$service['Name']` in the adapter versus `$item['name']` from the resolver.

Real files and folders take precedence over CMS pages. Colliding page slugs and reserved paths are rejected when saving. Adding a PHP file later can override an existing CMS page. Services and products may share a slug because their URL prefixes differ. Navigation links are entered explicitly: use `/about` at a domain root or `/my-website/about` for a subfolder website.

## Operations and verification

The dashboard and sign-in screen share Light, Dark, and Auto appearance controls. Auto follows the device preference; an explicit choice is stored in this browser and applied before the page paints. The controls continue to work for the current page when browser storage is unavailable. Run `node tests/theme.cjs` to verify preference persistence, system changes, and storage fallbacks.

Give PHP write access to `storage/`; Apache must honor its access-denial rule. Back up this directory and never overwrite live content during deployment. The inactive openings archive is retained. Use HTTPS. Sessions expire after 30 minutes idle; login throttling, CSRF checks, atomic saves and revision conflict protection remain enabled.

Run `node tests/smoke.cjs` against the local XAMPP development installation with no other editors active. It temporarily edits content and restores the original content file in a finally block. Tests cover authentication, all editors, validation, conflicts, ordering, editing/deletion, escaped output, template variables, root/subfolder route resolution, missing records, developer hints and HTTP denial of internal files/storage. PHP syntax checks were also run. Your website's rewrite rules must be verified after integration; no public website is installed by this project.
