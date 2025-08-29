<?php

if (!defined('ABSPATH')) {
    exit;
}

class SupaPress_Groups_Business {
    
    private $api;
    private $model;
    private $config;
    private $logger;
    
    public function __construct() {
        $this->api = new SupaPress_Groups_API();
        $this->model = new SupaPress_Groups_Model();
        $this->config = SupaPress_Groups_Config::getInstance();
        $this->logger = SupaPress_Groups_Logger::getInstance();
    }
    
    // Group sync methods
    public function syncGroupToSupabase($groupId) {
        if (!$this->config->canSyncGroups()) {
            return ['success' => false, 'message' => 'Groups sync not configured or disabled'];
        }
        
        $wpGroup = $this->model->getBuddyPressGroup($groupId);
        if (!$wpGroup) {
            return ['success' => false, 'message' => 'WordPress group not found'];
        }
        
        // Check if group privacy allows syncing
        $syncableTypes = $this->config->getSyncableGroupTypes();
        if (!in_array($wpGroup['privacy'], $syncableTypes)) {
            $this->logger->debug('Group skipped due to privacy settings', [
                'group_id' => $groupId,
                'privacy' => $wpGroup['privacy']
            ]);
            return ['success' => true, 'message' => 'Group skipped due to privacy settings'];
        }
        
        $existingSupabaseId = $this->model->getSupabaseId('group', $groupId);
        
        // Get creator's Supabase ID
        $creatorSupabaseId = get_user_meta($wpGroup['creator_id'], 'supabase_id', true);
        if (!$creatorSupabaseId) {
            return ['success' => false, 'message' => 'Creator not synced to Supabase'];
        }
        
        $wpGroup['creator_supabase_id'] = $creatorSupabaseId;
        
        try {
            if ($existingSupabaseId) {
                $result = $this->api->updateGroup($existingSupabaseId, $wpGroup);
            } else {
                $result = $this->api->createGroup($wpGroup);
                if ($result['success'] && isset($result['data'][0]['id'])) {
                    $this->model->setSupabaseId('group', $groupId, $result['data'][0]['id']);
                    $existingSupabaseId = $result['data'][0]['id'];
                }
            }
            
            if ($result['success']) {
                // Sync group members
                $this->syncGroupMembers($groupId, $existingSupabaseId ?: $result['data'][0]['id']);
            }
            
            $this->model->logSync(
                'group',
                $groupId,
                $existingSupabaseId ?: ($result['data'][0]['id'] ?? null),
                'wp_to_supabase',
                $result['success'] ? 'success' : 'error',
                $result['message'] ?? ''
            );
            
            do_action('supapress_groups_group_synced_to_supabase', $groupId, $result);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Group sync failed', [
                'group_id' => $groupId,
                'error' => $e->getMessage()
            ]);
            
            $this->model->logSync(
                'group',
                $groupId,
                $existingSupabaseId,
                'wp_to_supabase',
                'error',
                $e->getMessage()
            );
            
            return [
                'success' => false,
                'message' => 'Group sync failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function syncGroupMembers($groupId, $groupSupabaseId) {
        if (!$this->config->canSyncGroups()) {
            return false;
        }
        
        $members = $this->model->getGroupMembers($groupId);
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($members as $member) {
            if (empty($member['supabase_id'])) {
                $this->logger->warning('Member skipped - no Supabase ID', [
                    'user_id' => $member['wp_user_id'],
                    'group_id' => $groupId
                ]);
                continue;
            }
            
            $memberData = [
                'group_supabase_id' => $groupSupabaseId,
                'user_supabase_id' => $member['supabase_id'],
                'wp_user_id' => $member['wp_user_id'],
                'role' => $member['role']
            ];
            
            $result = $this->api->addGroupMember($memberData);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
                $this->logger->error('Failed to sync group member', [
                    'user_id' => $member['wp_user_id'],
                    'group_id' => $groupId,
                    'error' => $result['message']
                ]);
            }
        }
        
        $this->logger->info('Group members sync completed', [
            'group_id' => $groupId,
            'success' => $successCount,
            'errors' => $errorCount
        ]);
        
