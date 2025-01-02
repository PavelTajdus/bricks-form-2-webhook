<?php
/**
 * Plugin Name: Bricks Form 2 Webhook
 * Plugin URI: https://github.com/paveltajdus/bricks-form-2-webhook
 * Description: Send Bricks Builder form submissions to any webhook URL. Perfect for integrations with Make.com, Zapier, or any other webhook service.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Pavel Tajduš
 * Author URI: https://www.tajdus.cz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bricks-form-2-webhook
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add settings page
add_action('admin_menu', function() {
    add_options_page(
        'Bricks Form 2 Webhook',
        'Bricks Form 2 Webhook',
        'manage_options',
        'bricks-form-2-webhook',
        'bfw_settings_page'
    );
});

// Register plugin settings
add_action('admin_init', function() {
    register_setting('bfw_settings', 'bfw_webhook_url');
    register_setting('bfw_settings', 'bfw_form_id');
    register_setting('bfw_settings', 'bfw_success_message', array(
        'default' => 'Data successfully sent'
    ));
    register_setting('bfw_settings', 'bfw_error_message', array(
        'default' => 'Error sending data'
    ));
});

// Add admin CSS
add_action('admin_enqueue_scripts', function($hook) {
    if ('settings_page_bricks-form-2-webhook' !== $hook) {
        return;
    }
    wp_enqueue_style('bfw-admin', plugins_url('assets/css/admin.css', __FILE__));
});

// Settings page HTML
function bfw_settings_page() {
    ?>
    <div class="wrap">
        <h2>Bricks Form 2 Webhook Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('bfw_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>Webhook URL</th>
                    <td>
                        <input type="url" name="bfw_webhook_url" value="<?php echo esc_attr(get_option('bfw_webhook_url')); ?>" class="regular-text">
                        <p class="description">Enter the webhook URL where form data will be sent</p>
                    </td>
                </tr>
                <tr>
                    <th>Form ID</th>
                    <td>
                        <input type="text" name="bfw_form_id" value="<?php echo esc_attr(get_option('bfw_form_id')); ?>" class="regular-text">
                        <p class="description">Enter your Bricks form ID (e.g., fszxsr)</p>
                    </td>
                </tr>
                <tr>
                    <th>Success Message</th>
                    <td>
                        <input type="text" name="bfw_success_message" value="<?php echo esc_attr(get_option('bfw_success_message', 'Data successfully sent')); ?>" class="regular-text">
                        <p class="description">Message displayed after successful submission</p>
                    </td>
                </tr>
                <tr>
                    <th>Error Message</th>
                    <td>
                        <input type="text" name="bfw_error_message" value="<?php echo esc_attr(get_option('bfw_error_message', 'Error sending data')); ?>" class="regular-text">
                        <p class="description">Message displayed when an error occurs</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Handle form submission
add_action('bricks/form/submit', 'send_bricks_form_to_webhook', 10, 1);

function send_bricks_form_to_webhook($form) {
    $data = $form->get_fields();
    $formId = $data['formId'];
    $webhook_url = get_option('bfw_webhook_url');
    $set_form_id = get_option('bfw_form_id');

    if ($formId == $set_form_id && !empty($webhook_url)) {
        $args = array(
            'body' => json_encode($data),
            'headers' => array('Content-Type' => 'application/json')
        );

        $response = wp_remote_post($webhook_url, $args);

        if (is_wp_error($response)) {
            $form->set_result([
                'type' => 'danger',
                'message' => get_option('bfw_error_message', 'Error sending data'),
            ]);
        } else {
            $form->set_result([
                'type' => 'success',
                'message' => get_option('bfw_success_message', 'Data successfully sent'),
            ]);
        }
    }
}

// Check for updates
add_filter('pre_set_site_transient_update_plugins', 'bfw_check_update');

function bfw_check_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = 'bricks-form-2-webhook/bricks-form-2-webhook.php';
    
    $request_uri = 'https://api.github.com/repos/paveltajdus/bricks-form-2-webhook/releases/latest';
    
    $raw_response = wp_remote_get($request_uri, array(
        'headers' => array(
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress'
        ),
    ));

    if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
        $response = json_decode(wp_remote_retrieve_body($raw_response));

        if (version_compare($response->tag_name, $transient->checked[$plugin_slug], '>')) {
            $obj = new stdClass();
            $obj->slug = 'bricks-form-2-webhook';
            $obj->new_version = $response->tag_name;
            $obj->url = $response->html_url;
            $obj->package = $response->zipball_url;
            
            $transient->response[$plugin_slug] = $obj;
        }
    }

    return $transient;
}

// Plugin information for update details
add_filter('plugins_api', 'bfw_plugin_info', 20, 3);

function bfw_plugin_info($res, $action, $args) {
    if ('plugin_information' !== $action) {
        return $res;
    }

    if ('bricks-form-2-webhook' !== $args->slug) {
        return $res;
    }

    $remote = wp_remote_get(
        'https://api.github.com/repos/paveltajdus/bricks-form-2-webhook/releases/latest',
        array(
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress'
            )
        )
    );

    if (!is_wp_error($remote) && 200 == wp_remote_retrieve_response_code($remote)) {
        $remote = json_decode(wp_remote_retrieve_body($remote));

        $res = new stdClass();
        $res->name = 'Bricks Form 2 Webhook';
        $res->slug = 'bricks-form-2-webhook';
        $res->version = $remote->tag_name;
        $res->tested = '6.4';
        $res->requires = '5.8';
        $res->author = 'Pavel Tajdus';
        $res->author_profile = 'https://github.com/paveltajdus';
        $res->download_link = $remote->zipball_url;
        $res->trunk = $remote->zipball_url;
        $res->requires_php = '7.4';
        $res->last_updated = $remote->published_at;
        $res->sections = array(
            'description' => $remote->body,
            'changelog' => $remote->body
        );

        return $res;
    }

    return false;
}
