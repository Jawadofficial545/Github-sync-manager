<?php
/**
 * Plugin Name: GitHub Sync Manager
 * Plugin URI: https://github.com/yourusername/github-sync-manager
 * Description: Sync and update WordPress plugins directly from GitHub repositories
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: github-sync-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_Sync_Manager {
    
    private $option_name = 'github_sync_repos';
    private $menu_slug = 'github-sync-manager';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_github_sync_check_update', array($this, 'ajax_check_update'));
        add_action('wp_ajax_github_sync_install', array($this, 'ajax_install_update'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add custom update checker
        add_filter('site_transient_update_plugins', array($this, 'check_for_plugin_updates'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'GitHub Sync Manager',
            'GitHub Sync',
            'manage_options',
            $this->menu_slug,
            array($this, 'settings_page'),
            'dashicons-update',
            100
        );
    }
    
    public function register_settings() {
        register_setting('github_sync_settings', $this->option_name);
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_' . $this->menu_slug) {
            return;
        }
        
        wp_enqueue_style('github-sync-admin', plugin_dir_url(__FILE__) . 'admin-style.css', array(), '1.0.0');
        wp_enqueue_script('github-sync-admin', plugin_dir_url(__FILE__) . 'admin-script.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('github-sync-admin', 'githubSync', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('github_sync_nonce')
        ));
    }
    
    public function settings_page() {
        $repos = get_option($this->option_name, array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="github-sync-container">
                <div class="github-sync-card">
                    <h2>Add New Repository</h2>
                    <form method="post" action="" id="github-sync-form">
                        <?php wp_nonce_field('github_sync_add', 'github_sync_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="plugin_slug">Plugin Slug</label>
                                </th>
                                <td>
                                    <input type="text" name="plugin_slug" id="plugin_slug" class="regular-text" placeholder="my-plugin" required>
                                    <p class="description">The folder name of your plugin (e.g., my-plugin)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="github_repo">GitHub Repository</label>
                                </th>
                                <td>
                                    <input type="text" name="github_repo" id="github_repo" class="regular-text" placeholder="username/repository" required>
                                    <p class="description">Format: username/repository (e.g., john/my-plugin)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="github_token">GitHub Token (Optional)</label>
                                </th>
                                <td>
                                    <input type="password" name="github_token" id="github_token" class="regular-text" placeholder="ghp_xxxxxxxxxxxx">
                                    <p class="description">For private repositories. Get token from GitHub Settings → Developer settings → Personal access tokens</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="branch">Branch</label>
                                </th>
                                <td>
                                    <input type="text" name="branch" id="branch" class="regular-text" value="main" required>
                                    <p class="description">Git branch to sync from (default: main)</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary" name="github_sync_add">Add Repository</button>
                        </p>
                    </form>
                </div>
                
                <?php if (!empty($repos)): ?>
                <div class="github-sync-card">
                    <h2>Linked Repositories</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Plugin</th>
                                <th>GitHub Repository</th>
                                <th>Branch</th>
                                <th>Current Version</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repos as $slug => $repo): ?>
                            <tr data-slug="<?php echo esc_attr($slug); ?>">
                                <td><strong><?php echo esc_html($slug); ?></strong></td>
                                <td>
                                    <a href="https://github.com/<?php echo esc_html($repo['github_repo']); ?>" target="_blank">
                                        <?php echo esc_html($repo['github_repo']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($repo['branch']); ?></td>
                                <td class="current-version">
                                    <?php 
                                    $version = $this->get_plugin_version($slug);
                                    echo $version ? esc_html($version) : 'N/A';
                                    ?>
                                </td>
                                <td class="sync-status">
                                    <span class="status-badge status-checking">Checking...</span>
                                </td>
                                <td>
                                    <button class="button button-small check-update" data-slug="<?php echo esc_attr($slug); ?>">
                                        Check Update
                                    </button>
                                    <button class="button button-primary button-small install-update" data-slug="<?php echo esc_attr($slug); ?>" style="display:none;">
                                        Update Now
                                    </button>
                                    <button class="button button-small button-link-delete remove-repo" data-slug="<?php echo esc_attr($slug); ?>">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="github-sync-card">
                    <h2>How to Use</h2>
                    <ol>
                        <li><strong>Add your plugin's GitHub repository</strong> using the form above</li>
                        <li><strong>Make sure your plugin has a valid header</strong> with Version information in the main PHP file</li>
                        <li><strong>Create releases on GitHub</strong> with semantic versioning (e.g., v1.0.0)</li>
                        <li><strong>Click "Check Update"</strong> to see if a newer version is available</li>
                        <li><strong>Click "Update Now"</strong> to download and install the latest version</li>
                    </ol>
                    
                    <h3>For Private Repositories</h3>
                    <p>Generate a GitHub Personal Access Token:</p>
                    <ol>
                        <li>Go to GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)</li>
                        <li>Click "Generate new token (classic)"</li>
                        <li>Give it a name and select the "repo" scope</li>
                        <li>Copy the token and paste it in the form above</li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
        
        // Handle form submission
        if (isset($_POST['github_sync_add']) && check_admin_referer('github_sync_add', 'github_sync_nonce')) {
            $this->add_repository();
        }
        
        // Handle repository removal
        if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['slug'])) {
            check_admin_referer('github_sync_remove_' . $_GET['slug']);
            $this->remove_repository($_GET['slug']);
        }
    }
    
    private function add_repository() {
        $repos = get_option($this->option_name, array());
        
        $slug = sanitize_text_field($_POST['plugin_slug']);
        $github_repo = sanitize_text_field($_POST['github_repo']);
        $github_token = sanitize_text_field($_POST['github_token']);
        $branch = sanitize_text_field($_POST['branch']);
        
        $repos[$slug] = array(
            'github_repo' => $github_repo,
            'github_token' => $github_token,
            'branch' => $branch,
            'last_checked' => time()
        );
        
        update_option($this->option_name, $repos);
        
        add_settings_error(
            'github_sync_messages',
            'github_sync_message',
            'Repository added successfully!',
            'success'
        );
    }
    
    private function remove_repository($slug) {
        $repos = get_option($this->option_name, array());
        
        if (isset($repos[$slug])) {
            unset($repos[$slug]);
            update_option($this->option_name, $repos);
            
            add_settings_error(
                'github_sync_messages',
                'github_sync_message',
                'Repository removed successfully!',
                'success'
            );
        }
    }
    
    public function ajax_check_update() {
        check_ajax_referer('github_sync_nonce', 'nonce');
        
        $slug = sanitize_text_field($_POST['slug']);
        $repos = get_option($this->option_name, array());
        
        if (!isset($repos[$slug])) {
            wp_send_json_error('Repository not found');
        }
        
        $repo = $repos[$slug];
        $latest_version = $this->get_latest_github_version($repo['github_repo'], $repo['github_token']);
        $current_version = $this->get_plugin_version($slug);
        
        if (!$latest_version) {
            wp_send_json_error('Could not fetch latest version from GitHub');
        }
        
        $needs_update = version_compare($latest_version, $current_version, '>');
        
        wp_send_json_success(array(
            'current_version' => $current_version,
            'latest_version' => $latest_version,
            'needs_update' => $needs_update
        ));
    }
    
    public function ajax_install_update() {
        check_ajax_referer('github_sync_nonce', 'nonce');
        
        $slug = sanitize_text_field($_POST['slug']);
        $repos = get_option($this->option_name, array());
        
        if (!isset($repos[$slug])) {
            wp_send_json_error('Repository not found');
        }
        
        $repo = $repos[$slug];
        $result = $this->download_and_install_plugin($slug, $repo);
        
        if ($result) {
            wp_send_json_success('Plugin updated successfully!');
        } else {
            wp_send_json_error('Failed to update plugin');
        }
    }
    
    private function get_latest_github_version($github_repo, $token = '') {
        $url = "https://api.github.com/repos/{$github_repo}/releases/latest";
        
        $args = array(
            'headers' => array(
                'User-Agent' => 'WordPress-GitHub-Sync'
            )
        );
        
        if (!empty($token)) {
            $args['headers']['Authorization'] = "token {$token}";
        }
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['tag_name'])) {
            return ltrim($body['tag_name'], 'v');
        }
        
        return false;
    }
    
    private function get_plugin_version($slug) {
        $plugin_file = WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';
        
        if (!file_exists($plugin_file)) {
            // Try to find the main plugin file
            $files = glob(WP_PLUGIN_DIR . '/' . $slug . '/*.php');
            foreach ($files as $file) {
                $plugin_data = get_file_data($file, array('Version' => 'Version'));
                if (!empty($plugin_data['Version'])) {
                    return $plugin_data['Version'];
                }
            }
            return false;
        }
        
        $plugin_data = get_file_data($plugin_file, array('Version' => 'Version'));
        return isset($plugin_data['Version']) ? $plugin_data['Version'] : false;
    }
    
    private function download_and_install_plugin($slug, $repo) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        
        $github_repo = $repo['github_repo'];
        $branch = $repo['branch'];
        $token = $repo['github_token'];
        
        // Get download URL
        $url = "https://api.github.com/repos/{$github_repo}/zipball/{$branch}";
        
        $args = array(
            'headers' => array(
                'User-Agent' => 'WordPress-GitHub-Sync'
            )
        );
        
        if (!empty($token)) {
            $args['headers']['Authorization'] = "token {$token}";
        }
        
        // Download the file
        $temp_file = download_url($url, 300, false, $args);
        
        if (is_wp_error($temp_file)) {
            return false;
        }
        
        // Unzip to temp directory
        $unzip_result = unzip_file($temp_file, WP_PLUGIN_DIR);
        @unlink($temp_file);
        
        if (is_wp_error($unzip_result)) {
            return false;
        }
        
        // GitHub creates a folder with format: username-repo-commit
        // We need to rename it to match the slug
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        
        // Find the extracted folder
        $files = glob(WP_PLUGIN_DIR . '/' . explode('/', $github_repo)[1] . '-*');
        
        if (!empty($files)) {
            $extracted_folder = $files[0];
            
            // Remove old plugin folder if exists
            if (is_dir($plugin_dir)) {
                $this->delete_directory($plugin_dir);
            }
            
            // Rename to correct slug
            rename($extracted_folder, $plugin_dir);
        }
        
        return true;
    }
    
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
    
    public function admin_notices() {
        settings_errors('github_sync_messages');
    }
    
    public function check_for_plugin_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $repos = get_option($this->option_name, array());
        
        foreach ($repos as $slug => $repo) {
            $plugin_file = $slug . '/' . $slug . '.php';
            
            $latest_version = $this->get_latest_github_version($repo['github_repo'], $repo['github_token']);
            $current_version = $this->get_plugin_version($slug);
            
            if ($latest_version && version_compare($latest_version, $current_version, '>')) {
                $plugin_data = array(
                    'slug' => $slug,
                    'new_version' => $latest_version,
                    'url' => "https://github.com/{$repo['github_repo']}",
                    'package' => "https://github.com/{$repo['github_repo']}/archive/{$repo['branch']}.zip"
                );
                
                $transient->response[$plugin_file] = (object) $plugin_data;
            }
        }
        
        return $transient;
    }
}

// Initialize the plugin
new GitHub_Sync_Manager();

// Installation and uninstallation hooks
register_activation_hook(__FILE__, 'github_sync_activate');
register_deactivation_hook(__FILE__, 'github_sync_deactivate');

function github_sync_activate() {
    // Add default options
    add_option('github_sync_repos', array());
}

function github_sync_deactivate() {
    // Cleanup if needed
}
