<?php
/**
 * Plugin Name: Bricks Form 2 Webhook
 * Plugin URI: https://github.com/paveltajdus/bricks-form-2-webhook
 * Description: Sends Bricks Builder form submissions to any webhook URL using WordPress Custom Form Action.
 * Version: 1.0.5
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

// Define plugin constants
define('BF2W_VERSION', '1.0.5');
define('BF2W_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BF2W_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BF2W_GITHUB_REPO', 'paveltajdus/bricks-form-2-webhook');

/**
 * Main Plugin Class
 */
class BricksForm2Webhook {
    
    private $options;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Hook into Bricks form custom action
        add_action('bricks/form/custom_action', array($this, 'send_bricks_form_to_webhook'));
        
        // Auto-update hooks
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_plugin_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        
        $this->options = get_option('bf2w_settings');
    }
    
    public function init() {
        load_plugin_textdomain('bricks-form-2-webhook', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_admin_assets($hook) {
        // Debug: Check if we're on the right page
        if (strpos($hook, 'bricks-form-2-webhook') === false) {
            return;
        }
        
        wp_enqueue_style(
            'bf2w-admin-css',
            BF2W_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BF2W_VERSION
        );
        
        wp_enqueue_script(
            'bf2w-admin-js',
            BF2W_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            BF2W_VERSION,
            true
        );
        
        wp_localize_script('bf2w-admin-js', 'bf2w_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bf2w_nonce')
        ));
    }
    
    public function add_admin_menu() {
        $bricks_detected = false;
        
        // Check multiple ways if Bricks is active
        if (class_exists('Bricks\Database') || 
            defined('BRICKS_VERSION') || 
            function_exists('bricks_is_builder_main') ||
            is_plugin_active('bricks/bricks.php') ||
            (function_exists('get_template') && get_template() === 'bricks')) {
            $bricks_detected = true;
        }
        
        // Debug info
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BF2W: Bricks detected: ' . ($bricks_detected ? 'yes' : 'no'));
        }
        
        if ($bricks_detected) {
            $hook = add_submenu_page(
                'bricks',
                __('Webhook for Forms', 'bricks-form-2-webhook'),
                __('Webhook for Forms', 'bricks-form-2-webhook'),
                'manage_options',
                'bricks-form-2-webhook',
                array($this, 'admin_page'),
                100  // Position at the end of menu
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BF2W: Added to Bricks menu with hook: ' . $hook);
            }
        } else {
            // Fallback to Settings menu if Bricks is not detected
            $hook = add_options_page(
                __('Bricks Form 2 Webhook', 'bricks-form-2-webhook'),
                __('Bricks Form 2 Webhook', 'bricks-form-2-webhook'),
                'manage_options',
                'bricks-form-2-webhook',
                array($this, 'admin_page')
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BF2W: Added to Settings menu with hook: ' . $hook);
            }
        }
    }
    
    public function handle_form_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add new webhook
        if (isset($_POST['bf2w_add_webhook']) && wp_verify_nonce($_POST['bf2w_nonce'], 'bf2w_add_webhook')) {
            $this->add_webhook();
        }
        
        // Edit webhook
        if (isset($_POST['bf2w_edit_webhook']) && wp_verify_nonce($_POST['bf2w_nonce'], 'bf2w_edit_webhook')) {
            $this->edit_webhook();
        }
        
        // Delete webhook
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['webhook_id']) && wp_verify_nonce($_GET['_wpnonce'], 'bf2w_delete_webhook')) {
            $this->delete_webhook($_GET['webhook_id']);
        }
        
        // Toggle debug mode
        if (isset($_POST['bf2w_toggle_debug']) && wp_verify_nonce($_POST['bf2w_nonce'], 'bf2w_toggle_debug')) {
            $this->toggle_debug_mode();
        }
    }
    
    private function add_webhook() {
        $form_id = sanitize_text_field($_POST['form_id']);
        $webhook_url = esc_url_raw($_POST['webhook_url']);
        $success_message = sanitize_text_field($_POST['success_message']);
        $error_message = sanitize_text_field($_POST['error_message']);
        
        if (empty($form_id) || empty($webhook_url)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Form ID and Webhook URL are required.', 'bricks-form-2-webhook') . '</p></div>';
            });
            return;
        }
        
        $options = get_option('bf2w_settings', array('forms' => array()));
        
        // Check if form ID already exists
        foreach ($options['forms'] as $form) {
            if ($form['form_id'] === $form_id) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('Form ID already exists. Please use a different Form ID.', 'bricks-form-2-webhook') . '</p></div>';
                });
                return;
            }
        }
        
        $options['forms'][] = array(
            'form_id' => $form_id,
            'webhook_url' => $webhook_url,
            'success_message' => !empty($success_message) ? $success_message : __('Data successfully sent', 'bricks-form-2-webhook'),
            'error_message' => !empty($error_message) ? $error_message : __('Error sending data', 'bricks-form-2-webhook')
        );
        
        update_option('bf2w_settings', $options);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Webhook added successfully.', 'bricks-form-2-webhook') . '</p></div>';
        });
    }
    
    private function edit_webhook() {
        $webhook_id = intval($_POST['webhook_id']);
        $form_id = sanitize_text_field($_POST['form_id']);
        $webhook_url = esc_url_raw($_POST['webhook_url']);
        $success_message = sanitize_text_field($_POST['success_message']);
        $error_message = sanitize_text_field($_POST['error_message']);
        
        if (empty($form_id) || empty($webhook_url)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Form ID and Webhook URL are required.', 'bricks-form-2-webhook') . '</p></div>';
            });
            return;
        }
        
        $options = get_option('bf2w_settings', array('forms' => array()));
        
        if (!isset($options['forms'][$webhook_id])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Webhook not found.', 'bricks-form-2-webhook') . '</p></div>';
            });
            return;
        }
        
        // Check if form ID already exists (but not for the current webhook)
        foreach ($options['forms'] as $index => $form) {
            if ($index !== $webhook_id && $form['form_id'] === $form_id) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('Form ID already exists. Please use a different Form ID.', 'bricks-form-2-webhook') . '</p></div>';
                });
                return;
            }
        }
        
        $options['forms'][$webhook_id] = array(
            'form_id' => $form_id,
            'webhook_url' => $webhook_url,
            'success_message' => !empty($success_message) ? $success_message : __('Data successfully sent', 'bricks-form-2-webhook'),
            'error_message' => !empty($error_message) ? $error_message : __('Error sending data', 'bricks-form-2-webhook')
        );
        
        update_option('bf2w_settings', $options);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Webhook updated successfully.', 'bricks-form-2-webhook') . '</p></div>';
        });
    }
    
    private function delete_webhook($webhook_id) {
        $webhook_id = intval($webhook_id);
        $options = get_option('bf2w_settings', array('forms' => array()));
        
        if (isset($options['forms'][$webhook_id])) {
            unset($options['forms'][$webhook_id]);
            $options['forms'] = array_values($options['forms']); // Re-index array
            update_option('bf2w_settings', $options);
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Webhook deleted successfully.', 'bricks-form-2-webhook') . '</p></div>';
            });
        }
    }
    
    private function toggle_debug_mode() {
        $options = get_option('bf2w_settings', array('forms' => array()));
        $options['debug_mode'] = !empty($_POST['debug_mode']);
        update_option('bf2w_settings', $options);
        
        $message = $options['debug_mode'] 
            ? __('Debug mode enabled.', 'bricks-form-2-webhook')
            : __('Debug mode disabled.', 'bricks-form-2-webhook');
        
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
        });
    }
    
    public function send_bricks_form_to_webhook($form) {
        // Get settings
        $options = get_option('bf2w_settings');
        
        if (empty($options['forms'])) {
            return;
        }
        
        // Get form data
        $form_data = $form->get_fields();
        
        // Extract form ID from data
        $form_id = '';
        if (isset($form_data['formId'])) {
            $form_id = str_replace('bricks-element-', '', $form_data['formId']);
        }
        
        // Find matching form configuration
        $form_config = null;
        foreach ($options['forms'] as $config) {
            if ($config['form_id'] === $form_id) {
                $form_config = $config;
                break;
            }
        }
        
        // Debug info (only if debug mode is enabled)
        if (!empty($options['debug_mode'])) {
            $debug_info = array(
                'timestamp' => current_time('mysql'),
                'form_id_received' => $form_id,
                'form_config_found' => !is_null($form_config),
                'configured_forms' => array_column($options['forms'], 'form_id'),
                'webhook_url' => $form_config ? $form_config['webhook_url'] : null,
                'form_data' => $form_data,
                'response' => null,
                'error' => null
            );
        }
        
        // Check if form configuration exists
        if (!$form_config) {
            if (!empty($options['debug_mode'])) {
                $debug_info['error'] = 'No configuration found for form ID: ' . $form_id;
                set_transient('bf2w_debug_info', $debug_info, 60);
            }
            return;
        }
        
        // Prepare data for webhook
        $webhook_data = $form_data;
        
        // Send to webhook
        $response = wp_remote_post($form_config['webhook_url'], array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($webhook_data),
        ));
        
        if (is_wp_error($response)) {
            if (!empty($options['debug_mode'])) {
                $debug_info['error'] = $response->get_error_message();
                set_transient('bf2w_debug_info', $debug_info, 60);
            }
            
            $form->set_result(array(
                'action' => 'custom',
                'type' => 'error',
                'message' => $form_config['error_message']
            ));
        } else {
            if (!empty($options['debug_mode'])) {
                $debug_info['response'] = array(
                    'code' => wp_remote_retrieve_response_code($response),
                    'body' => wp_remote_retrieve_body($response)
                );
                set_transient('bf2w_debug_info', $debug_info, 60);
            }
            
            $form->set_result(array(
                'action' => 'custom',
                'type' => 'success',
                'message' => $form_config['success_message']
            ));
        }
    }
    
    public function admin_page() {
        // Debug info
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BF2W Admin Page Loading - Hook: ' . current_filter());
            error_log('BF2W Admin Page - Current user can manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        }
        
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $options = get_option('bf2w_settings', array('forms' => array()));
        $editing_id = isset($_GET['edit']) ? intval($_GET['edit']) : null;
        $editing_webhook = null;
        
        if ($editing_id !== null && isset($options['forms'][$editing_id])) {
            $editing_webhook = $options['forms'][$editing_id];
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Webhook for Forms Settings', 'bricks-form-2-webhook'); ?></h1>
            
            <div class="notice notice-warning">
                <p><strong><?php _e('Important:', 'bricks-form-2-webhook'); ?></strong> 
                <?php _e('Don\'t forget to set \'Custom\' action in your Bricks form settings!', 'bricks-form-2-webhook'); ?></p>
            </div>
            
            <!-- Add/Edit Webhook Form -->
            <div class="bf2w-add-webhook">
                <h2><?php echo $editing_webhook ? __('Edit Form Webhook', 'bricks-form-2-webhook') : __('Add New Form Webhook', 'bricks-form-2-webhook'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field($editing_webhook ? 'bf2w_edit_webhook' : 'bf2w_add_webhook', 'bf2w_nonce'); ?>
                    
                    <?php if ($editing_webhook): ?>
                        <input type="hidden" name="webhook_id" value="<?php echo esc_attr($editing_id); ?>" />
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="form_id"><?php _e('Form ID', 'bricks-form-2-webhook'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="form_id" 
                                       name="form_id" 
                                       value="<?php echo $editing_webhook ? esc_attr($editing_webhook['form_id']) : ''; ?>" 
                                       class="regular-text" 
                                       required />
                                <p class="description"><?php _e('Enter the form ID (without "bricks-element-" prefix). Example: if CSS ID is "bricks-element-fszxsr", enter "fszxsr"', 'bricks-form-2-webhook'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="webhook_url"><?php _e('Webhook URL', 'bricks-form-2-webhook'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="webhook_url" 
                                       name="webhook_url" 
                                       value="<?php echo $editing_webhook ? esc_attr($editing_webhook['webhook_url']) : ''; ?>" 
                                       class="regular-text" 
                                       required />
                                <p class="description"><?php _e('Enter the webhook URL where form data will be sent.', 'bricks-form-2-webhook'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="success_message"><?php _e('Success Message', 'bricks-form-2-webhook'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="success_message" 
                                       name="success_message" 
                                       value="<?php echo $editing_webhook ? esc_attr($editing_webhook['success_message']) : __('Data successfully sent', 'bricks-form-2-webhook'); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Message shown to users when form is submitted successfully.', 'bricks-form-2-webhook'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="error_message"><?php _e('Error Message', 'bricks-form-2-webhook'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="error_message" 
                                       name="error_message" 
                                       value="<?php echo $editing_webhook ? esc_attr($editing_webhook['error_message']) : __('Error sending data', 'bricks-form-2-webhook'); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Message shown to users when form submission fails.', 'bricks-form-2-webhook'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <?php if ($editing_webhook): ?>
                            <input type="submit" name="bf2w_edit_webhook" class="button-primary" value="<?php _e('Update Webhook', 'bricks-form-2-webhook'); ?>" />
                            <a href="<?php echo admin_url('admin.php?page=bricks-form-2-webhook'); ?>" class="button"><?php _e('Cancel', 'bricks-form-2-webhook'); ?></a>
                        <?php else: ?>
                            <input type="submit" name="bf2w_add_webhook" class="button-primary" value="<?php _e('Add Webhook', 'bricks-form-2-webhook'); ?>" />
                        <?php endif; ?>
                    </p>
                </form>
            </div>
            
            <!-- Debug Options -->
            <div class="bf2w-debug-options">
                <h2><?php _e('Debug Options', 'bricks-form-2-webhook'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('bf2w_toggle_debug', 'bf2w_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Debug Mode', 'bricks-form-2-webhook'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="debug_mode" 
                                           value="1" 
                                           <?php checked(!empty($options['debug_mode'])); ?> />
                                    <?php _e('Enable debug logging for troubleshooting', 'bricks-form-2-webhook'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="bf2w_toggle_debug" class="button" value="<?php _e('Save Settings', 'bricks-form-2-webhook'); ?>" />
                    </p>
                </form>
            </div>
            
            <!-- Existing Webhooks -->
            <?php if (!empty($options['forms'])): ?>
            <div class="bf2w-existing-webhooks">
                <h2><?php _e('Existing Form Webhooks', 'bricks-form-2-webhook'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Form ID', 'bricks-form-2-webhook'); ?></th>
                            <th scope="col"><?php _e('Webhook URL', 'bricks-form-2-webhook'); ?></th>
                            <th scope="col"><?php _e('Success Message', 'bricks-form-2-webhook'); ?></th>
                            <th scope="col"><?php _e('Error Message', 'bricks-form-2-webhook'); ?></th>
                            <th scope="col"><?php _e('Actions', 'bricks-form-2-webhook'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($options['forms'] as $index => $webhook): ?>
                        <tr>
                            <td><code><?php echo esc_html($webhook['form_id']); ?></code></td>
                            <td>
                                <a href="<?php echo esc_url($webhook['webhook_url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html(wp_trim_words($webhook['webhook_url'], 8, '...')); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </td>
                            <td><?php echo esc_html($webhook['success_message']); ?></td>
                            <td><?php echo esc_html($webhook['error_message']); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=bricks-form-2-webhook&edit=' . $index); ?>" 
                                   class="button button-small"><?php _e('Edit', 'bricks-form-2-webhook'); ?></a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=bricks-form-2-webhook&action=delete&webhook_id=' . $index), 'bf2w_delete_webhook'); ?>" 
                                   class="button button-small button-link-delete" 
                                   onclick="return confirm('<?php _e('Are you sure you want to delete this webhook?', 'bricks-form-2-webhook'); ?>')"><?php _e('Delete', 'bricks-form-2-webhook'); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Setup Instructions -->
            <div class="bf2w-setup-instructions">
                <h2><?php _e('Setup Instructions', 'bricks-form-2-webhook'); ?></h2>
                <ol>
                    <li><?php _e('Add form webhook configurations above (Form ID + Webhook URL)', 'bricks-form-2-webhook'); ?></li>
                    <li><?php _e('Copy the Bricks form ID from form CSS ID (e.g., if CSS ID is "bricks-element-fszxsr", form ID is "fszxsr")', 'bricks-form-2-webhook'); ?></li>
                    <li><?php _e('Enter the webhook URL for each form', 'bricks-form-2-webhook'); ?></li>
                    <li><?php _e('Go to your Bricks form settings', 'bricks-form-2-webhook'); ?></li>
                    <li><?php _e('Add new action and select "Custom"', 'bricks-form-2-webhook'); ?></li>
                    <li><?php _e('Save everything and test your forms', 'bricks-form-2-webhook'); ?></li>
                </ol>
            </div>
            
            <?php $this->display_debug_info(); ?>
        </div>
        <?php
    }
    
    private function display_debug_info() {
        $options = get_option('bf2w_settings', array());
        
        if (empty($options['debug_mode'])) {
            return;
        }
        
        $debug_info = get_transient('bf2w_debug_info');
        
        if (!$debug_info) {
            return;
        }
        
        ?>
        <div class="bf2w-debug-info">
            <h2><?php _e('Last Submission Debug Info', 'bricks-form-2-webhook'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Timestamp', 'bricks-form-2-webhook'); ?></strong></td>
                        <td><?php echo esc_html($debug_info['timestamp']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Form ID Received', 'bricks-form-2-webhook'); ?></strong></td>
                        <td><?php echo esc_html($debug_info['form_id_received']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Form Config Found', 'bricks-form-2-webhook'); ?></strong></td>
                        <td><?php echo $debug_info['form_config_found'] ? '<span style="color: green;">✓</span>' : '<span style="color: red;">✗</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Configured Forms', 'bricks-form-2-webhook'); ?></strong></td>
                        <td><?php echo esc_html(implode(', ', $debug_info['configured_forms'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Webhook URL', 'bricks-form-2-webhook'); ?></strong></td>
                        <td><?php echo esc_html($debug_info['webhook_url']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Form Data', 'bricks-form-2-webhook'); ?></strong></td>
                        <td><pre><?php echo esc_html(json_encode($debug_info['form_data'], JSON_PRETTY_PRINT)); ?></pre></td>
                    </tr>
                    <?php if ($debug_info['response']): ?>
                    <tr>
                        <td><strong><?php _e('Response Code', 'bricks-form-2-webhook'); ?></strong></td>
                        <td><?php echo esc_html($debug_info['response']['code']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Response Body', 'bricks-form-2-webhook'); ?></strong></td>
                        <td><pre><?php echo esc_html($debug_info['response']['body']); ?></pre></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($debug_info['error']): ?>
                    <tr>
                        <td><strong><?php _e('Error', 'bricks-form-2-webhook'); ?></strong></td>
                        <td style="color: red;"><?php echo esc_html($debug_info['error']); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    // Auto-update functionality
    public function check_for_plugin_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_slug = plugin_basename(__FILE__);
        $plugin_data = get_plugin_data(__FILE__);
        $remote_version = $this->get_remote_version();
        
        if (version_compare($plugin_data['Version'], $remote_version, '<')) {
            $transient->response[$plugin_slug] = (object) array(
                'slug' => dirname($plugin_slug),
                'plugin' => $plugin_slug,
                'new_version' => $remote_version,
                'url' => 'https://github.com/' . BF2W_GITHUB_REPO,
                'package' => 'https://github.com/' . BF2W_GITHUB_REPO . '/archive/refs/heads/main.zip'
            );
        }
        
        return $transient;
    }
    
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return false;
        }
        
        if (dirname(plugin_basename(__FILE__)) !== $args->slug) {
            return false;
        }
        
        $plugin_data = get_plugin_data(__FILE__);
        
        return (object) array(
            'slug' => dirname(plugin_basename(__FILE__)),
            'plugin_name' => $plugin_data['Name'],
            'version' => $this->get_remote_version(),
            'author' => $plugin_data['AuthorName'],
            'author_profile' => $plugin_data['AuthorURI'],
            'homepage' => $plugin_data['PluginURI'],
            'short_description' => $plugin_data['Description'],
            'sections' => array(
                'description' => $plugin_data['Description'],
            ),
            'download_link' => 'https://github.com/' . BF2W_GITHUB_REPO . '/archive/refs/heads/main.zip'
        );
    }
    
    private function get_remote_version() {
        $request = wp_remote_get('https://api.github.com/repos/' . BF2W_GITHUB_REPO . '/releases/latest');
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            if (isset($data['tag_name'])) {
                return ltrim($data['tag_name'], 'v');
            }
        }
        
        return BF2W_VERSION;
    }
}

// Initialize the plugin
new BricksForm2Webhook();

// Activation hook
register_activation_hook(__FILE__, 'bf2w_activate');
function bf2w_activate() {
    // Migrate old settings to new format
    $old_options = get_option('bf2w_settings');
    
    if ($old_options && isset($old_options['form_id']) && isset($old_options['webhook_url'])) {
        // Convert old single form to new array format
        $new_options = array(
            'forms' => array(
                array(
                    'form_id' => $old_options['form_id'],
                    'webhook_url' => $old_options['webhook_url'],
                    'success_message' => isset($old_options['success_message']) 
                        ? $old_options['success_message'] 
                        : __('Data successfully sent', 'bricks-form-2-webhook'),
                    'error_message' => isset($old_options['error_message']) 
                        ? $old_options['error_message'] 
                        : __('Error sending data', 'bricks-form-2-webhook')
                )
            ),
            'debug_mode' => false
        );
        update_option('bf2w_settings', $new_options);
    } else {
        // Set default options for new installation
        $default_options = array(
            'forms' => array(),
            'debug_mode' => false
        );
        add_option('bf2w_settings', $default_options);
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'bf2w_deactivate');
function bf2w_deactivate() {
    // Clean up transients
    delete_transient('bf2w_debug_info');
} 