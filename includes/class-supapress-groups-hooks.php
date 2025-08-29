<?php

if (!defined('ABSPATH')) {
    exit;
}

class SupaPress_Groups_Hooks {
    
    private $config;
    private $business;
    private $logger;
    
    public function __construct() {
        $this->config = SupaPress_Groups_Config::getInstance();
        $this->business = new SupaPress_Groups_Business();
        $this->logger = SupaPress_Groups_Logger::getInstance();
        
        $this->initHooks();
    }
    
    private function initHooks() {
        if ($this->config->isRealTimeSyncEnabled()) {
            $this->initGroupsHooks();
            $this->initMessagesHooks();
            $this->initYouzifyHooks();
        }
    }
    
    private function initGroupsHooks() {
        if (!$this->config->canSyncGroups()) {
            return;
        }
        
        // BuddyPress Groups hooks
        add_action('groups_group_after_save', [$this, 'onGroupSaved'], 10, 1);
        add_action('groups_before_delete_group', [$this, 'onGroupDeleted'], 10, 1);
        
        // Group membership hooks
        add_action('groups_member_after_save', [$this, 'onGroupMembershipChanged'], 10, 1);
        add_action('groups_member_before_remove', [$this, 'onGroupMemberRemoved'], 10, 2);
        add_action('groups_promoted_member', [$this, 'onGroupMemberPromoted'], 10, 2);
        add_action('groups_demoted_member', [$this, 'onGroupMemberDemoted'], 10, 2);
        
        // Group activity hooks
        add_action('bp_groups_posted_update', [$this, 'onGroupActivityPosted'], 10, 3);
    }
    
    private function initMessagesHooks() {
        if (!$this->config->canSyncMessages()) {
            return;
        }
        
        // BuddyPress Messages hooks
        add_action('messages_message_after_save', [$this, 'onMessageSent'], 10, 1);
        add_action('messages_thread_after_save', [$this, 'onThreadSaved'], 10, 1);
    }
    
    private function initYouzifyHooks() {
        if (!$this->config->canSyncChats()) {
            return;
        }
        
        // Youzify chat hooks (if available)
        if (has_action('youzify_chat_message_sent')) {
            add_action('youzify_chat_message_sent', [$this, 'onYouzifyChatMessage'], 10, 2);
        }
    }
    
    // Group event handlers
    public function onGroupSaved($group) {
        if (!$this->shouldSyncGroup($group->id)) {
            return;
        }
        
        $this->logger->debug('Group saved, triggering sync', ['group_id' => $group->id]);
        
        wp_schedule_single_event(
            time() + 5, // 5 second delay to ensure data consistency
            'supapress_groups_sync_group',
            [$group->id]
        );
        
        if (!wp_next_scheduled('supapress_groups_sync_group', [$group->id])) {
            add_action('supapress_groups_sync_group', [$this, 'syncGroupDelayed']);
        }
    }
    
    public function onGroupDeleted($group_id) {
        $supabaseId = $this->model->getSupabaseId('group', $group_id);
        
        if ($supabaseId) {
            $this->logger->info('Group deleted, removing from Supabase', ['group_id' => $group_id]);
            
            $api = new SupaPress_Groups_API();
            $result = $api->deleteGroup($supabaseId);
            
            if ($result['success']) {
                $this->model->removeMapping('group', $group_id);
            }
            
            do_action('supapress_groups_group_deleted_from_supabase', $group_id, $result);
        }
    }
    
    public function onGroupMembershipChanged($membership) {
        if (!$this->shouldSyncGroup($membership->group_id)) {
            return;
        }
        
        $this->logger->debug('Group membership changed', [
            'group_id' => $membership->group_id,
            'user_id' => $membership->user_id
        ]);
        
        wp_schedule_single_event(
            time() + 3,
            'supapress_groups_sync_membership',
            [$membership->group_id, $membership->user_id, 'add']
        );
        
        if (!wp_next_scheduled('supapress_groups_sync_membership')) {
            add_action('supapress_groups_sync_membership', [$this, 'syncMembershipDelayed'], 10, 3);
        }
    }
    
    public function onGroupMemberRemoved($group_id, $user_id) {
        if (!$this->shouldSyncGroup($group_id)) {
            return;
        }
        
        $this->logger->debug('Group member removed', [
            'group_id' => $group_id,
            'user_id' => $user_id
        ]);
        
        wp_schedule_single_event(
            time() + 3,
            'supapress_groups_sync_membership',
            [$group_id, $user_id, 'remove']
        );
    }
    
