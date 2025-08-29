<?php

if (!defined('ABSPATH')) {
    exit;
}

class SupaPress_Groups_Admin {
    
    private $config;
    private $business;
    private $model;
    
    public function __construct() {
        $this->config = SupaPress_Groups_Config::getInstance();
        $this->business = new SupaPress_Groups_Business();
        $this->model = new SupaPress_Groups_Model();
        
        $this->initHooks();
    }
    
    private function initHooks() {
        add_action('supapress_admin_menu_items', [$this, 'addAdminMenuItems']);
        add_action('admin_init', [$this, 'initSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        
        add_action('wp_ajax_supapress_groups_save_settings', [$this, 'saveSettings']);
        add_action('wp_ajax_supapress_groups_test_sync', [$this, 'testSync']);
        add_action('wp_ajax_supapress_groups_bulk_sync', [$this, 'bulkSync']);
    }
    
    public function addAdminMenuItems() {
        add_submenu_page(
            'supapress',
            __('Groups Settings', 'supapress-groups'),
            __('Groups', 'supapress-groups'),
            'manage_options',
            'supapress-groups',
            [$this, 'renderGroupsPage']
        );
    }
    
    public function initSettings() {
        register_setting('supapress_groups_settings', 'supapress_groups_settings', [$this, 'validateSettings']);
        
        add_settings_section(
            'supapress_groups_sync',
            __('Groups & Messages Sync', 'supapress-groups'),
            [$this, 'renderSyncSection'],
            'supapress-groups'
        );
        
        add_settings_field(
            'enable_groups_sync',
            __('Enable Groups Sync', 'supapress-groups'),
            [$this, 'renderCheckboxField'],
            'supapress-groups',
            'supapress_groups_sync',
            ['field' => 'enable_groups_sync']
        );
        
        add_settings_field(
            'enable_messages_sync',
            __('Enable Messages Sync', 'supapress-groups'),
            [$this, 'renderCheckboxField'],
            'supapress-groups',
            'supapress_groups_sync',
            ['field' => 'enable_messages_sync']
        );
        
        add_settings_field(
            'enable_chat_sync',
            __('Enable Chat Sync (Youzify)', 'supapress-groups'),
            [$this, 'renderCheckboxField'],
            'supapress-groups',
            'supapress_groups_sync',
            ['field' => 'enable_chat_sync']
        );
        
        add_settings_field(
            'groups_sync_privacy',
            __('Groups Privacy Level', 'supapress-groups'),
            [$this, 'renderSelectField'],
            'supapress-groups',
            'supapress_groups_sync',
            [
                'field' => 'groups_sync_privacy',
                'options' => [
                    'public_only' => __('Public groups only', 'supapress-groups'),
                    'public_private' => __('Public and private groups', 'supapress-groups'),
                    'all' => __('All groups (including hidden)', 'supapress-groups')
                ]
            ]
        );
        
        add_settings_field(
            'real_time_sync',
            __('Real-time Sync', 'supapress-groups'),
            [$this, 'renderCheckboxField'],
            'supapress-groups',
            'supapress_groups_sync',
            ['field' => 'real_time_sync']
        );
        
        add_settings_field(
            'batch_size',
            __('Batch Size', 'supapress-groups'),
            [$this, 'renderNumberField'],
            'supapress-groups',
            'supapress_groups_sync',
            ['field' => 'batch_size', 'min' => 5, 'max' => 100, 'default' => 20]
        );
    }
    
    public function renderGroupsPage() {
        $logs = $this->model->getSyncLogs(20);
        $lastGroupsSync = $this->model->getLastSyncTime('group');
        $lastMessagesSync = $this->model->getLastSyncTime('message');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="supapress-groups-header">
                <div class="connection-status">
                    <button type="button" id="test-groups-sync" class="button">
                        <?php _e('Test Groups Sync', 'supapress-groups'); ?>
                    </button>
                    
                    <div class="sync-status">
                        <strong><?php _e('Last Groups Sync:', 'supapress-groups'); ?></strong>
                        <?php echo $lastGroupsSync ? esc_html($lastGroupsSync) : __('Never', 'supapress-groups'); ?>
                        <br>
                        <strong><?php _e('Last Messages Sync:', 'supapress-groups'); ?></strong>
                        <?php echo $lastMessagesSync ? esc_html($lastMessagesSync) : __('Never', 'supapress-groups'); ?>
                    </div>
                </div>
                
                <div id="test-results"></div>
            </div>
            
            <div class="supapress-groups-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#settings" class="nav-tab nav-tab-active"><?php _e('Settings', 'supapress-groups'); ?></a>
                    <a href="#sync" class="nav-tab"><?php _e('Sync', 'supapress-groups'); ?></a>
                    <a href="#logs" class="nav-tab"><?php _e('Logs', 'supapress-groups'); ?></a>
                </nav>
                
                <div id="settings" class="tab-content active">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('supapress_groups_settings');
                        do_settings_sections('supapress-groups');
                        submit_button();
                        ?>
                    </form>
                    
                    <div class="supapress-info">
                        <h3><?php _e('Requirements', 'supapress-groups'); ?></h3>
                        <ul>
                            <li><?php echo bp_is_active('groups') ? '✅' : '❌'; ?> <?php _e('BuddyPress Groups', 'supapress-groups'); ?></li>
                            <li><?php echo bp_is_active('messages') ? '✅' : '❌'; ?> <?php _e('BuddyPress Messages', 'supapress-groups'); ?></li>
                            <li><?php echo function_exists('youzify') ? '✅' : '❌'; ?> <?php _e('Youzify (Optional)', 'supapress-groups'); ?></li>
                            <li><?php echo $this->config->isConfigured() ? '✅' : '❌'; ?> <?php _e('SupaPress Core Configured', 'supapress-groups'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <div id="sync" class="tab-content">
                    <div class="sync-controls">
                        <h3><?php _e('Manual Sync Operations', 'supapress-groups'); ?></h3>
                        
                        <div class="sync-actions">
                            <button type="button" id="sync-groups" class="button button-primary">
                                <?php _e('Sync Groups to Supabase', 'supapress-groups'); ?>
                            </button>
                            
                            <button type="button" id="sync-messages" class="button button-primary">
                                <?php _e('Sync Messages to Supabase', 'supapress-groups'); ?>
                            </button>
                            
                            <button type="button" id="sync-all" class="button button-secondary">
                                <?php _e('Sync All (Groups + Messages)', 'supapress-groups'); ?>
                            </button>
                        </div>
                        
                        <div id="sync-results"></div>
                    </div>
                </div>
                
                <div id="logs" class="tab-content">
                    <h3><?php _e('Sync Logs', 'supapress-groups'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Time', 'supapress-groups'); ?></th>
                                <th><?php _e('Type', 'supapress-groups'); ?></th>
                                <th><?php _e('Entity ID', 'supapress-groups'); ?></th>
                                <th><?php _e('Direction', 'supapress-groups'); ?></th>
                                <th><?php _e('Status', 'supapress-groups'); ?></th>
                                <th><?php _e('Message', 'supapress-groups'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6"><?php _e('No sync logs found.', 'supapress-groups'); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html($log['sync_time']); ?></td>
                                        <td><?php echo esc_html($log['entity_type']); ?></td>
                                        <td><?php echo esc_html($log['entity_id']); ?></td>
                                        <td><?php echo esc_html($log['sync_direction']); ?></td>
                                        <td>
                                            <span class="sync-status-<?php echo esc_attr($log['sync_status']); ?>">
                                                <?php echo esc_html($log['sync_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($log['error_message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function renderSyncSection() {
        echo '<p>' . __('Configure synchronization between BuddyPress groups/messages and Supabase.', 'supapress-groups') . '</p>';
    }
    
    public function renderCheckboxField($args) {
        $settings = $this->config->getAllSettings();
        $value = $settings[$args['field']] ?? false;
        
        printf(
            '<input type="checkbox" id="%s" name="supapress_groups_settings[%s]" value="1" %s />',
            esc_attr($args['field']),
            esc_attr($args['field']),
            checked(1, $value, false)
        );
    }
    
    public function renderSelectField($args) {
        $settings = $this->config->getAllSettings();
        $value = $settings[$args['field']] ?? '';
        
        printf('<select id="%s" name="supapress_groups_settings[%s]">', esc_attr($args['field']), esc_attr($args['field']));
        
        foreach ($args['options'] as $optionValue => $optionLabel) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($optionValue),
                selected($value, $optionValue, false),
                esc_html($optionLabel)
            );
        }
        
        echo '</select>';
    }
    
    public function renderNumberField($args) {
        $settings = $this->config->getAllSettings();
        $value = $settings[$args['field']] ?? $args['default'];
        
        printf(
            '<input type="number" id="%s" name="supapress_groups_settings[%s]" value="%s" min="%d" max="%d" class="small-text" />',
            esc_attr($args['field']),
            esc_attr($args['field']),
            esc_attr($value),
            intval($args['min']),
            intval($args['max'])
        );
    }
    
    public function validateSettings($input) {
        $validated = [];
        
        $validated['enable_groups_sync'] = !empty($input['enable_groups_sync']);
        $validated['enable_messages_sync'] = !empty($input['enable_messages_sync']);
        $validated['enable_chat_sync'] = !empty($input['enable_chat_sync']);
        $validated['real_time_sync'] = !empty($input['real_time_sync']);
        
        $validated['groups_sync_privacy'] = in_array($input['groups_sync_privacy'] ?? '', ['public_only', 'public_private', 'all']) 
            ? $input['groups_sync_privacy'] : 'public_only';
            
        $validated['batch_size'] = max(5, min(100, intval($input['batch_size'] ?? 20)));
        
        return $validated;
    }
    
    public function testSync() {
        check_ajax_referer('supapress_groups_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->business->testSync();
        wp_send_json($result);
    }
    
    public function bulkSync() {
        check_ajax_referer('supapress_groups_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $syncType = sanitize_text_field($_POST['sync_type'] ?? '');
        
        switch ($syncType) {
            case 'groups':
                $result = $this->business->bulkSyncGroups();
                break;
            case 'messages':
                $result = $this->business->bulkSyncMessages();
                break;
            case 'all':
                $groupsResult = $this->business->bulkSyncGroups();
                $messagesResult = $this->business->bulkSyncMessages();
                $result = [
                    'success' => $groupsResult['success'] && $messagesResult['success'],
                    'data' => [
                        'groups' => $groupsResult['data'],
                        'messages' => $messagesResult['data']
                    ]
                ];
                break;
            default:
                $result = ['success' => false, 'message' => 'Invalid sync type'];
        }
        
        wp_send_json($result);
    }
    
    public function enqueueScripts($hook) {
        if (strpos($hook, 'supapress-groups') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->getAdminScript());
        wp_add_inline_style('wp-admin', $this->getAdminStyles());
    }
    
    private function getAdminScript() {
        return "
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
            
            $('#test-groups-sync').click(function() {
                var button = $(this);
                var results = $('#test-results');
                
                button.prop('disabled', true).text('" . __('Testing...', 'supapress-groups') . "');
                results.html('');
                
                $.post(ajaxurl, {
                    action: 'supapress_groups_test_sync',
                    nonce: '" . wp_create_nonce('supapress_groups_admin_nonce') . "'
                }, function(response) {
                    button.prop('disabled', false).text('" . __('Test Groups Sync', 'supapress-groups') . "');
                    
                    if (response.success) {
                        var html = '<div class=\"notice notice-success inline\"><p>✓ " . __('Tests passed', 'supapress-groups') . "</p></div>';
                    } else {
                        var html = '<div class=\"notice notice-error inline\"><p>✗ " . __('Tests failed', 'supapress-groups') . ": ' + response.message + '</p></div>';
                    }
                    
                    results.html(html);
                    setTimeout(function() { results.fadeOut(); }, 5000);
                });
            });
            
            $('.sync-actions button').click(function() {
                var button = $(this);
                var syncType = button.attr('id').replace('sync-', '');
                var results = $('#sync-results');
                
                button.prop('disabled', true);
                results.html('<div class=\"notice notice-info\"><p>" . __('Syncing...', 'supapress-groups') . "</p></div>');
                
                $.post(ajaxurl, {
                    action: 'supapress_groups_bulk_sync',
                    sync_type: syncType,
                    nonce: '" . wp_create_nonce('supapress_groups_admin_nonce') . "'
                }, function(response) {
                    button.prop('disabled', false);
                    
                    if (response.success) {
                        results.html('<div class=\"notice notice-success\"><p>" . __('Sync completed successfully', 'supapress-groups') . "</p></div>');
                    } else {
                        results.html('<div class=\"notice notice-error\"><p>" . __('Sync failed', 'supapress-groups') . ": ' + response.message + '</p></div>');
                    }
                    
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                });
            });
        });
        ";
    }
    
    private function getAdminStyles() {
        return "
        .supapress-groups-header {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .connection-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .sync-actions {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        
        .sync-status-success {
            color: #00a32a;
            font-weight: bold;
        }
        
        .sync-status-error {
            color: #d63638;
            font-weight: bold;
        }
        
        .supapress-info {
            background: #f0f6fc;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .supapress-info ul {
            margin: 10px 0;
            list-style: none;
        }
        
        .supapress-info li {
            margin: 5px 0;
        }
        ";
    }
}