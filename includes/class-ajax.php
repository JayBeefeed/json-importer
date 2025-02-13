<?php

class Impact_Ajax {
    public function __construct() {
        add_action('wp_ajax_impact_process_batch', [$this, 'process_batch']);
        add_action('wp_ajax_impact_get_progress', [$this, 'get_progress']);
        add_action('wp_ajax_impact_write_csv', [$this, 'write_csv']);
        add_action('wp_ajax_impact_clear_data', [$this, 'clear_data']);
    }

    public function process_batch() {
        check_ajax_referer('impact_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $progress_key = isset($_POST['progress_key']) ? sanitize_text_field($_POST['progress_key']) : '';
        $batch_size = 10;

        if (empty($progress_key)) {
            wp_send_json_error(['message' => 'Invalid progress key'], 400);
        }

        $progress = get_transient("impact_progress_$progress_key");
        if (!$progress) {
            $progress = [
                'total' => 0,
                'processed' => 0,
                'errors' => [],
                'current' => '',
            ];
        }

        $json_file = get_option('impact_uploaded_json_file');
        if (empty($json_file)) {
            wp_send_json_error(['message' => 'No JSON file uploaded'], 400);
        }

        $products = json_decode(file_get_contents($json_file), true);
        $total_products = count($products);

        if ($progress['total'] === 0) {
            $progress['total'] = $total_products;
            set_transient("impact_progress_$progress_key", $progress, 3600);
        }

        $start = $progress['processed'];
        $end = min($start + $batch_size, $total_products);

        for ($i = $start; $i < $end; $i++) {
            $product = $products[$i];
            $progress['current'] = $product['Name'];
            set_transient("impact_progress_$progress_key", $progress, 3600);

            try {
                $this->process_product($product);
                $progress['processed']++;
            } catch (Exception $e) {
                $progress['errors'][] = $e->getMessage();
            }
        }

        set_transient("impact_progress_$progress_key", $progress, 3600);

        if ($progress['processed'] >= $progress['total']) {
            delete_transient("impact_progress_$progress_key");
        }

        wp_send_json_success($progress);
    }

    private function process_product($product) {
        global $wpdb;
        $manufacturer_id = absint($product['CatalogId']);
        $sku = $product['CatalogItemId'];

        $table_name = Impact_Database::get_table_name_by_manufacturer($manufacturer_id);
        $variations_table_name = Impact_Database::get_variations_table_name_by_manufacturer($manufacturer_id);

        if ($product['IsParent']) {
            $existing_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE sku = %s AND manufacturer_id = %s", $sku . '-P', $manufacturer_id));

            if ($existing_record) {
                // Update existing record
                $update_data = [
                    'price' => $product['CurrentPrice'],
                    'regular_price' => $product['OriginalPrice'],
                    'stock_status' => $product['StockAvailability'] === 'InStock' ? 'instock' : 'outofstock',
                    'updated_at' => current_time('mysql'),
                ];

                $wpdb->update($table_name, $update_data, ['true_parent_id' => $existing_record->true_parent_id]);
            } else {
                // Insert new record
                $response = $this->call_gemini_api($product, true);
                $insert_data = [
                    'manufacturer_id' => $manufacturer_id,
                    'sku' => $sku . '-P',
                    'name' => $response['trueParent']['name'],
                    'description' => $product['Description'],
                    'manufacturer' => $product['Manufacturer'],
                    'image_url' => $product['ImageUrl'],
                    'gallery_image_urls' => json_encode($product['AdditionalImageUrls']),
                    'status' => 'publish',
                    'comment_status' => 'closed',
                    'visibility' => 'visible',
                    'is_featured' => 'no',
                    'product_type' => 'variable',
                    'button_text' => "Buy Now at {$product['Manufacturer']}",
                    'meta_button_text' => "Buy Now at {$product['Manufacturer']}",
                    'wcev_external_status' => 'TRUE',
                    'wcev_external_add_to_cart_text' => "Buy Now at {$product['Manufacturer']}",
                    'seo_description' => $response['trueParent']['seo_description'],
                    'seo_keywords' => implode(', ', $response['trueParent']['seo_keywords']),
                    'seo_og_article_tags' => implode(', ', $response['trueParent']['tags']),
                    'seo_og_description' => $response['trueParent']['seo_description'],
                    'seo_og_title' => $response['trueParent']['seo_title'],
                    'seo_title' => $response['trueParent']['seo_title'],
                    'seo_twitter_description' => $response['trueParent']['seo_description'],
                    'seo_twitter_title' => $response['trueParent']['seo_title'],
                    'et_primary_category' => $response['trueParent']['et_primary_category'],
                    'tags' => implode(', ', $response['trueParent']['tags']),
                    'llm_attributes_json' => json_encode($response['trueParent']['attributes']),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ];

                $wpdb->insert($table_name, $insert_data);
            }
        } else {
            $parent_sku = $product['ParentSku'];
            $existing_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $variations_table_name WHERE sku = %s AND manufacturer_id = %s", $sku, $manufacturer_id));

            if ($existing_record) {
                // Update existing record
                $update_data = [
                    'price' => $product['CurrentPrice'],
                    'regular_price' => $product['OriginalPrice'],
                    'stock_status' => $product['StockAvailability'] === 'InStock' ? 'instock' : 'outofstock',
                    'updated_at' => current_time('mysql'),
                ];

                $wpdb->update($variations_table_name, $update_data, ['variation_id' => $existing_record->variation_id]);
            } else {
                // Insert new record
                $response = $this->call_gemini_api($product, false);
                $parent_record = $wpdb->get_row($wpdb->prepare("SELECT true_parent_id FROM $table_name WHERE sku = %s AND manufacturer_id = %s", $parent_sku . '-P', $manufacturer_id));
                $insert_data = [
                    'true_parent_id' => $parent_record->true_parent_id,
                    'manufacturer_id' => $manufacturer_id,
                    'sku' => $sku,
                    'parent_sku' => $parent_sku,
                    'name' => $product['Name'],
                    'description' => $product['Description'],
                    'short_description' => $response['variations'][0]['short_description'],
                    'price' => $product['CurrentPrice'],
                    'regular_price' => $product['OriginalPrice'],
                    'color' => $product['Color'],
                    'size' => $product['Size'],
                    'image_url' => $product['ImageUrl'],
                    'button_text' => "Buy Now at {$product['Manufacturer']}",
                    'meta_button_text' => "Buy Now at {$product['Manufacturer']}",
                    'wcev_external_status' => 'TRUE',
                    'wcev_external_add_to_cart_text' => "Buy Now at {$product['Manufacturer']}",
                    'external_url' => $product['Url'],
                    'meta_product_url' => $product['Url'],
                    'wcev_external_sku' => $sku,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ];

                $wpdb->insert($variations_table_name, $insert_data);
            }
        }
    }

    private function call_gemini_api($product, $is_parent) {
        $api_key = get_option('impact_gemini_api_key');
        $headers = [
            'Authorization' => "Bearer $api_key",
            'Content-Type' => 'application/json',
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

        if ($is_parent) {
            $prompt = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Based on the product information provided in the JSON object below, please GENERATE the following fields for a WooCommerce True Parent product:
*   **`trueParent.name`**:  A concise and SEO-friendly True Parent product name. Extract the core product name, removing variation-specific descriptors (e.g., size, firmness, color). Example: \"Leesa Reserve Hybrid Mattress, Twin/Soft\" becomes \"Leesa Reserve Hybrid Mattress\".
*   **`trueParent.seo_description`**: A concise, SEO-optimized product description (1-2 sentences) summarizing the True Parent product.
*   **`trueParent.seo_keywords`**: 5-10 comma-separated SEO keywords relevant to the True Parent product.
*   **`et_primary_category`**: A WooCommerce category path string (e.g., \"Home > Bedroom > Sheets\"). Map the provided `category` and `subcategory` to an existing WooCommerce category path using the provided `product_categories_export.csv` file (see separate document). If no suitable WooCommerce category is found, default to \"Uncategorized > Imported\".
*   **`tax_product_tag`**: 5-10 comma-separated product tags relevant to the True Parent product.
*   **`trueParent.attributes`**:  INFER PRODUCT ATTRIBUTES from the `name` and `description`. For example, if the `name` is \"Leesa Reserve Hybrid Mattress, Twin/Soft\", infer \"Firmness: Soft\" as an attribute. Output these inferred attributes as a JSON object where keys are attribute names (e.g., \"Firmness\") and values are attribute values (e.g., \"Soft\").
**Input JSON (Example - True Parent Product):**
```json
{
  \"name\": \"" . esc_js($product['Name']) . "\",
  \"description\": \"" . esc_js($product['Description']) . "\",
  \"category\": \"" . esc_js($product['Category']) . "\",
  \"subcategory\": \"" . esc_js($product['SubCategory'] ?? '') . "\",
  \"manufacturer\": \"" . esc_js($product['Manufacturer']) . "\"
}
```json
"
                            ]
                        ]
                    ]
                ]
            ];

