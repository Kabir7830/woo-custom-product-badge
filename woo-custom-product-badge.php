<?php
/**
 * Plugin Name: Product Badge Plugin
 * Description: Add badges to products like Best Seller, Hot Deal etc
 * Version: 1.0
 * Author: Kabir Behal
 * Text Domain: product-badge
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProductBadgePlugin {
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('product-badge', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->hooks();
    }
    
    public function hooks() {
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        add_action('save_post', array($this, 'save_meta_data'));
        add_action('restrict_manage_posts', array($this, 'add_admin_filter'));
        add_action('parse_query', array($this, 'handle_admin_filter'));
        add_action('woocommerce_single_product_summary', array($this, 'display_badge'), 4);
        add_shortcode('badge_products', array($this, 'render_badge_products'));
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>' . esc_html__('Product Badge Plugin requires WooCommerce to be installed and active.', 'product-badge') . '</p></div>';
    }
    
    public function register_meta_box() {
        add_meta_box(
            'product_badge_meta',
            __('Product Badge', 'product-badge'),
            array($this, 'meta_box_callback'),
            'product',
            'side'
        );
    }
    
    public function meta_box_callback($post) {
        wp_nonce_field('save_badge_meta', 'badge_meta_nonce');
        
        $current_value = get_post_meta($post->ID, '_product_badge', true);
        
        $options = array(
            '' => __('No Badge', 'product-badge'),
            'best_seller' => __('Best Seller', 'product-badge'),
            'hot_deal' => __('Hot Deal', 'product-badge'),
            'new_arrival' => __('New Arrival', 'product-badge'),
        );
        
        echo '<label for="product_badge_select">' . esc_html__('Select Badge:', 'product-badge') . '</label><br>';
        echo '<select id="product_badge_select" name="product_badge" style="width: 100%;">';
        foreach ($options as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($current_value, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }
    
    public function save_meta_data($post_id) {
        if (!isset($_POST['badge_meta_nonce']) || !wp_verify_nonce($_POST['badge_meta_nonce'], 'save_badge_meta')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $badge_value = isset($_POST['product_badge']) ? sanitize_text_field($_POST['product_badge']) : '';
        
        $allowed_values = array('', 'best_seller', 'hot_deal', 'new_arrival');
        if (!in_array($badge_value, $allowed_values, true)) {
            $badge_value = '';
        }
        
        if (!empty($badge_value)) {
            update_post_meta($post_id, '_product_badge', $badge_value);
        } else {
            delete_post_meta($post_id, '_product_badge');
        }
    }
    
    public function add_admin_filter() {
        global $typenow;
        
        if ($typenow === 'product') {
            $current_filter = isset($_GET['badge_filter']) ? sanitize_text_field($_GET['badge_filter']) : '';
            
            $filter_options = array(
                '' => __('All Badges', 'product-badge'),
                'best_seller' => __('Best Seller', 'product-badge'),
                'hot_deal' => __('Hot Deal', 'product-badge'),
                'new_arrival' => __('New Arrival', 'product-badge'),
                'no_badge' => __('No Badge', 'product-badge'),
            );
            
            echo '<select name="badge_filter">';
            foreach ($filter_options as $value => $label) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($value),
                    selected($current_filter, $value, false),
                    esc_html($label)
                );
            }
            echo '</select>';
        }
    }
    
    public function handle_admin_filter($query) {
        global $pagenow;
        
        if ($pagenow === 'edit.php' && 
            isset($_GET['post_type']) && $_GET['post_type'] === 'product' && 
            isset($_GET['badge_filter']) && !empty($_GET['badge_filter'])) {
            
            $filter_value = sanitize_text_field($_GET['badge_filter']);
            
            if ($filter_value === 'no_badge') {
                $query->set('meta_query', array(
                    'relation' => 'OR',
                    array(
                        'key' => '_product_badge',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_product_badge',
                        'value' => '',
                        'compare' => '='
                    )
                ));
            } else {
                $query->set('meta_key', '_product_badge');
                $query->set('meta_value', $filter_value);
            }
        }
    }
    
    public function display_badge() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $badge_key = get_post_meta($product->get_id(), '_product_badge', true);
        
        if (!empty($badge_key)) {
            $badge_text = $this->get_badge_label($badge_key);
            $badge_class = 'product-badge badge-' . esc_attr($badge_key);
            
            printf(
                '<div class="%s" style="background: #ff6b35; color: white; padding: 5px 10px; display: inline-block; margin-bottom: 10px; border-radius: 3px;">%s</div>',
                esc_attr($badge_class),
                esc_html($badge_text)
            );
        }
    }
    
    public function get_badge_label($badge_key) {
        $labels = array(
            'best_seller' => __('Best Seller', 'product-badge'),
            'hot_deal' => __('Hot Deal', 'product-badge'),
            'new_arrival' => __('New Arrival', 'product-badge'),
        );
        
        return isset($labels[$badge_key]) ? $labels[$badge_key] : '';
    }
    
    public function render_badge_products($atts) {
        $attributes = shortcode_atts(array(
            'badge' => '',
            'limit' => 12,
            'columns' => 4,
        ), $atts, 'badge_products');
        
        if (empty($attributes['badge'])) {
            return '';
        }
        
        $badge_key = $this->get_badge_key_from_label($attributes['badge']);
        if (empty($badge_key)) {
            return '';
        }
        
        $query_args = array(
            'post_type' => 'product',
            'posts_per_page' => absint($attributes['limit']),
            'meta_query' => array(
                array(
                    'key' => '_product_badge',
                    'value' => $badge_key,
                    'compare' => '='
                )
            ),
            'post_status' => 'publish',
        );
        
        $products_query = new WP_Query($query_args);
        
        if (!$products_query->have_posts()) {
            return '<p>' . esc_html__('No products found with this badge.', 'product-badge') . '</p>';
        }
        
        $output = '<div class="badge-products-list">';
        
        while ($products_query->have_posts()) {
            $products_query->the_post();
            global $product;
            
            $output .= '<div class="product-item" style="display: inline-block; width: 23%; margin: 1%; vertical-align: top;">';
            $output .= '<a href="' . esc_url(get_permalink()) . '">';
            $output .= '<div class="product-image">';
            $output .= get_the_post_thumbnail(get_the_ID(), 'medium');
            $output .= '</div>';
            $output .= '<h3>' . esc_html(get_the_title()) . '</h3>';
            $output .= '<span class="price">' . wp_kses_post($product->get_price_html()) . '</span>';
            $output .= '</a>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        wp_reset_postdata();
        
        return $output;
    }
    
    public function get_badge_key_from_label($label) {
        $mapping = array(
            'Best Seller' => 'best_seller',
            'Hot Deal' => 'hot_deal',
            'New Arrival' => 'new_arrival',
        );
        
        return isset($mapping[$label]) ? $mapping[$label] : '';
    }
}

new ProductBadgePlugin();