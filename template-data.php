<?php
// Require from the website's StaticPilot/template-data.php. Values remain plain text;
// escape them at the point of output using content_escape().
require_once __DIR__ . '/content-store.php';
require_once __DIR__ . '/urls.php';
require_once __DIR__ . '/media-store.php';
$website_content = content_read();
$company = $website_content['company']['name'];
$company_name = $company;
$email = $website_content['company']['email'];
$address = $website_content['company']['address'];
$address_short = $website_content['company']['address_short'];
$phone = array_values(array_filter(array_map('trim', explode("\n", $website_content['company']['phone']))));
$whatsapp_number = $website_content['company']['whatsapp'];
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
$services = $products = $pages = [];
foreach (['services','products','pages'] as $collection) {
    foreach ($website_content[$collection] as $item) {
        ${$collection}[$item['slug'] . '.php'] = [
            'Name'=>$item['name'], 'Slug'=>$item['slug'], 'Desc'=>$item['description'],
            'custom_fields'=>$item['custom_fields'] ?? [],
            'image_id'=>$item['image_id'] ?? '',
            'URL'=>content_url($collection, $item['slug']),
            'SEO_Title'=>$item['seo_title'] ?: $item['name'], 'SEO_Description'=>$item['seo_description'],
            'SEO'=>['<title>' . content_escape($item['seo_title'] ?: $item['name']) . '</title>', '<meta name="description" content="' . content_escape($item['seo_description']) . '">']
        ];
    }
}
$whatsapp = $whatsapp_number === '' ? '' : '<a href="https://wa.me/' . preg_replace('/\D/', '', $whatsapp_number) . '" target="_blank" rel="noopener">WhatsApp</a>';
$phonenum = !$phone ? '' : '<a href="tel:' . content_escape(preg_replace('/[^+0-9]/', '', $phone[0])) . '">Call us</a>';
