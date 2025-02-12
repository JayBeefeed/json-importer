jQuery(document).ready(function($) {
    let progressKey = '';

    $('#upload-json-form').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        $.ajax({
            url: impactAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#upload-status').text('CSV Received');
                    $('#impact-upload-form').hide();
                    $('#impact-processing-controls').show();
                } else {
                    $('#upload-status').text('Error uploading file: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                $('#upload-status').text('Error uploading file: ' + error);
            }
        });
    });

    $('#begin-processing').on('click', function() {
        progressKey = 'impact_' + Math.random().toString(36).substr(2, 12);
        processBatch();
    });

    function processBatch() {
        $.ajax({
            url: impactAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'impact_process_batch',
                nonce: impactAjax.nonce,
                progress_key: progressKey
            },
            success: function(response) {
                if (response.success) {
                    updateProgressBar(response.data.processed, response.data.total);
                    updateLog(response.data.current, response.data.errors);
                    if (response.data.processed >= response.data.total) {
                        $('#processing-status').text('Data Processed');
                        $('#impact-processing-controls').hide();
                        $('#impact-csv-controls').show();
                    } else {
                        setTimeout(processBatch, 5000);
                    }
                } else {
                    updateLog('', [response.data.message]);
                }
            },
            error: function(xhr, status, error) {
                updateLog('', ['Error processing batch: ' + error]);
            }
        });
    }

    function updateProgressBar(processed, total) {
        const percentage = (processed / total) * 100;
        $('#progress-bar').css('width', percentage + '%').text(percentage.toFixed(2) + '%');
    }

    function updateLog(current, errors) {
        const logDiv = $('#processing-log');
        if (current) {
            logDiv.append('<p>Processing: ' + current + '</p>');
        }
        if (errors && errors.length > 0) {
            errors.forEach(error => logDiv.append('<p style="color:red;">Error: ' + error + '</p>'));
        }
    }

    $('#write-csv').on('click', function() {
        $.ajax({
            url: impactAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'impact_write_csv',
                nonce: impactAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#csv-status').text('CSV Ready');
                    $('#download-csv').attr('href', response.data.csv_file);
                    $('#impact-csv-controls').hide();
                    $('#impact-download-controls').show();
                } else {
                    $('#csv-status').text('Error writing CSV: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                $('#csv-status').text('Error writing CSV: ' + error);
            }
        });
    });

    $('#clear-data').on('click', function() {
        $.ajax({
            url: impactAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'impact_clear_data',
                nonce: impactAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error clearing data: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error clearing data: ' + error);
            }
        });
    });
});