        return ['success_count' => $successCount, 'error_count' => $errorCount];
    }
    
    // Message sync methods
    public function syncMessageToSupabase($messageId) {
        if (!$this->config->canSyncMessages()) {
            return ['success' => false, 'message' => 'Messages sync not configured or disabled'];
        }
        
        global $wpdb;
        $bp = buddypress();
        
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bp->messages->table_name_messages} WHERE id = %d",
            $messageId
        ));
        
        if (!$message) {
            return ['success' => false, 'message' => 'Message not found'];
        }
        
        $senderSupabaseId = get_user_meta($message->sender_id, 'supabase_id', true);
        if (!$senderSupabaseId) {
            return ['success' => false, 'message' => 'Sender not synced to Supabase'];
        }
        
        // Sync thread first if not exists
        $threadSupabaseId = $this->model->getSupabaseId('thread', $message->thread_id);
        if (!$threadSupabaseId) {
            $threadResult = $this->syncMessageThread($message->thread_id);
            if (!$threadResult['success']) {
                return $threadResult;
            }
            $threadSupabaseId = $threadResult['thread_id'];
        }
        
        $messageData = [
            'thread_supabase_id' => $threadSupabaseId,
            'sender_supabase_id' => $senderSupabaseId,
            'wp_sender_id' => $message->sender_id,
            'content' => $message->message,
            'message_type' => 'text'
        ];
        
        try {
            $result = $this->api->createMessage($messageData);
            
            $this->model->logSync(
                'message',
                $messageId,
                $result['data'][0]['id'] ?? null,
                'wp_to_supabase',
                $result['success'] ? 'success' : 'error',
                $result['message'] ?? ''
            );
            
            do_action('supapress_groups_message_synced_to_supabase', $messageId, $result);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Message sync failed', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Message sync failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function syncMessageThread($threadId) {
        $thread = $this->model->getMessageThread($threadId);
        if (!$thread) {
            return ['success' => false, 'message' => 'Thread not found'];
        }
        
        $threadData = [
            'wp_thread_id' => $thread['wp_thread_id'],
            'subject' => $thread['subject']
        ];
        
        try {
            $result = $this->api->createMessageThread($threadData);
            
            if ($result['success'] && isset($result['data'][0]['id'])) {
                $threadSupabaseId = $result['data'][0]['id'];
                $this->model->setSupabaseId('thread', $threadId, $threadSupabaseId);
                
                // Sync thread participants
                foreach ($thread['participants'] as $participant) {
                    if ($participant['user_supabase_id']) {
                        $this->api->addThreadParticipant([
                            'thread_supabase_id' => $threadSupabaseId,
                            'user_supabase_id' => $participant['user_supabase_id'],
                            'wp_user_id' => $participant['wp_user_id']
                        ]);
                    }
                }
                
                $result['thread_id'] = $threadSupabaseId;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Thread sync failed', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Thread sync failed: ' . $e->getMessage()
            ];
        }
    }
    
    // Bulk sync methods
    public function bulkSyncGroups($limit = 20) {
        if (!$this->config->canSyncGroups()) {
            return ['success' => false, 'message' => 'Groups sync not configured'];
        }
        
        $groups = $this->model->getBuddyPressGroups($limit);
        $results = [
            'total' => count($groups),
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($groups as $group) {
            $result = $this->syncGroupToSupabase($group['wp_group_id']);
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['errors']++;
            }
            
            $results['details'][] = [
                'group_id' => $group['wp_group_id'],
                'name' => $group['name'],
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }
        
        return [
            'success' => true,
            'data' => $results
        ];
    }
    
    public function bulkSyncMessages($limit = 50) {
        if (!$this->config->canSyncMessages()) {
            return ['success' => false, 'message' => 'Messages sync not configured'];
        }
        
        $messages = $this->model->getBuddyPressMessages($limit);
        $results = [
            'total' => count($messages),
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($messages as $message) {
            $result = $this->syncMessageToSupabase($message['wp_message_id']);
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['errors']++;
            }
            
            $results['details'][] = [
                'message_id' => $message['wp_message_id'],
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }
        
        return [
            'success' => true,
            'data' => $results
        ];
    }
    
    // Test and utility methods
    public function testSync() {
        $tests = [];
        
        // Test SupaPress Core integration
        $tests['supapress_core'] = [
            'success' => class_exists('SupaPress') && defined('SUPAPRESS_VERSION'),
            'message' => (class_exists('SupaPress') && defined('SUPAPRESS_VERSION')) ? 
                'SupaPress Core available (v' . SUPAPRESS_VERSION . ')' : 'SupaPress Core not available'
        ];
        
        // Test Core methods accessibility
        try {
            $core_instance = $this->config->getCoreInstance();
            $core_api = $this->config->getCoreApiClient();
            $tests['core_methods'] = [
                'success' => ($core_instance && $core_api),
                'message' => ($core_instance && $core_api) ? 'Core methods accessible' : 'Core methods not accessible'
            ];
        } catch (Exception $e) {
            $tests['core_methods'] = [
                'success' => false,
                'message' => 'Core methods error: ' . $e->getMessage()
            ];
        }
        
        // Test API connection
        $tests['api_connection'] = $this->api->testConnection();
        
        // Test BuddyPress availability
        $tests['buddypress_groups'] = [
            'success' => bp_is_active('groups'),
            'message' => bp_is_active('groups') ? 'BuddyPress Groups active' : 'BuddyPress Groups not active'
        ];
        
        $tests['buddypress_messages'] = [
            'success' => bp_is_active('messages'),
            'message' => bp_is_active('messages') ? 'BuddyPress Messages active' : 'BuddyPress Messages not active'
        ];
        
        // Test configuration
        $tests['configuration'] = [
            'success' => $this->config->isConfigured(),
            'message' => $this->config->isConfigured() ? 'Configuration valid' : 'Configuration incomplete'
        ];
        
        // Test user mapping (if any users exist)
        $users = get_users(['number' => 1]);
        if (!empty($users)) {
            $test_user_id = $users[0]->ID;
            try {
                $supabase_id = $this->config->getUserSupabaseId($test_user_id);
                $tests['user_mapping'] = [
                    'success' => true,
                    'message' => $supabase_id ? 'User mapping working (test user has Supabase ID)' : 'User mapping working (test user not synced)'
                ];
            } catch (Exception $e) {
                $tests['user_mapping'] = [
                    'success' => false,
                    'message' => 'User mapping error: ' . $e->getMessage()
                ];
            }
        }
        
        $overallSuccess = array_reduce($tests, function($carry, $test) {
            return $carry && $test['success'];
        }, true);
        
        return [
            'success' => $overallSuccess,
            'tests' => $tests,
            'message' => $overallSuccess ? 'All tests passed' : 'Some tests failed'
        ];
    }
}