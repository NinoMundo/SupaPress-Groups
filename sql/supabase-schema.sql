-- SupaPress Groups - Supabase Database Schema
-- This file contains the complete database schema for SupaPress Groups addon
-- Run this in your Supabase SQL editor after setting up SupaPress core

-- Groups table for storing BuddyPress group data
CREATE TABLE groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    wp_group_id BIGINT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    privacy VARCHAR(20) NOT NULL DEFAULT 'public',
    creator_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    member_count INTEGER DEFAULT 0,
    avatar_url TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    platform_source VARCHAR(20) DEFAULT 'wordpress'
);

-- Group memberships table
CREATE TABLE group_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    group_id UUID NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    wp_user_id BIGINT NOT NULL,
    role VARCHAR(50) DEFAULT 'member',
    joined_at TIMESTAMPTZ DEFAULT NOW(),
    platform_source VARCHAR(20) DEFAULT 'wordpress',
    UNIQUE(group_id, user_id)
);

-- Message threads table
CREATE TABLE message_threads (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    wp_thread_id BIGINT UNIQUE,
    subject TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    platform_source VARCHAR(20) DEFAULT 'wordpress'
);

-- Messages table for private messages
CREATE TABLE messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    thread_id UUID NOT NULL REFERENCES message_threads(id) ON DELETE CASCADE,
    sender_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    wp_sender_id BIGINT NOT NULL,
    content TEXT NOT NULL,
    message_type VARCHAR(20) DEFAULT 'text',
    sent_at TIMESTAMPTZ DEFAULT NOW(),
    platform_source VARCHAR(20) DEFAULT 'wordpress'
);

-- Thread participants table
CREATE TABLE thread_participants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    thread_id UUID NOT NULL REFERENCES message_threads(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    wp_user_id BIGINT NOT NULL,
    last_read_at TIMESTAMPTZ,
    UNIQUE(thread_id, user_id)
);

-- Group activities/posts table (optional)
CREATE TABLE group_activities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    group_id UUID NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    wp_user_id BIGINT NOT NULL,
    content TEXT NOT NULL,
    activity_type VARCHAR(50) DEFAULT 'post',
    posted_at TIMESTAMPTZ DEFAULT NOW(),
    platform_source VARCHAR(20) DEFAULT 'wordpress'
);

-- Indexes for better performance
CREATE INDEX idx_groups_wp_id ON groups(wp_group_id);
CREATE INDEX idx_groups_creator ON groups(creator_id);
CREATE INDEX idx_groups_privacy ON groups(privacy);
CREATE INDEX idx_groups_updated ON groups(updated_at DESC);

CREATE INDEX idx_group_members_group ON group_members(group_id);
CREATE INDEX idx_group_members_user ON group_members(user_id);
CREATE INDEX idx_group_members_wp_user ON group_members(wp_user_id);

CREATE INDEX idx_messages_thread ON messages(thread_id);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_sent_at ON messages(sent_at DESC);
CREATE INDEX idx_messages_wp_sender ON messages(wp_sender_id);

CREATE INDEX idx_thread_participants_thread ON thread_participants(thread_id);
CREATE INDEX idx_thread_participants_user ON thread_participants(user_id);

CREATE INDEX idx_group_activities_group ON group_activities(group_id);
CREATE INDEX idx_group_activities_user ON group_activities(user_id);
CREATE INDEX idx_group_activities_posted ON group_activities(posted_at DESC);

-- Row Level Security (RLS) Policies

-- Enable RLS on all tables
ALTER TABLE groups ENABLE ROW LEVEL SECURITY;
ALTER TABLE group_members ENABLE ROW LEVEL SECURITY;
ALTER TABLE message_threads ENABLE ROW LEVEL SECURITY;
ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE thread_participants ENABLE ROW LEVEL SECURITY;
ALTER TABLE group_activities ENABLE ROW LEVEL SECURITY;

-- Groups policies
CREATE POLICY "Public groups are viewable by everyone" ON groups
    FOR SELECT USING (privacy = 'public');

CREATE POLICY "Private groups are viewable by members" ON groups
    FOR SELECT USING (
        privacy = 'private' AND (
            auth.uid() = creator_id OR 
            auth.uid() IN (
                SELECT user_id FROM group_members WHERE group_id = groups.id
            )
        )
    );

CREATE POLICY "Hidden groups are viewable by members only" ON groups
    FOR SELECT USING (
        privacy = 'hidden' AND (
            auth.uid() = creator_id OR 
            auth.uid() IN (
                SELECT user_id FROM group_members WHERE group_id = groups.id
            )
        )
    );

