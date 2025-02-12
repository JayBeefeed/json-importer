<?php

class Impact_Database {
    public static function get_table_names() {
        global $wpdb;
        $tables = [];
        $manufacturers = self::get_unique_manufacturers();
        foreach ($manufacturers as $manufacturer) {
            $sanitized_manufacturer = absint($manufacturer);
            $tables[] = $wpdb->prefix . "wcfm_true_parents_$sanitized_manufacturer";
        }
        return $tables;
    }

    private static function get_unique_manufacturers() {
        global $wpdb;
        $query = "SELECT DISTINCT manufacturer_id FROM {$wpdb->prefix}wcfm_true_parents_%";
        $results = $wpdb->get_col($wpdb->prepare($query));
        return $results;
    }

    public static function get_table_name_by_manufacturer($manufacturer_id) {
        global $wpdb;
        return $wpdb->prefix . "wcfm_true_parents_" . absint($manufacturer_id);
    }

    public static function get_variations_table_name_by_manufacturer($manufacturer_id) {
        global $wpdb;
        return $wpdb->prefix . "wcfm_variations_" . absint($manufacturer_id);
    }
}