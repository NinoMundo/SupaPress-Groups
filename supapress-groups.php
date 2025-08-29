<?php
/**
 * Plugin Name: SupaPress Groups
 * Plugin URI: https://github.com/NinoMundo/supapress-groups
 * Description: Sync BuddyPress groups, private messages, and chats with Supabase for cross-platform community features.
 * Version: 1.0.0
 * Author: NinoMundo
 * License: GPL v2 or later
 * Text Domain: supapress-groups
 * Domain Path: /languages
 * Requires Plugins: supapress, buddypress
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SUPAPRESS_GROUPS_VERSION', '1.0.0');
define('SUPAPRESS_GROUPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUPAPRESS_GROUPS_PLUGIN_URL', plugin_dir_url(__FILE__));

class SupaPress_Groups {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        add_action('plugins_loaded', [$this, 'loadPlugin']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function loadPlugin() {
        if (!$this->checkDependencies()) {
            return;
        }
        
        $this->loadDependencies();
        $this->initHooks();
    }
    
    private function checkDependencies() {
        $missing = [];
        
        // Check if SupaPress plugin is active using multiple methods
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $supapress_active = is_plugin_active('supapress/supapress.php') || 
                           (defined('SUPAPRESS_VERSION') && class_exists('SupaPress'));
        
        if (!$supapress_active) {
            $missing[] = 'SupaPress';
        }
        
        if (!function_exists('bp_is_active')) {
            $missing[] = 'BuddyPress';
        }
        
        if (!empty($missing)) {
            add_action('admin_notices', function() use ($missing) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('SupaPress Groups requires the following plugins to be installed and active: %s', 'supapress-groups'),
                    implode(', ', $missing)
                );
                echo '</p></div>';
            });
            return false;
        }
        
        $supapress_version = defined('SUPAPRESS_VERSION') ? SUPAPRESS_VERSION : '0.0.0';
        if (version_compare($supapress_version, '1.1.0', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                _e('SupaPress Groups requires SupaPress v1.1.0 or higher. Please update SupaPress.', 'supapress-groups');
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    private function loadDependencies() {
        require_once SUPAPRESS_GROUPS_PLUGIN_DIR . 'includes/class-supapress-groups-config.php';
        require_once SUPAPRESS_GROUPS_PLUGIN_DIR . 'includes/class-supapress-groups-logger.php';
        require_once SUPAPRESS_GROUPS_PLUGIN_DIR . 'includes/class-supapress-groups-api.php';
        require_once SUPAPRESS_GROUPS_PLUGIN_DIR . 'includes/class-supapress-groups-model.php';
        require_once SUPAPRESS_GROUPS_PLUGIN_DIR . 'includes/class-supapress-groups-business.php';
        require_once SUPAPRESS_GROUPS_PLUGIN_DIR . 'includes/class-supapress-groups-admin.php';
        require_once SUPAPRESS_GROUPS_PLUGIN_DIR . 'includes/class-supapress-groups-hooks.php';
    }
    
    private function initHooks() {
        new SupaPress_Groups_Hooks();
        
        if (is_admin()) {
            new SupaPress_Groups_Admin();
        }
        
        add_action('supapress_groups_cleanup_logs', [$this, 'cleanupLogs']);
    }
    
    public function cleanupLogs() {
        $logger = SupaPress_Groups_Logger::getInstance();
        $logger->clearOldLogs(30);
    }
    
    public function activate() {
        if (!$this->checkDependencies()) {
            wp_die(__('SupaPress Groups cannot be activated because required dependencies are not met.', 'supapress-groups'));
        }
        
        $this->createTables();
        $this->setDefaultOptions();
        $this->scheduleCleanup();
        
        update_option('supapress_groups_version', SUPAPRESS_GROUPS_VERSION);
        
        if (class_exists('SupaPress_Groups_Logger')) {
            $logger = SupaPress_Groups_Logger::getInstance();
            $logger->info('SupaPress Groups plugin activated', ['version' => SUPAPRESS_GROUPS_VERSION]);
        }
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('supapress_groups_cleanup_logs');
        
        if (class_exists('SupaPress_Groups_Logger')) {
            $logger = SupaPress_Groups_Logger::getInstance();
            $logger->info('SupaPress Groups plugin deactivated');
        }
    }
    
    private function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $tables = [
            'supapress_groups_sync_log' => "CREATE TABLE {$wpdb->prefix}supapress_groups_sync_log (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                entity_type varchar(20) NOT NULL,
                entity_id bigint(20) NOT NULL,
                supabase_id varchar(255),
                sync_direction varchar(20) NOT NULL,
                sync_status varchar(20) NOT NULL,
                error_message text,
                sync_time datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY entity_type_id (entity_type, entity_id),
                KEY supabase_id (supabase_id)
            ) $charset_collate;",
            
            'supapress_groups_mapping' => "CREATE TABLE {$wpdb->prefix}supapress_groups_mapping (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                entity_type varchar(20) NOT NULL,
                wp_id bigint(20) NOT NULL,
                supabase_id varchar(255) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_mapping (entity_type, wp_id),
                KEY supabase_id (supabase_id)
            ) $charset_collate;"
        ];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
        }
    }
    
    private function setDefaultOptions() {
        $default_settings = [
            'enable_groups_sync' => false,
            'enable_messages_sync' => false,
            'enable_chat_sync' => false,
            'sync_direction' => 'both',
            'groups_sync_privacy' => 'public_only',
            'messages_sync_limit' => 1000,
            'real_time_sync' => true,
            'batch_size' => 20,
            'retry_attempts' => 3
        ];
        
        add_option('supapress_groups_settings', $default_settings);
    }
    
    private function scheduleCleanup() {
        if (!wp_next_scheduled('supapress_groups_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'supapress_groups_cleanup_logs');
        }
    }
}

SupaPress_Groups::getInstance();