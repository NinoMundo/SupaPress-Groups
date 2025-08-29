<?php

if (!defined('ABSPATH')) {
    exit;
}

class SupaPress_Groups_Config {
    
    private static $instance = null;
    private $options;
    private $core_config;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->options = get_option('supapress_groups_settings', []);
        $this->core_config = SupaPress_Config::getInstance();
    }
    
    /**
     * Get SupaPress Core instance for direct access
     */
    public function getCoreInstance() {
        return SupaPress::getInstance();
    }
    
    /**
     * Get Core configuration instance
     */
    public function getCoreConfig() {
        return $this->core_config;
    }
    
    public function getSupabaseUrl() {
        return $this->core_config->getSupabaseUrl();
    }
    
    public function getSupabaseServiceKey() {
        return $this->core_config->getSupabaseServiceKey();
    }
    
    public function getSupabaseAnonKey() {
        return $this->core_config->getSupabaseAnonKey();
    }
    
    public function isGroupsSyncEnabled() {
        return isset($this->options['enable_groups_sync']) && $this->options['enable_groups_sync'];
    }
    
    public function isMessagesSyncEnabled() {
        return isset($this->options['enable_messages_sync']) && $this->options['enable_messages_sync'];
    }
    
    public function isChatSyncEnabled() {
        return isset($this->options['enable_chat_sync']) && $this->options['enable_chat_sync'];
    }
    
    public function getSyncDirection() {
        return $this->options['sync_direction'] ?? 'both';
    }
    
    public function getGroupsSyncPrivacy() {
        return $this->options['groups_sync_privacy'] ?? 'public_only';
    }
    
    public function getMessagesSyncLimit() {
        return intval($this->options['messages_sync_limit'] ?? 1000);
    }
    
    public function isRealTimeSyncEnabled() {
        return isset($this->options['real_time_sync']) && $this->options['real_time_sync'];
    }
    
    public function getBatchSize() {
        return intval($this->options['batch_size'] ?? 20);
    }
    
    public function getRetryAttempts() {
        return intval($this->options['retry_attempts'] ?? 3);
    }
    
    public function getAllSettings() {
        return $this->options;
    }
    
    public function updateSettings($settings) {
        $this->options = array_merge($this->options, $settings);
        return update_option('supapress_groups_settings', $this->options);
    }
    
    public function isConfigured() {
        return $this->core_config->isConfigured();
    }
    
    public function canSyncGroups() {
        return $this->isConfigured() && 
               $this->isGroupsSyncEnabled() && 
               bp_is_active('groups');
    }
    
    public function canSyncMessages() {
        return $this->isConfigured() && 
               $this->isMessagesSyncEnabled() && 
               bp_is_active('messages');
    }
    
    public function canSyncChats() {
        return $this->isConfigured() && 
               $this->isChatSyncEnabled() && 
               function_exists('youzify');
    }
    
    public function getSyncableGroupTypes() {
        $privacy = $this->getGroupsSyncPrivacy();
        
        switch ($privacy) {
            case 'public_only':
                return ['public'];
            case 'public_private':
                return ['public', 'private'];
            case 'all':
                return ['public', 'private', 'hidden'];
            default:
                return ['public'];
        }
    }
    
    /**
     * Get Supabase ID for WordPress user via Core
     */
    public function getUserSupabaseId($wpUserId) {
        return $this->getCoreInstance()->getUserSupabaseId($wpUserId);
    }
    
    /**
     * Check if user is synced to Supabase via Core
     */
    public function isUserSynced($wpUserId) {
        return $this->getCoreInstance()->isUserSynced($wpUserId);
    }
    
    /**
     * Get Core API client instance
     */
    public function getCoreApiClient() {
        return $this->getCoreInstance()->getApiClient();
    }
    
    /**
     * Get Core logger instance
     */
    public function getCoreLogger() {
        return $this->getCoreInstance()->getLogger();
    }
    
    /**
     * Get Core user model instance
     */
    public function getCoreUserModel() {
        return $this->getCoreInstance()->getUserModel();
    }
    
    /**
     * Get batch user Supabase IDs via Core
     */
    public function getBatchUserSupabaseIds($wpUserIds) {
        return $this->getCoreUserModel()->getBatchUserSupabaseIds($wpUserIds);
    }
}