CREATE POLICY "Service role can manage all groups" ON groups
    FOR ALL USING (auth.jwt() ->> 'role' = 'service_role');

-- Group members policies
CREATE POLICY "Group members are viewable by group members" ON group_members
    FOR SELECT USING (
        auth.uid() IN (
            SELECT user_id FROM group_members gm2 WHERE gm2.group_id = group_members.group_id
        )
    );

CREATE POLICY "Users can view their own memberships" ON group_members
    FOR SELECT USING (auth.uid() = user_id);

CREATE POLICY "Service role can manage all memberships" ON group_members
    FOR ALL USING (auth.jwt() ->> 'role' = 'service_role');

-- Messages policies
CREATE POLICY "Thread participants can view messages" ON messages
    FOR SELECT USING (
        auth.uid() IN (
            SELECT user_id FROM thread_participants WHERE thread_id = messages.thread_id
        )
    );

CREATE POLICY "Users can send messages to their threads" ON messages
    FOR INSERT WITH CHECK (
        auth.uid() = sender_id AND
        auth.uid() IN (
            SELECT user_id FROM thread_participants WHERE thread_id = messages.thread_id
        )
    );

CREATE POLICY "Service role can manage all messages" ON messages
    FOR ALL USING (auth.jwt() ->> 'role' = 'service_role');

-- Thread participants policies
CREATE POLICY "Participants can view thread participants" ON thread_participants
    FOR SELECT USING (
        auth.uid() IN (
            SELECT user_id FROM thread_participants tp2 WHERE tp2.thread_id = thread_participants.thread_id
        )
    );

CREATE POLICY "Service role can manage all participants" ON thread_participants
    FOR ALL USING (auth.jwt() ->> 'role' = 'service_role');

-- Message threads policies
CREATE POLICY "Thread participants can view threads" ON message_threads
    FOR SELECT USING (
        auth.uid() IN (
            SELECT user_id FROM thread_participants WHERE thread_id = message_threads.id
        )
    );

CREATE POLICY "Service role can manage all threads" ON message_threads
    FOR ALL USING (auth.jwt() ->> 'role' = 'service_role');

-- Group activities policies
CREATE POLICY "Group members can view group activities" ON group_activities
    FOR SELECT USING (
        auth.uid() IN (
            SELECT user_id FROM group_members WHERE group_id = group_activities.group_id
        )
    );

CREATE POLICY "Group members can post activities" ON group_activities
    FOR INSERT WITH CHECK (
        auth.uid() = user_id AND
        auth.uid() IN (
            SELECT user_id FROM group_members WHERE group_id = group_activities.group_id
        )
    );

CREATE POLICY "Service role can manage all activities" ON group_activities
    FOR ALL USING (auth.jwt() ->> 'role' = 'service_role');

-- Functions for updating timestamps
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers for updating timestamps
CREATE TRIGGER update_groups_updated_at BEFORE UPDATE ON groups
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_message_threads_updated_at BEFORE UPDATE ON message_threads
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Function to update group member count
CREATE OR REPLACE FUNCTION update_group_member_count()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE groups 
        SET member_count = (SELECT COUNT(*) FROM group_members WHERE group_id = NEW.group_id)
        WHERE id = NEW.group_id;
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE groups 
        SET member_count = (SELECT COUNT(*) FROM group_members WHERE group_id = OLD.group_id)
        WHERE id = OLD.group_id;
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE 'plpgsql';

-- Triggers for group member count
CREATE TRIGGER update_member_count_on_insert
    AFTER INSERT ON group_members
    FOR EACH ROW EXECUTE FUNCTION update_group_member_count();

CREATE TRIGGER update_member_count_on_delete
    AFTER DELETE ON group_members
    FOR EACH ROW EXECUTE FUNCTION update_group_member_count();

-- Enable Realtime for cross-platform sync
ALTER PUBLICATION supabase_realtime ADD TABLE groups;
ALTER PUBLICATION supabase_realtime ADD TABLE group_members;
ALTER PUBLICATION supabase_realtime ADD TABLE messages;
ALTER PUBLICATION supabase_realtime ADD TABLE message_threads;
ALTER PUBLICATION supabase_realtime ADD TABLE thread_participants;
ALTER PUBLICATION supabase_realtime ADD TABLE group_activities;

-- Insert some sample data for testing (optional)
-- You can uncomment these after running the main schema

/*
-- Sample group
INSERT INTO groups (wp_group_id, name, description, privacy) 
VALUES (1, 'Test Group', 'A test group for SupaPress Groups', 'public');

-- Sample message thread
INSERT INTO message_threads (wp_thread_id, subject) 
VALUES (1, 'Welcome to SupaPress Groups');
*/