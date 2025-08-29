<?php

if (!defined('ABSPATH')) {
    exit;
}

class SupaPress_Groups_API {
    
    private $config;
    private $supabase_url;
    private $service_key;
    private $anon_key;
    private $logger;
    
    public function __construct() {
        $this->config = SupaPress_Groups_Config::getInstance();
        $this->supabase_url = $this->config->getSupabaseUrl();
        $this->service_key = $this->config->getSupabaseServiceKey();
        $this->anon_key = $this->config->getSupabaseAnonKey();
        $this->logger = SupaPress_Groups_Logger::getInstance();
    }
    
    // Group API methods
    public function createGroup($groupData) {
        $endpoint = $this->supabase_url . '/rest/v1/groups';
        
        $body = [
            'wp_group_id' => $groupData['wp_group_id'],
            'name' => $groupData['name'],
            'description' => $groupData['description'],
            'privacy' => $groupData['privacy'],
            'creator_id' => $groupData['creator_supabase_id'],
            'platform_source' => 'wordpress'
        ];
        
        return $this->makeRequest($endpoint, 'POST', $body);
    }
    
    public function updateGroup($supabaseId, $groupData) {
        $endpoint = $this->supabase_url . '/rest/v1/groups?id=eq.' . $supabaseId;
        
        $body = [
            'name' => $groupData['name'],
            'description' => $groupData['description'],
            'privacy' => $groupData['privacy'],
            'updated_at' => current_time('c'),
            'platform_source' => 'wordpress'
        ];
        
        return $this->makeRequest($endpoint, 'PATCH', $body);
    }
    
    public function deleteGroup($supabaseId) {
        $endpoint = $this->supabase_url . '/rest/v1/groups?id=eq.' . $supabaseId;
        return $this->makeRequest($endpoint, 'DELETE');
    }
    
    public function getGroup($supabaseId) {
        $endpoint = $this->supabase_url . '/rest/v1/groups?id=eq.' . $supabaseId;
        return $this->makeRequest($endpoint, 'GET');
    }
    
    public function getGroupsByWpIds($wpGroupIds) {
        if (empty($wpGroupIds)) {
            return ['success' => true, 'data' => []];
        }
        
        $ids = implode(',', array_map('intval', $wpGroupIds));
        $endpoint = $this->supabase_url . '/rest/v1/groups?wp_group_id=in.(' . $ids . ')';
        return $this->makeRequest($endpoint, 'GET');
    }
    
    // Group membership API methods
    public function addGroupMember($memberData) {
        $endpoint = $this->supabase_url . '/rest/v1/group_members';
        
        $body = [
            'group_id' => $memberData['group_supabase_id'],
            'user_id' => $memberData['user_supabase_id'],
            'wp_user_id' => $memberData['wp_user_id'],
            'role' => $memberData['role'],
            'platform_source' => 'wordpress'
        ];
        
        return $this->makeRequest($endpoint, 'POST', $body);
    }
    
    public function removeGroupMember($groupSupabaseId, $userSupabaseId) {
        $endpoint = $this->supabase_url . '/rest/v1/group_members?group_id=eq.' . $groupSupabaseId . '&user_id=eq.' . $userSupabaseId;
        return $this->makeRequest($endpoint, 'DELETE');
    }
    
    public function updateGroupMember($groupSupabaseId, $userSupabaseId, $role) {
        $endpoint = $this->supabase_url . '/rest/v1/group_members?group_id=eq.' . $groupSupabaseId . '&user_id=eq.' . $userSupabaseId;
        
        $body = [
            'role' => $role,
            'platform_source' => 'wordpress'
        ];
        
        return $this->makeRequest($endpoint, 'PATCH', $body);
    }
    
    // Message API methods
    public function createMessage($messageData) {
        $endpoint = $this->supabase_url . '/rest/v1/messages';
        
        $body = [
            'thread_id' => $messageData['thread_supabase_id'],
            'sender_id' => $messageData['sender_supabase_id'],
            'wp_sender_id' => $messageData['wp_sender_id'],
            'content' => $messageData['content'],
            'message_type' => $messageData['message_type'] ?? 'text',
            'platform_source' => 'wordpress'
        ];
        
        return $this->makeRequest($endpoint, 'POST', $body);
    }
    
    public function createMessageThread($threadData) {
        $endpoint = $this->supabase_url . '/rest/v1/message_threads';
        
        $body = [
            'wp_thread_id' => $threadData['wp_thread_id'],
            'subject' => $threadData['subject'],
            'platform_source' => 'wordpress'
        ];
        
        return $this->makeRequest($endpoint, 'POST', $body);
    }
    
    public function addThreadParticipant($participantData) {
        $endpoint = $this->supabase_url . '/rest/v1/thread_participants';
        
        $body = [
            'thread_id' => $participantData['thread_supabase_id'],
            'user_id' => $participantData['user_supabase_id'],
            'wp_user_id' => $participantData['wp_user_id']
        ];
        
        return $this->makeRequest($endpoint, 'POST', $body);
    }
    
    public function getMessageThread($wpThreadId) {
        $endpoint = $this->supabase_url . '/rest/v1/message_threads?wp_thread_id=eq.' . $wpThreadId;
        return $this->makeRequest($endpoint, 'GET');
    }
    
    // Realtime subscription methods
    public function subscribeToGroupChanges($callback) {
        // This would be implemented with Supabase realtime subscriptions
        // For now, return a placeholder
        return ['success' => true, 'message' => 'Realtime subscriptions would be implemented here'];
    }
    
    // Generic request handler
    private function makeRequest($endpoint, $method = 'GET', $body = null) {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->service_key,
            'apikey' => $this->anon_key
        ];
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        ];
        
        if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($body);
        }
        
        $this->logger->debug('Making API request', [
            'endpoint' => $endpoint,
            'method' => $method,
            'body' => $body
        ]);
        
        $response = wp_remote_request($endpoint, $args);
        
        if (is_wp_error($response)) {
            $this->logger->error('API request failed', [
                'error' => $response->get_error_message(),
                'endpoint' => $endpoint
            ]);
            
            return [
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($status_code >= 400) {
            $this->logger->error('API request returned error status', [
                'status_code' => $status_code,
                'response' => $response_body,
                'endpoint' => $endpoint
            ]);
            
            return [
                'success' => false,
                'message' => 'API request failed with status: ' . $status_code,
                'data' => json_decode($response_body, true)
            ];
        }
        
        $decoded_body = json_decode($response_body, true);
        
        return [
            'success' => true,
            'data' => $decoded_body,
            'status_code' => $status_code
        ];
    }
    
    public function testConnection() {
        $endpoint = $this->supabase_url . '/rest/v1/groups?limit=1';
        $result = $this->makeRequest($endpoint, 'GET');
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Groups API connection successful'
            ];
        }
        
        return $result;
    }
}