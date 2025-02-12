<div class="wrap">
    <h1>Impact Data Manipulator</h1>
    <div id="impact-upload-form">
        <form id="upload-json-form" enctype="multipart/form-data">
            <?php wp_nonce_field('impact_ajax', 'impact_ajax_nonce'); ?>
            <label for="json-file">Upload JSON File:</label>
            <input type="file" id="json-file" name="json-file" accept=".json" required>
            <button type="submit">Upload JSON</button>
        </form>
        <div id="upload-status"></div>
    </div>
    <div id="impact-processing-controls" style="display:none;">
        <button id="begin-processing">Begin Processing</button>
        <div id="progress-bar-container">
            <div id="progress-bar"></div>
        </div>
        <div id="processing-log"></div>
        <div id="processing-status"></div>
    </div>
    <div id="impact-csv-controls" style="display:none;">
        <button id="write-csv">Write New CSV Data</button>
        <div id="csv-status"></div>
    </div>
    <div id="impact-download-controls" style="display:none;">
        <a id="download-csv" href="#" download="products.csv">Download CSV</a>
        <button id="clear-data">Clear Data</button>
    </div>
</div>