    public function onGroupMemberPromoted($user_id, $group_id) {
        $this->onGroupMembershipChanged((object)[
            'group_id' => $group_id,
            'user_id' => $user_id
        ]);
    }
    
    public function onGroupMemberDemoted($user_id, $group_id) {
        $this->onGroupMembershipChanged((object)[
            'group_id' => $group_id,
            'user_id' => $user_id
        ]);
    }
    
    public function onGroupActivityPosted($content, $user_id, $group_id) {
        $this->logger->debug('Group activity posted', [
            'group_id' => $group_id,
            'user_id' => $user_id
        ]);
        
        // This could sync group activity/posts if needed
        do_action('supapress_groups_activity_posted', $content, $user_id, $group_id);
    }
    
    // Message event handlers
    public function onMessageSent($message) {
        if (!$this->config->canSyncMessages()) {
            return;
        }
        
        $this->logger->debug('Message sent, triggering sync', ['message_id' => $message->id]);
        
        wp_schedule_single_event(
            time() + 2,
            'supapress_groups_sync_message',
            [$message->id]
        );
        
        if (!wp_next_scheduled('supapress_groups_sync_message')) {
            add_action('supapress_groups_sync_message', [$this, 'syncMessageDelayed']);
        }
    }
    
    public function onThreadSaved($thread) {
        $this->logger->debug('Message thread saved', ['thread_id' => $thread->thread_id]);
        
        // Thread sync is handled by message sync
        do_action('supapress_groups_thread_saved', $thread);
    }
    
    // Youzify event handlers
    public function onYouzifyChatMessage($message_data, $chat_id) {
        if (!$this->config->canSyncChats()) {
            return;
        }
        
        $this->logger->debug('Youzify chat message sent', [
            'chat_id' => $chat_id,
            'user_id' => $message_data['user_id'] ?? null
        ]);
        
        // This would sync Youzify chat messages
        do_action('supapress_groups_youzify_message_sent', $message_data, $chat_id);
    }
    
    // Delayed sync handlers
    public function syncGroupDelayed($group_id) {
        $result = $this->business->syncGroupToSupabase($group_id);
        
        $this->logger->info('Delayed group sync completed', [
            'group_id' => $group_id,
            'success' => $result['success'],
            'message' => $result['message'] ?? ''
        ]);
    }
    
    public function syncMembershipDelayed($group_id, $user_id, $action) {
        $groupSupabaseId = $this->model->getSupabaseId('group', $group_id);
        $userSupabaseId = get_user_meta($user_id, 'supabase_id', true);
        
        if (!$groupSupabaseId || !$userSupabaseId) {
            $this->logger->warning('Cannot sync membership - missing Supabase IDs', [
                'group_id' => $group_id,
                'user_id' => $user_id,
                'group_supabase_id' => $groupSupabaseId,
                'user_supabase_id' => $userSupabaseId
            ]);
            return;
        }
        
        $api = new SupaPress_Groups_API();
        
        if ($action === 'remove') {
            $result = $api->removeGroupMember($groupSupabaseId, $userSupabaseId);
        } else {
            $role = groups_get_user_group_role($user_id, $group_id) ?: 'member';
            $result = $api->updateGroupMember($groupSupabaseId, $userSupabaseId, $role);
        }
        
        $this->logger->info('Delayed membership sync completed', [
            'group_id' => $group_id,
            'user_id' => $user_id,
            'action' => $action,
            'success' => $result['success'],
            'message' => $result['message'] ?? ''
        ]);
    }
    
    public function syncMessageDelayed($message_id) {
        $result = $this->business->syncMessageToSupabase($message_id);
        
        $this->logger->info('Delayed message sync completed', [
            'message_id' => $message_id,
            'success' => $result['success'],
            'message' => $result['message'] ?? ''
        ]);
    }
    
    // Utility methods
    private function shouldSyncGroup($group_id) {
        if (!$this->config->canSyncGroups()) {
            return false;
        }
        
        $group = groups_get_group($group_id);
        if (!$group || !isset($group->status)) {
            return false;
        }
        
        $syncableTypes = $this->config->getSyncableGroupTypes();
        return in_array($group->status, $syncableTypes);
    }
}