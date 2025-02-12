<div class="wrap">
    <h1>Impact Data Manipulator Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('impact-data-manipulator-settings-group');
        do_settings_sections('impact-data-manipulator-settings-group');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Gemini API Key</th>
                <td>
                    <input type="text" name="impact_gemini_api_key" value="<?php echo esc_attr(get_option('impact_gemini_api_key')); ?>" />
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>