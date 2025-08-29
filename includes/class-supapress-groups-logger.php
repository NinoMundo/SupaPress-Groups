<?php

if (!defined('ABSPATH')) {
    exit;
}

class SupaPress_Groups_Logger {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }
    
    public function debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log('debug', $message, $context);
        }
    }
    
    private function log($level, $message, $context) {
        $log_entry = sprintf(
            '[%s] SupaPress Groups (%s): %s',
            current_time('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= ' ' . json_encode($context);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_entry);
        }
        
        if (function_exists('wp_cache_set')) {
            $cached_logs = wp_cache_get('supapress_groups_recent_logs', 'supapress_groups') ?: [];
            $cached_logs[] = [
                'time' => current_time('mysql'),
                'level' => $level,
                'message' => $message,
                'context' => $context
            ];
            
            if (count($cached_logs) > 50) {
                array_shift($cached_logs);
            }
            
            wp_cache_set('supapress_groups_recent_logs', $cached_logs, 'supapress_groups', 300);
        }
    }
    
    public function getRecentLogs($limit = 20) {
        $logs = wp_cache_get('supapress_groups_recent_logs', 'supapress_groups') ?: [];
        return array_slice(array_reverse($logs), 0, $limit);
    }
    
    public function clearOldLogs($days = 30) {
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            $lines = file($log_file);
            $filtered_lines = [];
            
            foreach ($lines as $line) {
                if (strpos($line, 'SupaPress Groups') !== false) {
                    preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $matches);
                    if (isset($matches[1]) && $matches[1] < $cutoff_date) {
                        continue;
                    }
                }
                $filtered_lines[] = $line;
            }
            
            file_put_contents($log_file, implode('', $filtered_lines), LOCK_EX);
        }
        
        wp_cache_delete('supapress_groups_recent_logs', 'supapress_groups');
    }
}