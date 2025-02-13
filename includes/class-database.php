<?php

class Impact_Database {
    public static function get_table_names() {
        global $wpdb;
        // **IMPORTANT:  `get_table_names()` needs to be reviewed and likely corrected as well.**
        // The current implementation based on `get_unique_manufacturers()` might not be correct
        // after we've changed `get_unique_manufacturers()` to no longer discover tables dynamically.
        // For now, I'm leaving this as is, but we might need to adjust `get_table_names()` too,
        // depending on how the plugin is *actually* intended to determine table names.

        // **For now, I'm returning an empty array to prevent errors.
        // You will need to RE-EVALUATE how table names are determined.**
        return [];

        /* **ORIGINAL (POTENTIALLY INCORRECT) IMPLEMENTATION - DO NOT USE THIS IF YOU ARE USING THE CORRECTED `get_unique_manufacturers()` **
        $tables = [];
        $manufacturers = self::get_unique_manufacturers();
        foreach ($manufacturers as $manufacturer) {
            $sanitized_manufacturer = absint($manufacturer);
            $tables[] = $wpdb->prefix . "wcfm_true_parents_$sanitized_manufacturer";
        }
        return $tables;
        */
    }

    public static function get_unique_manufacturers() {
        global $wpdb;
        $wcfm_table_name = self::get_table_name('wcfm_true_parents'); // Get base table name

        // **REMOVED the problematic table suffix logic:**
        // $table_suffix = apply_filters('wcfm_true_parent_table_suffix', '%');
        // $sql = "SELECT DISTINCT manufacturer_id FROM {$wcfm_table_name}_{$table_suffix}";

        // **Corrected SQL query using the base table name directly:**
        // **IMPORTANT:  This assumes you have a table named `wp_impact_wcfm_true_parents` (with your prefix).**
        // **If your table name is DIFFERENT, you MUST adjust `self::get_table_name('wcfm_true_parents')` accordingly.**
        $sql = "SELECT DISTINCT manufacturer_id FROM {$wcfm_table_name}";


        $manufacturers = $wpdb->get_results($sql, ARRAY_A);

        if (is_wp_error($manufacturers)) {
            // Handle error (log it, return empty array, etc.)
            error_log('Database error in get_unique_manufacturers: ' . $manufacturers->get_error_message());
            return []; // Return empty array on error
        }

        return $manufacturers;
    }

    public static function get_table_name_by_manufacturer($manufacturer_id) {
        global $wpdb;
        return $wpdb->prefix . "wcfm_true_parents_" . absint($manufacturer_id);
    }

    public static function get_variations_table_name_by_manufacturer($manufacturer_id) {
        global $wpdb;
        return $wpdb->prefix . "wcfm_variations_" . absint($manufacturer_id);
    }

    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . "impact_" . $table;
    }
}