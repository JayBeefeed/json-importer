=== Impact Data Manipulator ===
Contributors: [Your Name]
Tags: wordpress, plugin, WooCommerce, data manipulation, product import, JSON, Gemini API, SEO optimization
Requires at least: 6.0
Tested up to: 6.0
Stable tag: 1.3.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Imports JSON product data, leverages the Gemini LLM API for targeted SEO optimization and data transformation, stores processed data in manufacturer-specific custom database tables for efficient incremental updates, and exports data to a WooCommerce-ready CSV file. Supports variable products and variations with incremental update capabilities. Optimized for minimal Gemini API token usage and robust operation. Includes dynamic attribute handling and programmatic category mapping from JSON input.

== Description ==

This plugin allows you to import product data from JSON files, optimize the data using the Gemini LLM API, store the processed data in custom database tables, and export it to a CSV file suitable for WooCommerce product import. It handles variable products and variations, implements robust error handling and logging, prioritizes efficient Gemini API usage, and incorporates dynamic attribute handling.

== Installation ==

1. Upload the `impact-data-manipulator.zip` file to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the 'Impact Data Manipulator' menu in the WordPress admin dashboard.

== Configuration ==

1. Go to the 'Settings' submenu under 'Impact Data Manipulator'.
2. Enter your Gemini API Key in the provided field and save changes.

== Usage Instructions ==

1. Upload a JSON file containing product data through the main plugin page.
2. Click the 'Begin Processing' button to start the data processing workflow.
3. Monitor the progress bar and log window for real-time feedback.
4. Once processing is complete, click the 'Write New CSV Data' button to initiate the CSV export process.
5. Click the 'Download CSV' button to download the generated CSV file.
6. Optionally, click the 'Clear Data' button to clean up the processed data for the current manufacturer.

== CSV Column Mapping Explanation ==

The CSV output includes standard columns such as Type, SKU, Parent Sku, Name, Published, etc. Additionally, it includes dynamically generated "Attribute: [Attribute Name]" columns based on the LLM-inferred attributes.

== Rate Limit Warnings ==

The plugin processes data in batches of 10 products to avoid exceeding the Gemini API rate limits. Ensure you stay within the free tier limits.

== Troubleshooting Tips ==

- **API Connection Errors**: Verify that your Gemini API key is correct and that there are no network issues.
- **CSV Download Problems**: Ensure that the CSV file is correctly generated and that there are no file permission issues.
- **Checking Error Logs**: Review the error logs located in `wp-content/impact-logs/` for detailed error information.

== Dynamic Category Mapping Requirement Clarification ==

The categories can be input to the CSV exactly as found in the JSON.

== LLM Attribute Handling in CSV ==

The plugin collects all unique attribute names across products, generates dynamic "Attribute: {name}" columns in CSV headers, and populates each product row with corresponding attribute values.

== Transient Storage for Large Datasets ==

The plugin uses transient storage for large datasets to avoid memory exhaustion.

== Manufacturer ID Sanitization Edge Cases ==

The plugin uses `absint()` to sanitize Manufacturer IDs for use in table names.

== Multi-Site Compatibility ==

The plugin is not compatible with multi-site installations.

== WP-CLI Integration ==

WP-CLI integration is supported.

== Localization Requirements ==

No translations are required.

== WooCommerce Version Compatibility ==

The plugin is compatible with WooCommerce version 9.6.1 or later.

== Error Log Rotation ==

Error logs refresh with each script run.

== CSV Encoding Validation ==

The CSV file is encoded in UTF-8 without BOM.

== API Response Caching ==

API responses are cached to reduce the number of API calls.

== Bulk Delete Operations ==

Tables are not deleted to allow for future use.

== Attribute Conflict Handling ==

The string in the JSON is used for attributes.

== Image URL Validation ==

No validation is required for image URLs.

== Price Format Validation ==

Currency symbols are stripped from prices.

== Stock Status Mapping ==

Stock status is mapped using the value from the JSON.

== CSV File Naming Convention ==

The CSV file includes a timestamp in its name.

== Memory Management ==

Chunked JSON parsing and batched database inserts are implemented to prevent memory exhaustion.

== SSL Verification ==

Self-signed certificates are allowed for API calls to Gemini.
