<?php
/**
 * Plugin Name: Bricks Form 2 Webhook
 * Plugin URI: https://github.com/paveltajdus/bricks-form-2-webhook
 * Description: Sends Bricks Builder form submissions to any webhook URL using WordPress Custom Form Action.
 * Version: 1.2.9
 * Author: Pavel Tajduš
 * Author URI: https://www.paveltajdus.cz
 * Text Domain: bricks-form-2-webhook
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
add_action('plugins_loaded', function() {
    load_plugin_textdomain('bricks-form-2-webhook', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Add settings page - do Settings menu
add_action('admin_menu', function() {
    add_options_page(
        __('Bricks Form 2 Webhook', 'bricks-form-2-webhook'),
        __('Bricks Form 2 Webhook', 'bricks-form-2-webhook'),
        'manage_options',
        'bricks-form-2-webhook',
        'bf2w_settings_page'
    );
});

// Register settings
add_action('admin_init', function() {
    register_setting('bf2w_settings', 'bf2w_webhooks');
    register_setting('bf2w_settings', 'bf2w_debug_mode');
    
    // Handle debug log download
    if (isset($_GET['bf2w_download_log']) && wp_verify_nonce($_GET['_wpnonce'], 'bf2w_download_log')) {
        bf2w_download_debug_log();
    }
    
    // Handle debug log clear
    if (isset($_GET['bf2w_clear_log']) && wp_verify_nonce($_GET['_wpnonce'], 'bf2w_clear_log')) {
        bf2w_clear_debug_log();
    }
});

// Enqueue admin assets
add_action('admin_enqueue_scripts', function($hook) {
    if ('settings_page_bricks-form-2-webhook' !== $hook) {
        return;
    }
    wp_enqueue_style('bf2w-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), '1.2.0');
    wp_enqueue_script('bf2w-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), '1.2.0', true);
});

// Settings page
function bf2w_settings_page() {
    // Handle form submissions
    if (isset($_POST['action'])) {
        bf2w_handle_form_action();
    }
    
    $webhooks = get_option('bf2w_webhooks', array());
    $debug_mode = get_option('bf2w_debug_mode', false);
    $editing_id = isset($_GET['edit']) ? intval($_GET['edit']) : null;
    $editing_webhook = null;
    
    if ($editing_id !== null && isset($webhooks[$editing_id])) {
        $editing_webhook = $webhooks[$editing_id];
    }
    ?>
    <div class="wrap">
        <h1>🔗 <?php _e('Bricks Form 2 Webhook', 'bricks-form-2-webhook'); ?></h1>
        
        <div class="notice notice-warning">
            <p><strong><?php _e('Důležité:', 'bricks-form-2-webhook'); ?></strong> <?php _e('Nezapomeňte nastavit "Custom" akci ve vašem Bricks formuláři!', 'bricks-form-2-webhook'); ?></p>
        </div>
        
        <!-- Add/Edit Form -->
        <div class="bf2w-card">
            <h2><?php echo $editing_webhook ? __('Upravit Webhook', 'bricks-form-2-webhook') : __('Přidat Nový Webhook', 'bricks-form-2-webhook'); ?></h2>
            
            <form method="post">
                <?php wp_nonce_field('bf2w_action', 'bf2w_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $editing_webhook ? 'edit' : 'add'; ?>">
                <?php if ($editing_webhook): ?>
                    <input type="hidden" name="webhook_id" value="<?php echo esc_attr($editing_id); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Form ID', 'bricks-form-2-webhook'); ?> <span style="color: red;">*</span></th>
                        <td>
                            <input type="text" name="form_id" value="<?php echo $editing_webhook ? esc_attr($editing_webhook['form_id']) : ''; ?>" class="regular-text" required>
                            <p class="description"><?php _e('ID formuláře (např. fszxsr bez prefixu bricks-element-)', 'bricks-form-2-webhook'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Webhook URL', 'bricks-form-2-webhook'); ?> <span style="color: red;">*</span></th>
                        <td>
                            <input type="url" name="webhook_url" value="<?php echo $editing_webhook ? esc_attr($editing_webhook['webhook_url']) : ''; ?>" class="regular-text" required>
                            <p class="description"><?php _e('URL kam se odešlou data z formuláře', 'bricks-form-2-webhook'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Success Message', 'bricks-form-2-webhook'); ?></th>
                        <td>
                            <input type="text" name="success_message" value="<?php echo $editing_webhook ? esc_attr($editing_webhook['success_message']) : __('Data successfully sent', 'bricks-form-2-webhook'); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Error Message', 'bricks-form-2-webhook'); ?></th>
                        <td>
                            <input type="text" name="error_message" value="<?php echo $editing_webhook ? esc_attr($editing_webhook['error_message']) : __('Error sending data', 'bricks-form-2-webhook'); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php if ($editing_webhook): ?>
                        <input type="submit" class="button-primary" value="<?php _e('Aktualizovat Webhook', 'bricks-form-2-webhook'); ?>">
                        <a href="<?php echo admin_url('options-general.php?page=bricks-form-2-webhook'); ?>" class="button"><?php _e('Zrušit', 'bricks-form-2-webhook'); ?></a>
                    <?php else: ?>
                        <input type="submit" class="button-primary" value="<?php _e('Přidat Webhook', 'bricks-form-2-webhook'); ?>">
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <!-- Debug Settings -->
        <div class="bf2w-card">
            <h2><?php _e('Debug Nastavení', 'bricks-form-2-webhook'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('bf2w_action', 'bf2w_nonce'); ?>
                <input type="hidden" name="action" value="debug">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Debug Mode', 'bricks-form-2-webhook'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" value="1" <?php checked($debug_mode); ?>>
                                <?php _e('Zapnout debug logging', 'bricks-form-2-webhook'); ?>
                            </label>
                            <p class="description"><?php _e('Loguje detaily o odesílání formulářů pro řešení problémů', 'bricks-form-2-webhook'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button" value="<?php _e('Uložit Nastavení', 'bricks-form-2-webhook'); ?>">
                </p>
            </form>
            
            <?php if ($debug_mode): ?>
            <div style="margin-top: 20px;">
                <h4><?php _e('Debug Log Akce', 'bricks-form-2-webhook'); ?></h4>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=bricks-form-2-webhook&bf2w_download_log=1'), 'bf2w_download_log'); ?>" class="button">📥 <?php _e('Stáhnout Debug Log', 'bricks-form-2-webhook'); ?></a>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=bricks-form-2-webhook&bf2w_clear_log=1'), 'bf2w_clear_log'); ?>" class="button" onclick="return confirm('<?php _e('Opravdu vymazat debug log?', 'bricks-form-2-webhook'); ?>')">🗑️ <?php _e('Vymazat Debug Log', 'bricks-form-2-webhook'); ?></a>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Existing Webhooks -->
        <?php if (!empty($webhooks)): ?>
        <div class="bf2w-card">
            <h2><?php _e('Existující Webhooks', 'bricks-form-2-webhook'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Form ID', 'bricks-form-2-webhook'); ?></th>
                        <th><?php _e('Webhook URL', 'bricks-form-2-webhook'); ?></th>
                        <th><?php _e('Success Message', 'bricks-form-2-webhook'); ?></th>
                        <th><?php _e('Error Message', 'bricks-form-2-webhook'); ?></th>
                        <th><?php _e('Akce', 'bricks-form-2-webhook'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webhooks as $index => $webhook): ?>
                    <tr>
                        <td><code><?php echo esc_html($webhook['form_id']); ?></code></td>
                        <td><a href="<?php echo esc_url($webhook['webhook_url']); ?>" target="_blank"><?php echo esc_html(wp_trim_words($webhook['webhook_url'], 8, '...')); ?></a></td>
                        <td><?php echo esc_html($webhook['success_message']); ?></td>
                        <td><?php echo esc_html($webhook['error_message']); ?></td>
                        <td>
                            <a href="<?php echo admin_url('options-general.php?page=bricks-form-2-webhook&edit=' . $index); ?>" class="button button-small"><?php _e('Upravit', 'bricks-form-2-webhook'); ?></a>
                            <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=bricks-form-2-webhook&action=delete&webhook_id=' . $index), 'bf2w_delete'); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('Opravdu smazat?', 'bricks-form-2-webhook'); ?>')"><?php _e('Smazat', 'bricks-form-2-webhook'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Instructions -->
        <div class="bf2w-card">
            <h2><?php _e('Návod k použití', 'bricks-form-2-webhook'); ?></h2>
            <ol>
                <li><?php _e('Přidejte webhook konfiguraci výše (Form ID + Webhook URL)', 'bricks-form-2-webhook'); ?></li>
                <li><?php _e('Zkopírujte Form ID z Bricks formuláře (z CSS ID, např. z "bricks-element-fszxsr" použijte jen "fszxsr")', 'bricks-form-2-webhook'); ?></li>
                <li><?php _e('Zadejte webhook URL kam se mají data posílat', 'bricks-form-2-webhook'); ?></li>
                <li><?php _e('V Bricks formuláři přidejte akci a vyberte "Custom"', 'bricks-form-2-webhook'); ?></li>
                <li><?php _e('Uložte a otestujte formulář', 'bricks-form-2-webhook'); ?></li>
            </ol>
        </div>
        
        <?php bf2w_display_debug_info(); ?>
    </div>
    <?php
}

// Handle form actions
function bf2w_handle_form_action() {
    if (!wp_verify_nonce($_POST['bf2w_nonce'], 'bf2w_action')) {
        wp_die(esc_html__('Security check failed', 'bricks-form-2-webhook'));
    }
    
    $action = sanitize_text_field($_POST['action']);
    
    switch ($action) {
        case 'add':
            bf2w_add_webhook();
            break;
        case 'edit':
            bf2w_edit_webhook();
            break;
        case 'debug':
            bf2w_update_debug();
            break;
    }
}

// Add webhook
function bf2w_add_webhook() {
    $webhooks = get_option('bf2w_webhooks', array());
    
    $new_webhook = array(
        'form_id' => sanitize_text_field($_POST['form_id']),
        'webhook_url' => esc_url_raw($_POST['webhook_url']),
        'success_message' => sanitize_text_field($_POST['success_message']),
        'error_message' => sanitize_text_field($_POST['error_message'])
    );
    
    $webhooks[] = $new_webhook;
    update_option('bf2w_webhooks', $webhooks);
    
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>' . esc_html__('Webhook přidán!', 'bricks-form-2-webhook') . '</p></div>';
    });
}

// Edit webhook
function bf2w_edit_webhook() {
    $webhook_id = intval($_POST['webhook_id']);
    $webhooks = get_option('bf2w_webhooks', array());
    
    if (isset($webhooks[$webhook_id])) {
        $webhooks[$webhook_id] = array(
            'form_id' => sanitize_text_field($_POST['form_id']),
            'webhook_url' => esc_url_raw($_POST['webhook_url']),
            'success_message' => sanitize_text_field($_POST['success_message']),
            'error_message' => sanitize_text_field($_POST['error_message'])
        );
        
        update_option('bf2w_webhooks', $webhooks);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . esc_html__('Webhook aktualizován!', 'bricks-form-2-webhook') . '</p></div>';
        });
    }
}

// Update debug
function bf2w_update_debug() {
    $debug_mode = !empty($_POST['debug_mode']);
    update_option('bf2w_debug_mode', $debug_mode);
    
    add_action('admin_notices', function() use ($debug_mode) {
        $message = $debug_mode ? esc_html__('Debug mode zapnut', 'bricks-form-2-webhook') : esc_html__('Debug mode vypnut', 'bricks-form-2-webhook');
        echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
    });
}

// Handle delete via GET
add_action('admin_init', function() {
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['webhook_id'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'bf2w_delete')) {
            $webhook_id = intval($_GET['webhook_id']);
            $webhooks = get_option('bf2w_webhooks', array());
            
            if (isset($webhooks[$webhook_id])) {
                unset($webhooks[$webhook_id]);
                $webhooks = array_values($webhooks);
                update_option('bf2w_webhooks', $webhooks);
                
                wp_redirect(admin_url('options-general.php?page=bricks-form-2-webhook&bf2w_message=webhook_deleted'));
                exit;
            }
        }
    }
});

// Display admin notices
add_action('admin_notices', function() {
    if (isset($_GET['bf2w_message'])) {
        $message_key = sanitize_key($_GET['bf2w_message']);
        $message = '';
        $type = 'success';
        
        switch ($message_key) {
            case 'webhook_deleted':
                $message = esc_html__('Webhook úspěšně smazán.', 'bricks-form-2-webhook');
                break;
            case 'log_cleared':
                $message = esc_html__('Debug log úspěšně vymazán.', 'bricks-form-2-webhook');
                break;
        }
        
        if ($message) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . $message . '</p></div>';
        }
    }
});

// Custom debug logging functions
function bf2w_log($message) {
    $debug_mode = get_option('bf2w_debug_mode', false);
    if (!$debug_mode) return;
    
    $timestamp = current_time('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] BF2W: " . (is_array($message) || is_object($message) ? json_encode($message) : $message) . PHP_EOL;
    
    // Save to custom log in uploads directory
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/bf2w-debug.log';
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function bf2w_download_debug_log() {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/bf2w-debug.log';
    
    if (file_exists($log_file)) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="bf2w-debug-' . date('Y-m-d-H-i-s') . '.log"');
        header('Content-Length: ' . filesize($log_file));
        readfile($log_file);
        exit;
    } else {
        wp_die(esc_html__('Debug log neexistuje.', 'bricks-form-2-webhook'));
    }
}

function bf2w_clear_debug_log() {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/bf2w-debug.log';
    
    if (file_exists($log_file)) {
        unlink($log_file);
    }
    
    wp_redirect(admin_url('options-general.php?page=bricks-form-2-webhook&bf2w_message=log_cleared'));
    exit;
}

// Handle Bricks form submission
add_action('bricks/form/submit', 'bf2w_handle_form_submission', 10, 1);
add_action('bricks/form/custom_action', 'bf2w_handle_form_submission', 10, 1);

function bf2w_handle_form_submission($form) {
    bf2w_log("=== FORM SUBMISSION DETECTED ===");
    bf2w_log("Hook called: " . current_action());
    
    // Try to get form data
    $data = array();
    if (method_exists($form, 'get_fields')) {
        $data = $form->get_fields();
        bf2w_log("Got form data via get_fields(): " . json_encode($data));
    } else {
        bf2w_log("Form object does not have get_fields() method. Object type: " . get_class($form) . ", Methods: " . implode(', ', get_class_methods($form)));
    }
    
    // Try alternative ways to get form ID
    $formId = '';
    if (isset($data['formId'])) {
        $formId = $data['formId'];
    } else if (isset($_POST['formId'])) {
        $formId = $_POST['formId'];
        bf2w_log("Form ID from POST: " . $formId);
    } else if (method_exists($form, 'get_form_id')) {
        $formId = $form->get_form_id();
        bf2w_log("Form ID from get_form_id(): " . $formId);
    }
    
    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
        bf2w_log("Using POST data as fallback: " . json_encode($_POST));
    }
    
    // Remove bricks-element- prefix if present
    $clean_form_id = str_replace('bricks-element-', '', $formId);
    
    $webhooks = get_option('bf2w_webhooks', array());
    
    bf2w_log("Form submitted - Original ID: {$formId}, Clean ID: {$clean_form_id}");
    bf2w_log("Available webhooks: " . implode(', ', array_column($webhooks, 'form_id')));
    
    // Find matching webhook
    $webhook_config = null;
    foreach ($webhooks as $webhook) {
        if ($webhook['form_id'] === $clean_form_id) {
            $webhook_config = $webhook;
            break;
        }
    }
    
    bf2w_log("Webhook found: " . ($webhook_config ? __('Yes', 'bricks-form-2-webhook') : __('No', 'bricks-form-2-webhook')));
    
    if (!$webhook_config) {
        bf2w_log(sprintf(esc_html__("No webhook configuration found for form ID: %s", 'bricks-form-2-webhook'), $clean_form_id));
        return; // No webhook configured for this form
    }
    
    bf2w_log(sprintf(esc_html__("Sending to webhook: %s", 'bricks-form-2-webhook'), $webhook_config['webhook_url']));
    bf2w_log(esc_html__("Form data: ", 'bricks-form-2-webhook') . json_encode($data));
    
    // Send to webhook
    $args = array(
        'body' => json_encode($data),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 30
    );
    
    $response = wp_remote_post($webhook_config['webhook_url'], $args);
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        bf2w_log(sprintf(esc_html__("Webhook error: %s", 'bricks-form-2-webhook'), $error_message));
        
        if (method_exists($form, 'set_result')) {
            $form->set_result([
                'type' => 'danger',
                'message' => $webhook_config['error_message'],
            ]);
        }
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        bf2w_log(sprintf(esc_html__("Webhook success - Response code: %s, Body: %s", 'bricks-form-2-webhook'), $response_code, $response_body));
        
        if (method_exists($form, 'set_result')) {
            $form->set_result([
                'type' => 'success',
                'message' => $webhook_config['success_message'],
            ]);
        }
    }
}

// Display debug info
function bf2w_display_debug_info() {
    $debug_mode = get_option('bf2w_debug_mode', false);
    if (!$debug_mode) return;
    
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/bf2w-debug.log';
    
    echo '<div class="bf2w-card">';
    echo '<h2>📋 ' . esc_html__('Debug Log', 'bricks-form-2-webhook') . '</h2>';
    
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $log_lines = array_filter(explode(PHP_EOL, $log_content));
        $recent_lines = array_slice($log_lines, -10); // Posledních 10 řádků
        
        echo '<div style="background: #f1f1f1; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">';
        if (!empty($recent_lines)) {
            echo '<strong>' . esc_html__('Posledních 10 záznamů:', 'bricks-form-2-webhook') . '</strong><br><br>';
            foreach ($recent_lines as $line) {
                echo esc_html($line) . '<br>';
            }
        } else {
            echo esc_html__('Debug log je prázdný.', 'bricks-form-2-webhook');
        }
        echo '</div>';
        
        echo '<p style="margin-top: 10px;">';
        echo '<small>' . sprintf(esc_html__('Celkem záznamů: %d | Velikost souboru: %s', 'bricks-form-2-webhook'), count($log_lines), size_format(filesize($log_file))) . '</small>';
        echo '</p>';
    } else {
        echo '<p>' . esc_html__('Debug log soubor neexistuje. Zkuste odeslat formulář pro vytvoření logů.', 'bricks-form-2-webhook') . '</p>';
    }
    
    echo '</div>';
}

// ============================================================================
// GITHUB AUTO-UPDATE SYSTEM
// This section handles the automatic updates from GitHub.
// Version 1.2.3 - testing update process again.
// ============================================================================

// Define constants for GitHub
define('BF2W_GITHUB_USER', 'PavelTajdus');
define('BF2W_GITHUB_REPO', 'bricks-form-2-webhook');
define('BF2W_PLUGIN_SLUG', plugin_basename(__FILE__));

// Hook into WordPress update system
add_filter('pre_set_site_transient_update_plugins', 'bf2w_check_for_update');
add_filter('plugins_api', 'bf2w_plugin_info', 20, 3);

// Check for plugin updates
function bf2w_check_for_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    // Get current plugin version
    $plugin_data = get_plugin_data(__FILE__);
    $current_version = $plugin_data['Version'];
    
    // Get remote version from GitHub
    $remote_version = bf2w_get_remote_version();
    
    if (!$remote_version) { // Check if remote version was fetched successfully
        return $transient;
    }
    
    // Compare versions
    if (version_compare($current_version, $remote_version, '<')) {
        $download_url = bf2w_get_download_url($remote_version);

        // Log the download URL for debugging
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
            error_log('[BF2W Updater] Proposing update to ' . $remote_version . ' with package URL: ' . print_r($download_url, true));
        }
        
        if ($download_url) { // Only offer update if we have a valid package URL
            $transient->response[BF2W_PLUGIN_SLUG] = (object) array(
                'slug' => dirname(BF2W_PLUGIN_SLUG),
                'plugin' => BF2W_PLUGIN_SLUG,
                'new_version' => $remote_version,
                'url' => 'https://github.com/' . BF2W_GITHUB_USER . '/' . BF2W_GITHUB_REPO,
                'package' => $download_url
            );
        } else {
            // Log if download URL is false
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
                error_log('[BF2W Updater] Download URL for version ' . $remote_version . ' was not found or was invalid.');
            }
        }
    }

    return $transient;
}

// Get remote version from GitHub releases
function bf2w_get_remote_version() {
    $request = wp_remote_get(
        'https://api.github.com/repos/' . BF2W_GITHUB_USER . '/' . BF2W_GITHUB_REPO . '/releases/latest',
        array(
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            ),
            'timeout' => 10
        )
    );

    if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($request);
    $data = json_decode($body, true);

    if (isset($data['tag_name'])) {
        // Remove 'v' prefix if present (e.g., v1.1.0 -> 1.1.0)
        return ltrim($data['tag_name'], 'v');
    }

    return false;
}

// Get download URL for specific version
function bf2w_get_download_url($version) {
    if (empty($version)) {
        return false; 
    }

    $api_url = 'https://api.github.com/repos/' . BF2W_GITHUB_USER . '/' . BF2W_GITHUB_REPO . '/releases/tags/v' . $version;
    
    $request_args = array(
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ),
        'timeout' => 15 
    );
    
    // Add token if defined, to avoid rate limiting for private repos (though this repo is public)
    if (defined('BF2W_GITHUB_TOKEN') && !empty(BF2W_GITHUB_TOKEN)) {
        $request_args['headers']['Authorization'] = 'token ' . BF2W_GITHUB_TOKEN;
    }

    $request = wp_remote_get($api_url, $request_args);

    if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
        // Optional: Log error for debugging
        // error_log('BF2W GitHub API Error for release tag v' . $version . ': ' . (is_wp_error($request) ? $request->get_error_message() : wp_remote_retrieve_response_code($request) . ' ' . wp_remote_retrieve_response_message($request)));
        return false;
    }

    $release_data = json_decode(wp_remote_retrieve_body($request), true);

    if (empty($release_data) || !isset($release_data['assets']) || !is_array($release_data['assets'])) {
        // Optional: Log error
        // error_log('BF2W GitHub Release data or assets missing for tag v' . $version);
        return false;
    }

    foreach ($release_data['assets'] as $asset) {
        if (isset($asset['name']) && $asset['name'] === 'bricks-form-2-webhook.zip' && isset($asset['browser_download_url'])) {
            return $asset['browser_download_url'];
        }
    }
    
    // Optional: Log error if specific asset not found
    // error_log('BF2W GitHub Release Asset "bricks-form-2-webhook.zip" not found for tag v' . $version);
    return false; 
}

// Provide plugin information for update details
function bf2w_plugin_info($result, $action, $args) {
    if ($action !== 'plugin_information') {
        return $result;
    }

    if (!isset($args->slug) || dirname(BF2W_PLUGIN_SLUG) !== $args->slug) {
        return $result;
    }

    // Get plugin data
    $plugin_data = get_plugin_data(__FILE__);
    
    // Get remote data
    $remote_data = bf2w_get_remote_plugin_info();
    
    $result = (object) array(
        'slug' => dirname(BF2W_PLUGIN_SLUG),
        'plugin_name' => $plugin_data['Name'],
        'name' => $plugin_data['Name'],
        'version' => $remote_data['version'] ?? $plugin_data['Version'],
        'author' => '<a href="' . $plugin_data['AuthorURI'] . '">' . $plugin_data['AuthorName'] . '</a>',
        'author_profile' => $plugin_data['AuthorURI'],
        'homepage' => $plugin_data['PluginURI'],
        'short_description' => $plugin_data['Description'],
        'requires' => '5.8',
        'tested' => '6.4',
        'requires_php' => '7.4',
        'last_updated' => $remote_data['updated'] ?? date('Y-m-d'),
        'download_link' => bf2w_get_download_url($remote_data['version'] ?? 'latest'),
        'sections' => array(
            'description' => $plugin_data['Description'],
            'changelog' => $remote_data['changelog'] ?? __('Podrobnosti na GitHub stránce pluginu.', 'bricks-form-2-webhook')
        ),
        'banners' => array(),
        'icons' => array()
    );

    return $result;
}

// Get remote plugin information from GitHub
function bf2w_get_remote_plugin_info() {
    $request = wp_remote_get(
        'https://api.github.com/repos/' . BF2W_GITHUB_USER . '/' . BF2W_GITHUB_REPO . '/releases/latest',
        array(
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            ),
            'timeout' => 10
        )
    );

    if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
        return array();
    }

    $body = wp_remote_retrieve_body($request);
    $data = json_decode($body, true);

    if (!$data) {
        return array();
    }

    return array(
        'version' => isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : false,
        'updated' => isset($data['published_at']) ? date('Y-m-d', strtotime($data['published_at'])) : date('Y-m-d'),
        'changelog' => isset($data['body']) ? $data['body'] : ''
    );
}

// Add update info to plugin page
add_action('in_plugin_update_message-' . BF2W_PLUGIN_SLUG, 'bf2w_update_message', 10, 2);

function bf2w_update_message($plugin_data, $response) {
    if (isset($response->new_version)) {
        echo '<br><strong>' . sprintf(esc_html__('🔄 Nová verze %s je dostupná z GitHubu!', 'bricks-form-2-webhook'), esc_html($response->new_version)) . '</strong>';
    }
}

define( 'BF2W_VERSION', '1.2.9' ); 