<?php
defined('ABSPATH') || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('oscar-react-storefront'); ?>>
<?php wp_body_open(); ?>
<div id="root"></div>
<noscript>Bạn cần bật JavaScript để sử dụng website Laptop OSCAR Thủ Đức.</noscript>
<?php wp_footer(); ?>
</body>
</html>
