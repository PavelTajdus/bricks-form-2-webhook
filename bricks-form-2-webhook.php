<?php
/**
 * Plugin Name: Bricks Form 2 Webhook
 * Plugin URI: https://github.com/paveltajdus/bricks-form-2-webhook
 * Description: Sends Bricks Builder form submissions to any webhook URL using WordPress Custom Form Action.
 * Version: 1.1.0
 * Author: Pavel Tajdus
 * Author URI: https://www.tajdus.cz
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

// Add settings page - do Settings menu (jako funkční verze)
add_action('admin_menu', function() {
    add_options_page(
        'Bricks Form 2 Webhook',
        'Bricks Form 2 Webhook',
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
    wp_enqueue_style('bf2w-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), '1.1.0');
    wp_enqueue_script('bf2w-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), '1.1.0', true);
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
        <h1>🔗 Bricks Form 2 Webhook</h1>
        
        <div class="notice notice-warning">
            <p><strong>Důležité:</strong> Nezapomeňte nastavit "Custom" akci ve vašem Bricks formuláři!</p>
        </div>
        
        <!-- Add/Edit Form -->
        <div class="bf2w-card">
            <h2><?php echo $editing_webhook ? 'Upravit Webhook' : 'Přidat Nový Webhook'; ?></h2>
            
            <form method="post">
                <?php wp_nonce_field('bf2w_action', 'bf2w_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $editing_webhook ? 'edit' : 'add'; ?>">
                <?php if ($editing_webhook): ?>
                    <input type="hidden" name="webhook_id" value="<?php echo esc_attr($editing_id); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Form ID <span style="color: red;">*</span></th>
                        <td>
                            <input type="text" name="form_id" value="<?php echo $editing_webhook ? esc_attr($editing_webhook['form_id']) : ''; ?>" class="regular-text" required>
                            <p class="description">ID formuláře (např. fszxsr bez prefixu bricks-element-)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook URL <span style="color: red;">*</span></th>
                        <td>
                            <input type="url" name="webhook_url" value="<?php echo $editing_webhook ? esc_attr($editing_webhook['webhook_url']) : ''; ?>" class="regular-text" required>
                            <p class="description">URL kam se odešlou data z formuláře</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Success Message</th>
                        <td>
                            <input type="text" name="success_message" value="<?php echo $editing_webhook ? esc_attr($editing_webhook['success_message']) : 'Data successfully sent'; ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Error Message</th>
                        <td>
                            <input type="text" name="error_message" value="<?php echo $editing_webhook ? esc_attr($editing_webhook['error_message']) : 'Error sending data'; ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php if ($editing_webhook): ?>
                        <input type="submit" class="button-primary" value="Aktualizovat Webhook">
                        <a href="<?php echo admin_url('options-general.php?page=bricks-form-2-webhook'); ?>" class="button">Zrušit</a>
                    <?php else: ?>
                        <input type="submit" class="button-primary" value="Přidat Webhook">
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <!-- Debug Settings -->
        <div class="bf2w-card">
            <h2>Debug Nastavení</h2>
            <form method="post">
                <?php wp_nonce_field('bf2w_action', 'bf2w_nonce'); ?>
                <input type="hidden" name="action" value="debug">
                <table class="form-table">
                    <tr>
                        <th scope="row">Debug Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" value="1" <?php checked($debug_mode); ?>>
                                Zapnout debug logging
                            </label>
                            <p class="description">Loguje detaily o odesílání formulářů pro řešení problémů</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button" value="Uložit Nastavení">
                </p>
            </form>
            
            <?php if ($debug_mode): ?>
            <div style="margin-top: 20px;">
                <h4>Debug Log Akce</h4>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=bricks-form-2-webhook&bf2w_download_log=1'), 'bf2w_download_log'); ?>" class="button">📥 Stáhnout Debug Log</a>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=bricks-form-2-webhook&bf2w_clear_log=1'), 'bf2w_clear_log'); ?>" class="button" onclick="return confirm('Opravdu vymazat debug log?')">🗑️ Vymazat Debug Log</a>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Existing Webhooks -->
        <?php if (!empty($webhooks)): ?>
        <div class="bf2w-card">
            <h2>Existující Webhooks</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Form ID</th>
                        <th>Webhook URL</th>
                        <th>Success Message</th>
                        <th>Error Message</th>
                        <th>Akce</th>
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
                            <a href="<?php echo admin_url('options-general.php?page=bricks-form-2-webhook&edit=' . $index); ?>" class="button button-small">Upravit</a>
                            <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=bricks-form-2-webhook&action=delete&webhook_id=' . $index), 'bf2w_delete'); ?>" class="button button-small button-link-delete" onclick="return confirm('Opravdu smazat?')">Smazat</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Instructions -->
        <div class="bf2w-card">
            <h2>Návod k použití</h2>
            <ol>
                <li>Přidejte webhook konfiguraci výše (Form ID + Webhook URL)</li>
                <li>Zkopírujte Form ID z Bricks formuláře (z CSS ID, např. z "bricks-element-fszxsr" použijte jen "fszxsr")</li>
                <li>Zadejte webhook URL kam se mají data posílat</li>
                <li>V Bricks formuláři přidejte akci a vyberte "Custom"</li>
                <li>Uložte a otestujte formulář</li>
            </ol>
        </div>
        
        <?php bf2w_display_debug_info(); ?>
    </div>
    <?php
}

// Handle form actions
function bf2w_handle_form_action() {
    if (!wp_verify_nonce($_POST['bf2w_nonce'], 'bf2w_action')) {
        wp_die('Security check failed');
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
        echo '<div class="notice notice-success"><p>Webhook přidán!</p></div>';
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
            echo '<div class="notice notice-success"><p>Webhook aktualizován!</p></div>';
        });
    }
}

// Update debug
function bf2w_update_debug() {
    $debug_mode = !empty($_POST['debug_mode']);
    update_option('bf2w_debug_mode', $debug_mode);
    
    add_action('admin_notices', function() use ($debug_mode) {
        $message = $debug_mode ? 'Debug mode zapnut' : 'Debug mode vypnut';
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
                
                wp_redirect(admin_url('options-general.php?page=bricks-form-2-webhook&deleted=1'));
                exit;
            }
        }
    }
});

// Custom debug logging functions
function bf2w_log($message) {
    $debug_mode = get_option('bf2w_debug_mode', false);
    if (!$debug_mode) return;
    
    $timestamp = current_time('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] BF2W: {$message}" . PHP_EOL;
    
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
        wp_die('Debug log neexistuje.');
    }
}

function bf2w_clear_debug_log() {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/bf2w-debug.log';
    
    if (file_exists($log_file)) {
        unlink($log_file);
    }
    
    wp_redirect(admin_url('options-general.php?page=bricks-form-2-webhook&log_cleared=1'));
    exit;
}

// Handle Bricks form submission (podle funkční verze!)
add_action('bricks/form/submit', 'bf2w_handle_form_submission', 10, 1);

function bf2w_handle_form_submission($form) {
    $data = $form->get_fields();
    $formId = isset($data['formId']) ? $data['formId'] : '';
    
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
    
    bf2w_log("Webhook found: " . ($webhook_config ? 'Yes' : 'No'));
    
    if (!$webhook_config) {
        bf2w_log("No webhook configuration found for form ID: {$clean_form_id}");
        return; // No webhook configured for this form
    }
    
    bf2w_log("Sending to webhook: {$webhook_config['webhook_url']}");
    bf2w_log("Form data: " . json_encode($data));
    
    // Send to webhook
    $args = array(
        'body' => json_encode($data),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 30
    );
    
    $response = wp_remote_post($webhook_config['webhook_url'], $args);
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        bf2w_log("Webhook error: {$error_message}");
        
        $form->set_result([
            'type' => 'danger',
            'message' => $webhook_config['error_message'],
        ]);
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        bf2w_log("Webhook success - Response code: {$response_code}, Body: {$response_body}");
        
        $form->set_result([
            'type' => 'success',
            'message' => $webhook_config['success_message'],
        ]);
    }
}

// Display debug info
function bf2w_display_debug_info() {
    $debug_mode = get_option('bf2w_debug_mode', false);
    if (!$debug_mode) return;
    
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/bf2w-debug.log';
    
    echo '<div class="bf2w-card">';
    echo '<h2>📋 Debug Log</h2>';
    
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $log_lines = array_filter(explode(PHP_EOL, $log_content));
        $recent_lines = array_slice($log_lines, -10); // Posledních 10 řádků
        
        echo '<div style="background: #f1f1f1; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">';
        if (!empty($recent_lines)) {
            echo '<strong>Posledních 10 záznamů:</strong><br><br>';
            foreach ($recent_lines as $line) {
                echo esc_html($line) . '<br>';
            }
        } else {
            echo 'Debug log je prázdný.';
        }
        echo '</div>';
        
        echo '<p style="margin-top: 10px;">';
        echo '<small>Celkem záznamů: ' . count($log_lines) . ' | ';
        echo 'Velikost souboru: ' . size_format(filesize($log_file)) . '</small>';
        echo '</p>';
    } else {
        echo '<p>Debug log soubor neexistuje. Zkuste odeslat formulář pro vytvoření logů.</p>';
    }
    
    echo '</div>';
}

// ============================================================================
// GITHUB AUTO-UPDATE SYSTEM
// ============================================================================

// Define constants for GitHub
define('BF2W_GITHUB_USER', 'paveltajdus');
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
    
    // Compare versions
    if (version_compare($current_version, $remote_version, '<')) {
        $transient->response[BF2W_PLUGIN_SLUG] = (object) array(
            'slug' => dirname(BF2W_PLUGIN_SLUG),
            'plugin' => BF2W_PLUGIN_SLUG,
            'new_version' => $remote_version,
            'url' => 'https://github.com/' . BF2W_GITHUB_USER . '/' . BF2W_GITHUB_REPO,
            'package' => bf2w_get_download_url($remote_version)
        );
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
function bf2w_get_download_url($version = 'latest') {
    if ($version === 'latest') {
        return 'https://github.com/' . BF2W_GITHUB_USER . '/' . BF2W_GITHUB_REPO . '/archive/refs/heads/main.zip';
    }
    
    // Use release ZIP
    return 'https://github.com/' . BF2W_GITHUB_USER . '/' . BF2W_GITHUB_REPO . '/archive/refs/tags/v' . $version . '.zip';
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
            'changelog' => $remote_data['changelog'] ?? 'Podrobnosti na GitHub stránce pluginu.'
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
        echo '<br><strong>🔄 Nová verze ' . esc_html($response->new_version) . ' je dostupná z GitHubu!</strong>';
    }
} 