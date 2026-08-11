<?php
/**
 * Plugin Name: OSCAR Product Specs
 * Description: Sync specs từ descriptions, render JSON-LD additionalProperty, render [oscar_specs] shortcode.
 * Author: OSCAR Thủ Đức
 */

defined('ABSPATH') || exit;

/**
 * Spec fields mapping: meta_key => JSON-LD propertyName + label tiếng Việt
 */
function oscar_specs_fields(): array
{
    return [
        '_oscar_brand'           => ['name' => 'Thương hiệu',     'group' => 'brand'],
        '_oscar_cpu'             => ['name' => 'CPU',             'group' => 'cpu'],
        '_oscar_gpu'             => ['name' => 'Card đồ họa',     'group' => 'gpu'],
        '_oscar_ram'             => ['name' => 'RAM',             'group' => 'ram'],
        '_oscar_ssd'             => ['name' => 'Ổ cứng',          'group' => 'storage'],
        '_oscar_screen'          => ['name' => 'Màn hình',        'group' => 'display'],
        '_oscar_battery_wh'      => ['name' => 'Pin (Wh)',        'group' => 'battery'],
        '_oscar_condition_vi'    => ['name' => 'Tình trạng',      'group' => 'condition'],
        '_oscar_warranty_months' => ['name' => 'Bảo hành (tháng)', 'group' => 'warranty'],
    ];
}

/**
 * JSON-LD additionalProperty cho Product schema — output specs có cấu trúc
 * để Google/Máy tìm kiếm đọc được cấu hình laptop.
 *
 * Hooks vào oscar_seo_product_jsonld filter, output trước </script>.
 */
add_filter('oscar_seo_product_jsonld', 'oscar_specs_enrich_jsonld', 10, 2);
function oscar_specs_enrich_jsonld(array $payload, ?WC_Product $product): array
{
    if (!$product || empty($payload)) {
        return $payload;
    }

    $properties = [];
    foreach (oscar_specs_fields() as $meta_key => $info) {
        $value = $product->get_meta($meta_key);
        if ($value === '' || $value === null) {
            continue;
        }
        if ($meta_key === '_oscar_battery_wh') {
            $value = $value . ' Wh';
        } elseif ($meta_key === '_oscar_warranty_months') {
            $value = $value . ' tháng';
        }
        $properties[] = [
            '@type'      => 'PropertyValue',
            'name'       => $info['name'],
            'value'      => (string) $value,
            'valueReference' => ['@type' => 'PropertyValue', 'name' => $info['group']],
        ];
    }

    if ($properties) {
        $payload['additionalProperty'] = $properties;
    }
    return $payload;
}

/**
 * Shortcode [oscar_specs] — render specs table cho single product page.
 * React bundle có thể đọc nội dung này hoặc dùng REST API.
 */
add_shortcode('oscar_specs', 'oscar_specs_shortcode');
function oscar_specs_shortcode(): string
{
    if (!function_exists('is_product') || !is_product()) {
        return '';
    }
    global $product;
    if (!$product instanceof WC_Product) {
        $product = wc_get_product(get_the_ID());
    }
    if (!$product) {
        return '';
    }

    $rows = [];
    foreach (oscar_specs_fields() as $meta_key => $info) {
        $value = $product->get_meta($meta_key);
        if ($value === '' || $value === null) {
            continue;
        }
        if ($meta_key === '_oscar_battery_wh') {
            $value = $value . ' Wh';
        } elseif ($meta_key === '_oscar_warranty_months') {
            $value = $value . ' tháng';
        }
        $rows[] = '<tr><th>' . esc_html($info['name']) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
    }

    if (!$rows) {
        return '';
    }

    return '<table class="oscar-specs"><tbody>' . implode('', $rows) . '</tbody></table>';
}