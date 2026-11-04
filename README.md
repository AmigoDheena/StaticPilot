# StaticPilot

StaticPilot is a lightweight, file-based PHP CMS and content dashboard for custom websites. It gives website owners a private place to manage company information, social links, services, products, pages, navigation, testimonials, FAQs, team members, and reusable custom sections without requiring a database or a large application framework.

StaticPilot was created for websites that need editable content without the complexity of a traditional enterprise CMS. It keeps the public website in the developer’s control while giving clients a clear dashboard for updating content. A website can keep its own HTML, CSS, JavaScript, PHP templates, and visual design while StaticPilot supplies the content and administration tools.

The dashboard supports reusable custom sections for content that varies from one website to another. A site can define sections such as Mission and Vision, Why Choose Us, FAQs, Features, Team Members, or any other collection of entries. Each custom section can have shared fields, permanent field keys, ordering, search, duplication, custom values, and copyable PHP integration examples.

StaticPilot includes typed fields for text, long text, numbers, URLs, checkboxes, and dropdowns. Field labels can be updated without breaking website templates because the underlying keys remain stable. Social media links are edited inside Company details and saved with the company form. Media uploads, stored media, and image fields are not part of the CMS. Manage website assets in your website project or external hosting. Open Custom sections and choose Manage entries to edit a section; there is no separate Custom collections navigation.

The content adapter exposes saved data to PHP templates as simple variables and arrays. Developers choose exactly how content appears on the public website, while the dashboard handles validation, ordering, search, duplication, custom fields, and integration guidance. Content remains plain text by default so templates can escape and present it safely in their own layouts.

StaticPilot includes optional clean URL routing for pages, services, and products. Existing website files remain under the developer’s control, while CMS content can be resolved through the project’s route helper when clean URLs are needed.

The project is designed to stay small, understandable, and portable. It uses protected local storage, session-based administration, CSRF protection, login throttling, revision checks, atomic content saves, protected internal files, and content validation. Light, dark, and automatic appearance modes are available in the dashboard, along with a client view that hides developer-focused integration controls.

StaticPilot is currently a focused CMS and dashboard rather than a general-purpose application framework. Its long-term direction is to provide a dependable content layer and a small set of stable extension points that developers can adapt to many different website types.

The project is suitable for company websites, portfolios, agencies, service businesses, product showcases, marketing sites, and other custom PHP websites that need editable content without giving up control of the frontend. Before deploying it publicly, change the installation credentials, configure HTTPS, protect the storage directory, back up content, and review the server’s PHP and file permissions.

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