<?php
/**
 * Plugin Name: Bricks Form 2 Webhook
 * Plugin URI: https://github.com/paveltajdus/bricks-form-2-webhook
 * Description: Propojení Bricks Builder formulářů s webhooky. Umožňuje snadné nastavení webhooku pro jakýkoliv Bricks formulář.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Pavel Tajduš
 * Author URI: https://hotend.cz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bricks-form-2-webhook
 */

if (!defined('ABSPATH')) {
    exit;
}

// Přidání stránky nastavení
add_action('admin_menu', function() {
    add_options_page(
        'Bricks Form 2 Webhook',
        'Bricks Form 2 Webhook',
        'manage_options',
        'bricks-form-2-webhook',
        'bfw_settings_page'
    );
});

// Registrace nastavení
add_action('admin_init', function() {
    register_setting('bfw_settings', 'bfw_webhook_url');
    register_setting('bfw_settings', 'bfw_form_id');
});

// Přidání CSS
add_action('admin_enqueue_scripts', function($hook) {
    if ('settings_page_bricks-form-2-webhook' !== $hook) {
        return;
    }
    wp_enqueue_style('bfw-admin', plugins_url('assets/css/admin.css', __FILE__));
});

// Stránka nastavení
function bfw_settings_page() {
    ?>
    <div class="wrap">
        <h2>Bricks Form 2 Webhook Nastavení</h2>
        <form method="post" action="options.php">
            <?php settings_fields('bfw_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>Webhook URL</th>
                    <td>
                        <input type="url" name="bfw_webhook_url" value="<?php echo esc_attr(get_option('bfw_webhook_url')); ?>" class="regular-text">
                        <p class="description">Zadejte URL webhooku, kam se budou odesílat data z formuláře</p>
                    </td>
                </tr>
                <tr>
                    <th>ID Formuláře</th>
                    <td>
                        <input type="text" name="bfw_form_id" value="<?php echo esc_attr(get_option('bfw_form_id')); ?>" class="regular-text">
                        <p class="description">Zadejte ID Bricks formuláře (např. fszxsr)</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Funkce pro odeslání dat na webhook
function odeslat_bricks_form_na_webhook($form) {
    $data = $form->get_fields();
    $formId = $data['formId'];
    $webhook_url = get_option('bfw_webhook_url');
    $nastavene_id = get_option('bfw_form_id');

    if ($formId == $nastavene_id && !empty($webhook_url)) {
        $args = array(
            'body' => json_encode($data),
            'headers' => array('Content-Type' => 'application/json')
        );

        $response = wp_remote_post($webhook_url, $args);

        if (is_wp_error($response)) {
            $form->set_result([
                'type' => 'danger',
                'message' => 'Chyba při odesílání na webhook',
            ]);
        } else {
            $form->set_result([
                'type' => 'success',
                'message' => 'Data úspěšně odeslána',
            ]);
        }
    }
}

add_action('bricks/form/custom_action', 'odeslat_bricks_form_na_webhook', 10, 1);
