<?php
/*
Plugin Name: impact-data-manipulator
Description: Imports JSON product data, leverages the Gemini LLM API for targeted SEO optimization and data transformation, stores processed data in manufacturer-specific custom database tables for efficient incremental updates, and exports data to a WooCommerce-ready CSV file. Supports variable products and variations with incremental update capabilities. Optimized for minimal Gemini API token usage and robust operation. Includes dynamic attribute handling and programmatic category mapping from JSON input.
Version: 1.3.0
Author: [Your Name]
License: GPL-2.0+
WC requires at least: 9.6.1
WC tested up to: 9.6.1
*/

defined('ABSPATH') || exit;

define('IMPACT_DATA_MANIPULATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMPACT_DATA_MANIPULATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once IMPACT_DATA_MANIPULATOR_PLUGIN_DIR . 'includes/class-database.php';
require_once IMPACT_DATA_MANIPULATOR_PLUGIN_DIR . 'includes/class-ajax.php';

class Impact_Data_Manipulator {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_plugin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('init', [$this, 'create_custom_tables']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_plugin_menu() {
        add_menu_page(
            'Impact Data Manipulator',
            'Impact Data Manipulator',
            'manage_options',
            'impact-data-manipulator',
            [$this, 'render_main_page'],
            'dashicons-admin-generic',
            6
        );

        add_submenu_page(
            'impact-data-manipulator',
            'Settings',
            'Settings',
            'manage_options',
            'impact-data-manipulator-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_main_page() {
        include IMPACT_DATA_MANIPULATOR_PLUGIN_DIR . 'admin/main-page.php';
    }

    public function render_settings_page() {
        include IMPACT_DATA_MANIPULATOR_PLUGIN_DIR . 'admin/settings-page.php';
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook === 'toplevel_page_impact-data-manipulator') {
            wp_enqueue_script('impact-admin-js', IMPACT_DATA_MANIPULATOR_PLUGIN_URL . 'admin/js/admin.js', ['jquery'], null, true);
            wp_localize_script('impact-admin-js', 'impactAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('impact_ajax')
            ]);
        }
    }

    public function create_custom_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        foreach (Impact_Database::get_table_names() as $table_name) {
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                true_parent_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT NOT NULL,
                manufacturer_id VARCHAR(255) NOT NULL,
                sku VARCHAR(255) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                description LONGTEXT NOT NULL,
                manufacturer VARCHAR(255) NOT NULL,
                image_url VARCHAR(255) NOT NULL,
                gallery_image_urls TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'publish',
                comment_status VARCHAR(20) NOT NULL DEFAULT 'closed',
                visibility VARCHAR(20) NOT NULL DEFAULT 'visible',
                is_featured VARCHAR(3) NOT NULL DEFAULT 'no',
                product_type VARCHAR(20) NOT NULL DEFAULT 'variable',
                button_text VARCHAR(255) NOT NULL,
                meta_button_text VARCHAR(255) NOT NULL,
                wcev_external_status VARCHAR(10) NOT NULL DEFAULT 'TRUE',
                wcev_external_add_to_cart_text VARCHAR(255) NOT NULL,
                seo_description TEXT NOT NULL,
                seo_keywords TEXT NOT NULL,
                seo_og_article_tags TEXT NOT NULL,
                seo_og_description TEXT NOT NULL,
                seo_og_title VARCHAR(255) NOT NULL,
                seo_title VARCHAR(255) NOT NULL,
                seo_twitter_description TEXT NOT NULL,
                seo_twitter_title VARCHAR(255) NOT NULL,
                et_primary_category VARCHAR(255) NOT NULL,
                tags TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                llm_attributes_json TEXT NOT NULL,
                INDEX (manufacturer_id),
                INDEX (sku)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            $variations_table_name = str_replace('true_parents', 'variations', $table_name);
            $variations_sql = "CREATE TABLE IF NOT EXISTS $variations_table_name (
                variation_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT NOT NULL,
                true_parent_id BIGINT UNSIGNED NOT NULL,
                manufacturer_id VARCHAR(255) NOT NULL,
                sku VARCHAR(255) NOT NULL UNIQUE,
                parent_sku VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description LONGTEXT NOT NULL,
                short_description TEXT NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                regular_price DECIMAL(10, 2) NOT NULL,
                color VARCHAR(255) NOT NULL,
                size VARCHAR(255) NOT NULL,
                image_url VARCHAR(255) NOT NULL,
                visibility VARCHAR(20) NOT NULL DEFAULT 'hidden',
                product_visibility VARCHAR(20) NOT NULL DEFAULT 'hidden',
                stock_status VARCHAR(20) NOT NULL DEFAULT 'instock',
                stock_quantity INT NOT NULL DEFAULT 3,
                button_text VARCHAR(255) NOT NULL,
                meta_button_text VARCHAR(255) NOT NULL,
                wcev_external_status VARCHAR(10) NOT NULL DEFAULT 'TRUE',
                wcev_external_add_to_cart_text VARCHAR(255) NOT NULL,
                external_url VARCHAR(255) NOT NULL,
                meta_product_url VARCHAR(255) NOT NULL,
                wcev_external_sku VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                INDEX (manufacturer_id),
                INDEX (parent_sku),
                FOREIGN KEY (true_parent_id) REFERENCES $table_name(true_parent_id) ON DELETE CASCADE
            ) $charset_collate;";

            dbDelta($variations_sql);
        }
    }

    public function register_settings() {
        register_setting('impact-data-manipulator-settings-group', 'impact_gemini_api_key');
    }
}

new Impact_Data_Manipulator();