<?php

if (!defined('ABSPATH')) {
    exit;
}

class SupaPress_Groups_Model {
    
    private $sync_log_table;
    private $mapping_table;
    
    public function __construct() {
        global $wpdb;
        $this->sync_log_table = $wpdb->prefix . 'supapress_groups_sync_log';
        $this->mapping_table = $wpdb->prefix . 'supapress_groups_mapping';
    }
    
    // BuddyPress Group methods
    public function getBuddyPressGroup($groupId) {
        if (!bp_is_active('groups')) {
            return null;
        }
        
        $group = groups_get_group($groupId);
        if (!$group || !isset($group->id)) {
            return null;
        }
        
        return [
            'wp_group_id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'privacy' => $group->status,
            'creator_id' => $group->creator_id,
            'created' => $group->date_created,
            'updated' => $group->last_activity,
            'member_count' => groups_get_total_member_count($group->id),
            'avatar_url' => bp_core_fetch_avatar([
                'item_id' => $group->id,
                'object' => 'group',
                'type' => 'full',
                'html' => false
            ])
        ];
    }
    
    public function getBuddyPressGroups($limit = 50, $offset = 0) {
        if (!bp_is_active('groups')) {
            return [];
        }
        
        $groups = groups_get_groups([
            'per_page' => $limit,
            'page' => ($offset / $limit) + 1,
            'populate_extras' => false,
            'show_hidden' => true
        ]);
        
        $groupData = [];
        if (!empty($groups['groups'])) {
            foreach ($groups['groups'] as $group) {
                $groupData[] = $this->getBuddyPressGroup($group->id);
            }
        }
        
        return $groupData;
    }
    
    public function getGroupMembers($groupId) {
        if (!bp_is_active('groups')) {
            return [];
        }
        
        $members = groups_get_group_members([
            'group_id' => $groupId,
            'per_page' => 100,
            'page' => 1
        ]);
        
        $memberData = [];
        if (!empty($members['members'])) {
            foreach ($members['members'] as $member) {
                $memberData[] = [
                    'wp_user_id' => $member->ID,
                    'wp_group_id' => $groupId,
                    'role' => groups_get_user_group_role($member->ID, $groupId) ?: 'member',
                    'joined_date' => groups_get_user_join_time($member->ID, $groupId),
                    'user_email' => $member->user_email,
                    'display_name' => $member->display_name,
                    'supabase_id' => get_user_meta($member->ID, 'supabase_id', true)
                ];
            }
        }
        
        return $memberData;
    }
    
    // BuddyPress Messages methods
    public function getBuddyPressMessages($limit = 50, $offset = 0) {
        if (!bp_is_active('messages')) {
            return [];
        }
        
        global $wpdb;
        $bp = buddypress();
        
        $query = $wpdb->prepare("
            SELECT m.id, m.thread_id, m.sender_id, m.subject, m.message, m.date_sent
            FROM {$bp->messages->table_name_messages} m
            ORDER BY m.date_sent DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset);
        
        $messages = $wpdb->get_results($query);
        
        $messageData = [];
        foreach ($messages as $message) {
            $sender_supabase_id = get_user_meta($message->sender_id, 'supabase_id', true);
            
            $messageData[] = [
                'wp_message_id' => $message->id,
                'wp_thread_id' => $message->thread_id,
                'wp_sender_id' => $message->sender_id,
                'sender_supabase_id' => $sender_supabase_id,
                'subject' => $message->subject,
                'content' => $message->message,
                'date_sent' => $message->date_sent,
                'message_type' => 'text'
            ];
        }
        
        return $messageData;
    }
    
    public function getMessageThread($threadId) {
        if (!bp_is_active('messages')) {
            return null;
        }
        
        $thread = new BP_Messages_Thread($threadId);
        
        if (!$thread->thread_id) {
            return null;
        }
        
        $participants = [];
        foreach ($thread->recipients as $recipient) {
            $supabase_id = get_user_meta($recipient->user_id, 'supabase_id', true);
            $participants[] = [
                'wp_user_id' => $recipient->user_id,
                'user_supabase_id' => $supabase_id,
                'unread_count' => $recipient->unread_count
            ];
        }
        
        return [
            'wp_thread_id' => $thread->thread_id,
            'subject' => $thread->subject,
            'last_message_date' => $thread->last_message_date,
            'message_count' => count($thread->messages),
            'participants' => $participants
        ];
    }
    
    // Mapping methods
    public function getSupabaseId($entityType, $wpId) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT supabase_id FROM {$this->mapping_table} 
             WHERE entity_type = %s AND wp_id = %d",
            $entityType,
            $wpId
        ));
    }
    
    public function setSupabaseId($entityType, $wpId, $supabaseId) {
        global $wpdb;
        
        $result = $wpdb->replace(
            $this->mapping_table,
            [
                'entity_type' => $entityType,
                'wp_id' => $wpId,
                'supabase_id' => $supabaseId,
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    public function getWpId($entityType, $supabaseId) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT wp_id FROM {$this->mapping_table} 
             WHERE entity_type = %s AND supabase_id = %s",
            $entityType,
            $supabaseId
        ));
    }
    
    public function removeMapping($entityType, $wpId) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->mapping_table,
            [
                'entity_type' => $entityType,
                'wp_id' => $wpId
            ],
            ['%s', '%d']
        );
    }
    
    // Sync logging methods
    public function logSync($entityType, $entityId, $supabaseId, $direction, $status, $errorMessage = '') {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->sync_log_table,
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'supabase_id' => $supabaseId,
                'sync_direction' => $direction,
                'sync_status' => $status,
                'error_message' => $errorMessage,
                'sync_time' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $result;
    }
    
    public function getSyncLogs($limit = 50, $offset = 0, $entityType = null) {
        global $wpdb;
        
        $where = '';
        $args = [$limit, $offset];
        
        if ($entityType) {
            $where = ' WHERE entity_type = %s';
            array_unshift($args, $entityType);
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->sync_log_table}{$where} ORDER BY sync_time DESC LIMIT %d OFFSET %d",
            ...$args
        );
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    public function getLastSyncTime($entityType = null, $direction = null) {
        global $wpdb;
        
        $where_conditions = [];
        $args = [];
        
        if ($entityType) {
            $where_conditions[] = 'entity_type = %s';
            $args[] = $entityType;
        }
        
        if ($direction) {
            $where_conditions[] = 'sync_direction = %s';
            $args[] = $direction;
        }
        
        $where_conditions[] = 'sync_status = %s';
        $args[] = 'success';
        
        $where = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT MAX(sync_time) as last_sync FROM {$this->sync_log_table}{$where}";
        
        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, ...$args);
        }
        
        return $wpdb->get_var($sql);
    }
    
    // Youzify integration methods
    public function isYouzifyActive() {
        return function_exists('youzify');
    }
    
    public function getYouzifyUserData($userId) {
        if (!$this->isYouzifyActive()) {
            return [];
        }
        
        // Youzify profile fields would be accessed here
        // This is a placeholder for actual Youzify integration
        return [
            'user_id' => $userId,
            'custom_fields' => [],
            'social_links' => []
        ];
    }
}