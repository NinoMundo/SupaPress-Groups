<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables_to_drop = [
    $wpdb->prefix . 'supapress_groups_sync_log',
    $wpdb->prefix . 'supapress_groups_mapping'
];

foreach ($tables_to_drop as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

delete_option('supapress_groups_settings');
delete_option('supapress_groups_version');

wp_clear_scheduled_hook('supapress_groups_cleanup_logs');