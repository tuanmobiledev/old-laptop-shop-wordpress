<?php
/**
 * Plugin Name: OSCAR Product Specs
 * Description: Sync specs từ descriptions, render JSON-LD additionalProperty, expose /search và /filter REST endpoints cho storefront React.
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

/**
 * REST endpoint /oscar/v1/search — AJAX autocomplete cho storefront search box.
 *
 * GET /wp-json/oscar/v1/search?q=think&limit=8
 *
 * Trả về top N sp match theo name + sku + brand + cpu, có highlight từ khóa.
 */
add_action('rest_api_init', 'oscar_specs_register_search_route');
function oscar_specs_register_search_route(): void
{
    register_rest_route('oscar/v1', '/search', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'oscar_specs_rest_search',
        'permission_callback' => '__return_true',
        'args' => [
            'q'     => ['required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            'limit' => ['required' => false, 'default' => 8, 'sanitize_callback' => 'absint'],
        ],
    ]);

    register_rest_route('oscar/v1', '/filter', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'oscar_specs_rest_filter',
        'permission_callback' => '__return_true',
        'args' => [
            'brand'    => ['required' => false, 'default' => ''],
            'cpu'      => ['required' => false, 'default' => ''],
            'ram'      => ['required' => false, 'default' => ''],
            'ssd'      => ['required' => false, 'default' => ''],
            'screen'   => ['required' => false, 'default' => ''],
            'price_min' => ['required' => false, 'default' => 0, 'sanitize_callback' => 'absint'],
            'price_max' => ['required' => false, 'default' => 0, 'sanitize_callback' => 'absint'],
            'per_page' => ['required' => false, 'default' => 24, 'sanitize_callback' => 'absint'],
            'page'     => ['required' => false, 'default' => 1, 'sanitize_callback' => 'absint'],
            'orderby'  => ['required' => false, 'default' => 'menu_order', 'sanitize_callback' => 'sanitize_key'],
        ],
    ]);

    register_rest_route('oscar/v1', '/facets', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'oscar_specs_rest_facets',
        'permission_callback' => '__return_true',
    ]);
}

function oscar_specs_normalize_query(string $q): string
{
    $q = trim($q);
    if ($q === '') {
        return '';
    }
    // Tách chuỗi thành tokens, giữ cả cụm có dấu
    $tokens = preg_split('/\s+/u', $q);
    $tokens = array_filter(array_map('trim', $tokens), static fn($t) => mb_strlen($t) >= 2);
    return implode(' ', $tokens);
}

function oscar_specs_score_product(WC_Product $product, string $haystack_norm, string $query_norm): int
{
    $sku = strtolower((string) $product->get_sku());
    $name = strtolower((string) $product->get_name());
    $brand = strtolower((string) $product->get_meta('_oscar_brand'));
    $cpu = strtolower((string) $product->get_meta('_oscar_cpu'));

    $score = 0;
    if ($sku && str_contains($query_norm, strtolower($sku))) {
        $score += 100;
    }
    if ($name && str_contains($name, $query_norm)) {
        $score += 50;
    }
    // Từng token
    foreach (preg_split('/\s+/u', $query_norm) as $tok) {
        $tok = trim($tok);
        if ($tok === '') continue;
        if (str_contains($name, $tok))    $score += 10;
        if (str_contains($brand, $tok))   $score += 8;
        if (str_contains($cpu, $tok))     $score += 6;
    }
    // Prefix match
    foreach (preg_split('/\s+/u', $name) as $w) {
        if (mb_strlen($w) >= 4 && str_starts_with($w, $query_norm)) {
            $score += 4;
        }
    }
    return $score;
}

