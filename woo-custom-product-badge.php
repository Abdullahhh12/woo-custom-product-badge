<?php
/*
Plugin Name: Woo Custom Product Badge
Description: Adds a custom badge to selected WooCommerce products.
Version: 1.0
Author: Abdullah Qureshi
*/

// Add meta box
add_action('add_meta_boxes', 'wcpb_add_custom_badge_meta_box');
function wcpb_add_custom_badge_meta_box() {
    add_meta_box(
        'wcpb_product_badge',
        'Product Badge',
        'wcpb_display_badge_meta_box',
        'product',
        'side',
        'default'
    );
}
function wcpb_display_badge_meta_box($post) {
    wp_nonce_field('wcpb_save_badge_meta', 'wcpb_badge_nonce');
    $selected_badge = get_post_meta($post->ID, '_wcpb_product_badge', true);
    $badges = [
        'none' => 'None',
        'best_seller' => 'Best Seller',
        'limited_edition' => 'Limited Edition',
        'new_arrival' => 'New Arrival',
    ];
    echo '<label for="wcpb_product_badge">Select Badge:</label><br>';
    echo '<select name="wcpb_product_badge" id="wcpb_product_badge" style="width:100%;">';
    foreach ($badges as $key => $label) {
        echo '<option value="' . esc_attr($key) . '" ' . selected($selected_badge, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

// Save selected badge
add_action('save_post_product', 'wcpb_save_product_badge_meta');
function wcpb_save_product_badge_meta($post_id) {
    if (!isset($_POST['wcpb_badge_nonce'])) return;
    if (!wp_verify_nonce($_POST['wcpb_badge_nonce'], 'wcpb_save_badge_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product', $post_id)) return;
    if (isset($_POST['wcpb_product_badge'])) {
        $valid_badges = ['none', 'best_seller', 'limited_edition', 'new_arrival'];
        $selected = sanitize_text_field($_POST['wcpb_product_badge']);
        if (in_array($selected, $valid_badges)) {
            update_post_meta($post_id, '_wcpb_product_badge', $selected);
        }
    }
}

// Show badge on single product page
add_action('woocommerce_single_product_summary', 'wcpb_show_product_badge', 4);
function wcpb_show_product_badge() {
    global $post;
    $badge = get_post_meta($post->ID, '_wcpb_product_badge', true);
    if (!$badge || $badge === 'none') return;
    $badge_labels = [
        'best_seller' => 'Best Seller',
        'limited_edition' => 'Limited Edition',
        'new_arrival' => 'New Arrival',
    ];
    if (!isset($badge_labels[$badge])) return;
    echo '<div class="wcpb-badge">' . esc_html($badge_labels[$badge]) . '</div>';
}

// Enqueue CSS
add_action('wp_enqueue_scripts', 'wcpb_enqueue_styles');
function wcpb_enqueue_styles() {
    wp_enqueue_style('wcpb-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), time());
}

// Admin filter dropdown
add_action('restrict_manage_posts', 'wcpb_add_badge_filter_dropdown');
function wcpb_add_badge_filter_dropdown() {
    global $typenow;
    if ($typenow !== 'product') return;
    $current = isset($_GET['wcpb_badge_filter']) ? $_GET['wcpb_badge_filter'] : '';
    $options = [
        '' => 'All Badges',
        'best_seller' => 'Best Seller',
        'limited_edition' => 'Limited Edition',
        'new_arrival' => 'New Arrival',
    ];
    echo '<select name="wcpb_badge_filter">';
    foreach ($options as $value => $label) {
        printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($current, $value, false), esc_html($label));
    }
    echo '</select>';
}

// Filter query by badge
add_action('pre_get_posts', 'wcpb_filter_products_by_badge');
function wcpb_filter_products_by_badge($query) {
    global $pagenow;
    if (!is_admin() || $pagenow !== 'edit.php' || $query->get('post_type') !== 'product') return;
    if (!empty($_GET['wcpb_badge_filter'])) {
        $query->set('meta_key', '_wcpb_product_badge');
        $query->set('meta_value', sanitize_text_field($_GET['wcpb_badge_filter']));
    }
}

// Shortcode to display badge products
add_shortcode('custom_badge_products', 'wcpb_custom_badge_products_shortcode');
function wcpb_custom_badge_products_shortcode($atts) {
    $atts = shortcode_atts(['badge' => ''], $atts);
    $badge = sanitize_text_field($atts['badge']);
    if (empty($badge)) return '<p>No badge specified.</p>';
    ob_start();
    $args = [
        'post_type' => 'product',
        'posts_per_page' => 8,
        'meta_query' => [
            [
                'key' => '_wcpb_product_badge',
                'value' => $badge,
                'compare' => '=',
            ],
        ],
    ];
    $products = new WP_Query($args);
    if ($products->have_posts()) {
        echo '<ul class="products columns-4">';
        while ($products->have_posts()) {
            $products->the_post();
            wc_get_template_part('content', 'product');
        }
        echo '</ul>';
    } else {
        echo '<p>No products found with badge: ' . esc_html($badge) . '</p>';
    }
    wp_reset_postdata();
    return ob_get_clean();
}
