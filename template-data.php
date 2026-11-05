<?php
// Require from the website's StaticPilot/template-data.php. Values remain plain text;
// escape them at the point of output using content_escape().
require_once __DIR__ . '/content-store.php';
require_once __DIR__ . '/urls.php';
$website_content = content_read();
$company = $website_content['company']['name'];
$company_name = $company;
$email = $website_content['company']['email'];
$address = $website_content['company']['address'];
$address_short = $website_content['company']['address_short'];
$phone = array_values(array_filter(array_map('trim', explode("\n", $website_content['company']['phone']))));
$whatsapp_number = $website_content['company']['whatsapp'];
$google_analytics_code = $website_content['company']['google_analytics_code'] ?? '';
$google_analytics = $google_analytics_code;
if (!defined('BASE_URL')) { define('BASE_URL', $website_content['company']['base_url']); }
$currentYear = date('Y');
$footer_credit = '© Copyright ' . $currentYear . ' ' . content_escape($company);
$author = '<meta name="generator" content="StaticPilot">';
foreach (array_keys(content_fields('social')) as $network) { ${$network} = $website_content['social'][$network]; }
$company_custom_fields = $website_content['company']['custom_fields'] ?? [];
$social_custom_fields = $website_content['social']['custom_fields'] ?? [];
$testimonials = $website_content['testimonials'];
$faqs = $website_content['faqs'];
$team = $website_content['team'];
$custom_sections = [];
foreach ($website_content['custom_sections'] as $custom_section_record) {
    $custom_sections[$custom_section_record['slug']] = $custom_section_record;
}
unset($custom_section_record);
$menu = $website_content['menu'];
function content_meta_markup($item, $collection) {
    $title = $item['seo_title'] ?: $item['name'];
    $description = $item['seo_description'] ?? '';
    $canonical = ($item['canonical_url'] ?? '') ?: content_url($collection, $item['slug']);
    $tag = function ($name, $value, $property = false) {
        return '<meta ' . ($property ? 'property' : 'name') . '="' . content_escape($name) . '" content="' . content_escape($value) . '">';
    };
    $primary = ['<title>' . content_escape($title) . '</title>', $tag('description', $description)];
    if (($item['seo_keywords'] ?? '') !== '') { $primary[] = $tag('keywords', $item['seo_keywords']); }
    $primary[] = $tag('robots', ($item['seo_robots'] ?? '') ?: 'index, follow');
    $primary[] = '<link rel="canonical" href="' . content_escape($canonical) . '">';
    $ogTitle = ($item['og_title'] ?? '') ?: $title; $ogDescription = ($item['og_description'] ?? '') ?: $description;
    $ogUrl = ($item['og_url'] ?? '') ?: $canonical;
    $facebook = [$tag('og:type', ($item['og_type'] ?? '') ?: 'website', true), $tag('og:url', $ogUrl, true), $tag('og:title', $ogTitle, true), $tag('og:description', $ogDescription, true)];
    if (($item['og_image'] ?? '') !== '') { $facebook[] = $tag('og:image', $item['og_image'], true); }
    $twitter = [$tag('twitter:card', ($item['twitter_card'] ?? '') ?: 'summary_large_image', true), $tag('twitter:url', ($item['twitter_url'] ?? '') ?: $ogUrl, true), $tag('twitter:title', ($item['twitter_title'] ?? '') ?: $ogTitle, true), $tag('twitter:description', ($item['twitter_description'] ?? '') ?: $ogDescription, true)];
    $twitterImage = ($item['twitter_image'] ?? '') ?: ($item['og_image'] ?? '');
    if ($twitterImage !== '') { $twitter[] = $tag('twitter:image', $twitterImage, true); }
    return [implode("\n", $primary), implode("\n", $facebook), implode("\n", $twitter)];
}
$services = $products = $pages = [];
foreach (['services','products','pages'] as $collection) {
    foreach ($website_content[$collection] as $item) {
        [$primaryMeta, $facebookOg, $twitterCard] = content_meta_markup($item, $collection);
        ${$collection}[$item['slug'] . '.php'] = [
            'Name'=>$item['name'], 'Slug'=>$item['slug'], 'Desc'=>$item['description'],
            'custom_fields'=>$item['custom_fields'] ?? [],
            'URL'=>content_url($collection, $item['slug']),
            'SEO_Title'=>$item['seo_title'] ?: $item['name'], 'SEO_Description'=>$item['seo_description'],
            'Primary_meta'=>$primaryMeta, 'Facebook_og'=>$facebookOg, 'Twitter_card'=>$twitterCard,
            'SEO'=>['<title>' . content_escape($item['seo_title'] ?: $item['name']) . '</title>', '<meta name="description" content="' . content_escape($item['seo_description']) . '">']
        ];
    }
}
$whatsapp = $whatsapp_number === '' ? '' : '<a href="https://wa.me/' . preg_replace('/\D/', '', $whatsapp_number) . '" target="_blank" rel="noopener">WhatsApp</a>';
$phonenum = !$phone ? '' : '<a href="tel:' . content_escape(preg_replace('/[^+0-9]/', '', $phone[0])) . '">Call us</a>';