function oscar_specs_rest_search(WP_REST_Request $request): WP_REST_Response
{
    $q_raw = (string) $request->get_param('q');
    $limit = max(1, min(20, (int) $request->get_param('limit')));
    $q = oscar_specs_normalize_query($q_raw);

    if ($q === '') {
        return new WP_REST_Response(['products' => [], 'count' => 0], 200);
    }

    $products = wc_get_products([
        'status'  => 'publish',
        'limit'   => -1,
        'orderby' => 'menu_order',
        'order'   => 'ASC',
    ]);
    $products = array_values(array_filter($products, static fn(WC_Product $p): bool => !$p->get_meta('_oscar_catalog_type')));

    $scored = [];
    $q_norm = strtolower($q);
    foreach ($products as $p) {
        $score = oscar_specs_score_product($p, '', $q_norm);
        if ($score > 0) {
            $scored[] = ['product' => $p, 'score' => $score];
        }
    }
    usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);

    $results = [];
    foreach (array_slice($scored, 0, $limit) as $row) {
        $p = $row['product'];
        $results[] = [
            'id'    => (int) ($p->get_meta('_oscar_source_id') ?: $p->get_id()),
            'wooId' => $p->get_id(),
            'sku'   => $p->get_sku(),
            'name'  => $p->get_name(),
            'slug'  => $p->get_slug(),
            'url'   => get_permalink($p->get_id()),
            'price' => (float) $p->get_price(),
            'oldPrice' => (float) $p->get_regular_price(),
            'brand' => $p->get_meta('_oscar_brand'),
            'cpu'   => $p->get_meta('_oscar_cpu'),
            'ram'   => $p->get_meta('_oscar_ram'),
            'ssd'   => $p->get_meta('_oscar_ssd'),
            'screen' => $p->get_meta('_oscar_screen'),
            'image' => wp_get_attachment_image_url($p->get_image_id(), 'medium') ?: '',
            'score' => $row['score'],
        ];
    }

    return new WP_REST_Response(['products' => $results, 'count' => count($results), 'query' => $q_raw], 200);
}