            $response = wp_remote_post($url, [
                'headers' => $headers,
                'body' => json_encode($prompt),
                'sslverify' => false, // Allow self-signed certificates if needed
            ]);

            if (is_wp_error($response)) {
                throw new Exception('Error calling Gemini API: ' . $response->get_error_message());
            }

            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            return $this->parse_gemini_response($response_body, true);
        } else {
            $prompt = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Based on the product variation information provided in the JSON object below, please GENERATE the following field for a WooCommerce Variation product:
*   **`variations[].short_description`**: A concise, SEO-optimized short description (1-2 sentences) summarizing the specific product variation, highlighting its unique size and color attributes.
**Input JSON (Example - Variation Product):**
```json
{
  \"name\": \"" . esc_js($product['Name']) . "\",
  \"description\": \"" . esc_js($product['Description']) . "\",
  \"size\": \"" . esc_js($product['Size']) . "\",
  \"color\": \"" . esc_js($product['Color']) . "\"
}
```json
"
                            ]
                        ]
                    ]
                ]
            ];

            $response = wp_remote_post($url, [
                'headers' => $headers,
                'body' => json_encode($prompt),
                'sslverify' => false, // Allow self-signed certificates if needed
            ]);

            if (is_wp_error($response)) {
                throw new Exception('Error calling Gemini API: ' . $response->get_error_message());
            }

            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            return $this->parse_gemini_response($response_body, false);
        }
    }

    private function parse_gemini_response($response_body, $is_parent) {
        if ($is_parent) {
            $content = $response_body['candidates'][0]['content']['parts'][0]['text'];
            $lines = explode("\n", $content);
            $result = [];

            foreach ($lines as $line) {
                if (preg_match('/^\*\*\*`([^`]+)`\*\*\*: (.+)$/', $line, $matches)) {
                    $key = trim($matches[1]);
                    $value = trim($matches[2]);

                    switch ($key) {
                        case 'trueParent.name':
                            $result['name'] = $value;
                            break;
                        case 'trueParent.seo_description':
                            $result['seo_description'] = $value;
                            break;
                        case 'trueParent.seo_keywords':
                            $result['seo_keywords'] = explode(',', $value);
                            break;
                        case 'et_primary_category':
                            $result['et_primary_category'] = $value;
                            break;
                        case 'tax_product_tag':
                            $result['tags'] = explode(',', $value);
                            break;
                        case 'trueParent.attributes':
                            $result['attributes'] = json_decode($value, true);
                            break;
                    }
                }
            }

            return [
                'trueParent' => $result
            ];
        } else {
            $content = $response_body['candidates'][0]['content']['parts'][0]['text'];
            $lines = explode("\n", $content);
            $result = [];

            foreach ($lines as $line) {
                if (preg_match('/^\*\*\*`([^`]+)`\*\*\*: (.+)$/', $line, $matches)) {
                    $key = trim($matches[1]);
                    $value = trim($matches[2]);

                    if ($key === 'variations[].short_description') {
                        $result['short_description'] = $value;
                        break;
                    }
                }
            }

            return [
                'variations' => [
                    [
                        'short_description' => $result['short_description'] ?? ''
                    ]
                ]
            ];
        }
    }

    public function get_progress() {
        check_ajax_referer('impact_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $progress_key = isset($_GET['progress_key']) ? sanitize_text_field($_GET['progress_key']) : '';

        if (empty($progress_key)) {
            wp_send_json_error(['message' => 'Invalid progress key'], 400);
        }

        $progress = get_transient("impact_progress_$progress_key");

        if (!$progress) {
            wp_send_json_error(['message' => 'Progress not found'], 404);
        }

        wp_send_json_success($progress);
    }

    public function write_csv() {
        check_ajax_referer('impact_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $csv_file = tempnam(sys_get_temp_dir(), 'impact_csv_') . '.csv';
        $fp = fopen($csv_file, 'w');

        if (!$fp) {
            wp_send_json_error(['message' => 'Failed to create CSV file'], 500);
        }

        global $wpdb;
        $manufacturers = Impact_Database::get_unique_manufacturers();

        // Collect all unique attribute names
        $all_attributes = [];
        foreach ($manufacturers as $manufacturer) {
            $table_name = Impact_Database::get_table_name_by_manufacturer($manufacturer);
            $attributes_query = $wpdb->prepare("SELECT llm_attributes_json FROM $table_name");
            $attributes_results = $wpdb->get_col($attributes_query);

            foreach ($attributes_results as $attributes_json) {
                $attributes = json_decode($attributes_json, true);
                if ($attributes) {
                    $all_attributes = array_merge($all_attributes, array_keys($attributes));
                }
            }
        }

        $all_attributes = array_unique($all_attributes);

        // Define CSV headers
        $headers = [
            'Type',
            'SKU',
            'Parent Sku',
            'Name',
            'Published',
            'Is featured?',
            'Visibility in catalog',
            'Short description',
            'Description',
            'In stock?',
            'Allow customer reviews?',
            'Sale price',
            'Regular price',
            'Size',
            'Color',
            'Category',
            'Tags',
            'Image 1',
            'External URL',
            'Button text',
            'meta:_wcev_external_add_to_cart_text',
            'meta:_wcev_external_sku',
            'meta:_wcev_external_status',
            'meta:_wcev_external_url',
            'Position',
            'Brand'
        ];

        // Add dynamic attribute columns
        foreach ($all_attributes as $attribute) {
            $headers[] = "Attribute: $attribute";
        }

        fputcsv($fp, $headers);

        foreach ($manufacturers as $manufacturer) {
            $table_name = Impact_Database::get_table_name_by_manufacturer($manufacturer);
            $variations_table_name = Impact_Database::get_variations_table_name_by_manufacturer($manufacturer);

            $parents = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name"));
            foreach ($parents as $parent) {
                $attributes = json_decode($parent->llm_attributes_json, true);
                $attribute_columns = [];
                foreach ($attributes as $key => $value) {
                    $attribute_columns["Attribute: $key"] = $value;
                }

                $row = [
                    'Variable',
                    $parent->sku,
                    '',
                    $parent->name,
                    $parent->status === 'publish' ? 'yes' : 'no',
                    $parent->is_featured === 'yes' ? 'yes' : 'no',
                    $parent->visibility,
                    '',
                    $parent->description,
                    $parent->stock_status === 'instock' ? 'yes' : 'no',
                    'no',
                    $parent->price,
                    $parent->regular_price,
                    '',
                    '',
                    $parent->et_primary_category,
                    $parent->tags,
                    $parent->image_url,
                    '',
                    $parent->button_text,
                    $parent->meta_button_text,
                    $parent->wcev_external_sku,
                    $parent->wcev_external_status,
                    '',
                    '',
                    $parent->manufacturer
                ];

                $row = array_merge($row, $attribute_columns);
                fputcsv($fp, $row);
            }

            $variations = $wpdb->get_results($wpdb->prepare("SELECT * FROM $variations_table_name"));
            foreach ($variations as $variation) {
                $row = [
                    'Variation',
                    $variation->sku,
                    $variation->parent_sku,
                    $variation->name,
                    $variation->status === 'publish' ? 'yes' : 'no',
                    $variation->is_featured === 'yes' ? 'yes' : 'no',
                    $variation->visibility,
                    $variation->short_description,
                    $variation->description,
                    $variation->stock_status === 'instock' ? 'yes' : 'no',
                    'no',
                    $variation->price,
                    $variation->regular_price,
                    $variation->size,
                    $variation->color,
                    '',
                    '',
                    $variation->image_url,
                    $variation->external_url,
                    $variation->button_text,
                    $variation->meta_button_text,
                    $variation->wcev_external_sku,
                    $variation->wcev_external_status,
                    $variation->external_url,
                    '',
                    $variation->manufacturer
                ];

                fputcsv($fp, $row);
            }
        }

        fclose($fp);

        update_option('impact_csv_file', $csv_file);

        wp_send_json_success(['csv_file' => $csv_file]);
    }

    public function clear_data() {
        check_ajax_referer('impact_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        global $wpdb;
        $manufacturers = Impact_Database::get_unique_manufacturers();

        foreach ($manufacturers as $manufacturer) {
            $table_name = Impact_Database::get_table_name_by_manufacturer($manufacturer);
            $variations_table_name = Impact_Database::get_variations_table_name_by_manufacturer($manufacturer);

            $wpdb->query("TRUNCATE TABLE $table_name");
            $wpdb->query("TRUNCATE TABLE $variations_table_name");
        }

        wp_send_json_success(['message' => 'Data cleared successfully']);
    }
}

new Impact_Ajax();