function oscar_specs_rest_filter(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $per_page = max(1, min(60, (int) $request->get_param('per_page')));
    $page     = max(1, (int) $request->get_param('page'));

    $tax_query = ['relation' => 'AND'];
    foreach (['brand' => 'pa_brand', 'cpu' => 'pa_cpu', 'ram' => 'pa_ram', 'ssd' => 'pa_ssd', 'screen' => 'pa_screen'] as $field => $tax) {
        $vals = (string) $request->get_param($field);
        if ($vals === '') continue;
        $arr = array_filter(array_map('trim', explode(',', $vals)));
        if (!$arr) continue;
        $tax_query[] = [
            'taxonomy' => $tax,
            'field'    => 'slug',
            'terms'    => array_map('sanitize_title', $arr),
            // NOTE: omit 'operator' — WC's QueryClauses hook mishandles it
            // when tax_query has multiple clauses + relation key.
        ];
    }

    $meta_query = [];
    $price_min = (int) $request->get_param('price_min');
    $price_max = (int) $request->get_param('price_max');
    if ($price_min > 0 || $price_max > 0) {
        $price_clause = ['key' => '_price', 'type' => 'NUMERIC'];
        if ($price_min > 0 && $price_max > 0) {
            $price_clause['value'] = [$price_min, $price_max];
            $price_clause['compare'] = 'BETWEEN';
        } elseif ($price_min > 0) {
            $price_clause['value'] = $price_min;
            $price_clause['compare'] = '>=';
        } else {
            $price_clause['value'] = $price_max;
            $price_clause['compare'] = '<=';
        }
        $meta_query[] = $price_clause;
    }

    $orderby = (string) $request->get_param('orderby');
    $order   = 'ASC';
    $wc_orderby = 'menu_order';
    if ($orderby === 'price')     { $wc_orderby = 'price'; $order = 'ASC'; }
    if ($orderby === 'price-desc'){ $wc_orderby = 'price'; $order = 'DESC'; }
    if ($orderby === 'date')      { $wc_orderby = 'date'; $order = 'DESC'; }

    // WC 10.9.4 wc_get_products with paginate=true flattens multiple tax_query JOINs into one,
    // so AND conditions on pa_brand + pa_ram collapse to OR. Use WP_Query directly.
    $wp_args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'no_found_rows'  => false,
    ];

    if ($wc_orderby === 'price') {
        $wp_args['orderby'] = 'meta_value_num';
        $wp_args['meta_key'] = '_price';
        $wp_args['order'] = $order;
    } elseif ($wc_orderby === 'date') {
        $wp_args['orderby'] = 'date';
        $wp_args['order'] = 'DESC';
    } else {
        $wp_args['orderby'] = 'menu_order';
        $wp_args['order'] = 'ASC';
    }

    if (count($tax_query) > 1) {
        $wp_args['tax_query'] = $tax_query;
    }
    if ($meta_query) {
        $wp_args['meta_query'] = $meta_query;
    }

    $wpq = new WP_Query($wp_args);
    $products = [];
    foreach ($wpq->posts as $post) {
        $product = wc_get_product($post->ID);
        if ($product instanceof WC_Product) {
            $products[] = $product;
        }
    }
    $total     = (int) $wpq->found_posts;
    $max_pages = (int) $wpq->max_num_pages;

    $items = array_map(static function (WC_Product $p): array {
        $image_ids = array_values(array_filter(array_merge([$p->get_image_id()], $p->get_gallery_image_ids())));
        $images_out = array_values(array_filter(array_map(
            static fn(int $id) => wp_get_attachment_image_url($id, 'medium'),
            $image_ids
        )));
        return [
            'id'        => (int) ($p->get_meta('_oscar_source_id') ?: $p->get_id()),
            'wooId'     => $p->get_id(),
            'sku'       => $p->get_sku(),
            'name'      => $p->get_name(),
            'slug'      => $p->get_slug(),
            'url'       => get_permalink($p->get_id()),
            'price'     => (float) $p->get_price(),
            'oldPrice'  => (float) $p->get_regular_price(),
            'brand'     => $p->get_meta('_oscar_brand'),
            'cpu'       => $p->get_meta('_oscar_cpu'),
            'gpu'       => $p->get_meta('_oscar_gpu'),
            'ram'       => $p->get_meta('_oscar_ram'),
            'ssd'       => $p->get_meta('_oscar_ssd'),
            'screen'    => $p->get_meta('_oscar_screen'),
            'batteryWh' => (float) $p->get_meta('_oscar_battery_wh'),
            'condition' => ['vi' => $p->get_meta('_oscar_condition_vi'), 'en' => $p->get_meta('_oscar_condition_en')],
            'image'     => $images_out[0] ?? '',
            'images'    => $images_out,
        ];
    }, $products);

    return new WP_REST_Response([
        'products'  => $items,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $per_page,
        'max_pages' => $max_pages,
    ], 200);
}

function oscar_specs_rest_facets(WP_REST_Request $request): WP_REST_Response
{
    $facets = [];
    foreach ([
        'pa_brand'  => 'brand',
        'pa_cpu'    => 'cpu',
        'pa_ram'    => 'ram',
        'pa_ssd'    => 'ssd',
        'pa_screen' => 'screen',
    ] as $tax => $field) {
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
        if (is_wp_error($terms)) {
            $facets[$field] = [];
            continue;
        }
        $facets[$field] = array_map(static fn($t) => [
            'slug'  => $t->slug,
            'name'  => $t->name,
            'count' => $t->count,
        ], $terms);
    }

    // Price range
    global $wpdb;
    $prices = $wpdb->get_col(
        "SELECT CAST(meta_value AS UNSIGNED) FROM {$wpdb->postmeta}
         INNER JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
         WHERE meta_key = '_price' AND meta_value <> '' AND post_status = 'publish' AND post_type = 'product'"
    );
    if ($prices) {
        $facets['price'] = [
            'min' => (int) min($prices),
            'max' => (int) max($prices),
        ];
    }

    return new WP_REST_Response($facets, 200);
}