# SupaPress Groups

A powerful addon for SupaPress that enables bidirectional synchronization of BuddyPress groups, private messages, and Youzify chats with Supabase for cross-platform community features.

## Features

- **Groups Sync**: Bidirectional synchronization of BuddyPress groups with Supabase
- **Private Messages**: Real-time sync of BuddyPress private messages for app integration
- **Group Memberships**: Sync group memberships, roles, and permissions
- **Youzify Integration**: Optional chat sync when Youzify is active
- **Real-time Updates**: Automatic sync when groups or messages are created/updated
- **Privacy Controls**: Configurable privacy levels for group synchronization
- **Cross-platform**: Enables seamless community experience between WordPress and mobile apps

## Requirements

- WordPress 5.0+
- PHP 7.4+
- **SupaPress Core v1.1.0+** (required)
- **BuddyPress** (required)
- Youzify (optional, for chat features)
- Active Supabase project

## Installation

1. Ensure SupaPress Core v1.1.0+ is installed and activated
2. Upload the SupaPress Groups plugin files to `/wp-content/plugins/supapress-groups/`
3. Activate the plugin through the WordPress admin
4. Run the Supabase schema setup (see below)
5. Go to SupaPress → Groups to configure settings

## Supabase Setup

### 1. Database Schema
Run the SQL schema in your Supabase project:

1. Go to your Supabase dashboard → SQL Editor
2. Copy the contents of `sql/supabase-schema.sql`
3. Paste and run the SQL to create the required tables

### 2. Row Level Security (RLS)
The schema includes comprehensive RLS policies for:
- Group privacy (public, private, hidden)
- Message privacy (thread participants only)
- Service role access for WordPress sync operations

### 3. Realtime Subscriptions
The schema automatically enables Supabase Realtime for:
- Groups and group memberships
- Messages and message threads
- Group activities

## Configuration

### Groups Settings

Navigate to **SupaPress → Groups** in WordPress admin:

#### Sync Options
- **Enable Groups Sync**: Toggle group synchronization
- **Enable Messages Sync**: Toggle private message synchronization  
- **Enable Chat Sync**: Toggle Youzify chat synchronization (if available)
- **Real-time Sync**: Enable automatic sync on group/message actions

#### Privacy Settings
- **Public groups only**: Sync only public BuddyPress groups
- **Public and private groups**: Sync public and private groups
- **All groups**: Sync all groups including hidden ones

#### Performance Settings
- **Batch Size**: Number of items to sync per batch operation (5-100)

## Usage

### Automatic Sync
Once configured, the plugin automatically syncs:
- New groups created in BuddyPress
- Group updates (name, description, privacy changes)
- Group membership changes (join, leave, role changes)
- Private messages between users
- Youzify chat messages (if enabled)

### Manual Sync
Use the **SupaPress → Groups** page to:
- Test sync connectivity
- Run manual group sync operations
- View sync logs and status
- Monitor sync performance

### App Integration
The Supabase tables provide a complete API for mobile/web apps:

#### Groups API Endpoints
```
GET  /rest/v1/groups                    # List groups
GET  /rest/v1/groups?id=eq.{id}        # Get specific group
POST /rest/v1/groups                    # Create group
PATCH /rest/v1/groups?id=eq.{id}       # Update group
DELETE /rest/v1/groups?id=eq.{id}      # Delete group
```

#### Messages API Endpoints  
```
GET  /rest/v1/messages                  # List messages
GET  /rest/v1/messages?thread_id=eq.{id} # Get thread messages
POST /rest/v1/messages                  # Send message
```

#### Real-time Subscriptions
```javascript
// Listen for new groups
supabase
  .from('groups')
  .on('INSERT', payload => {
    console.log('New group created:', payload.new)
  })
  .subscribe()

// Listen for new messages  
supabase
  .from('messages')
  .on('INSERT', payload => {
    console.log('New message:', payload.new)
  })
  .subscribe()
```

## Database Tables

The plugin creates the following Supabase tables:

### Core Tables
- `groups` - BuddyPress group data
- `group_members` - Group membership relationships
- `message_threads` - Private message conversations
- `messages` - Individual messages
- `thread_participants` - Message thread participants

### Optional Tables
- `group_activities` - Group posts and activities

## Hooks and Filters

The plugin provides WordPress hooks for customization:

### Actions
- `supapress_groups_group_synced_to_supabase` - Fired after group sync
- `supapress_groups_message_synced_to_supabase` - Fired after message sync
- `supapress_groups_group_deleted_from_supabase` - Fired after group deletion

### Real-time WordPress Hooks
- Automatically triggers on BuddyPress group actions
- Automatically triggers on BuddyPress message actions
- Optional Youzify chat integration

## Troubleshooting

### Connection Issues
1. Verify SupaPress Core is configured with valid Supabase credentials
2. Ensure service role key has proper permissions
3. Test connection using the built-in test function

### Sync Failures
1. Check the Groups sync log for detailed error messages
2. Verify BuddyPress is active and functioning
3. Ensure users have Supabase IDs (synced via SupaPress Core)
4. Check Supabase quotas and rate limits

### Performance
- Adjust batch size settings for optimal performance
- Real-time sync creates minimal overhead
- Large initial syncs may take time depending on group/message volume

## Development

### File Structure
```
supapress-groups/
├── supapress-groups.php                        # Main plugin file
├── uninstall.php                              # Cleanup on uninstall
├── includes/
│   ├── class-supapress-groups-config.php     # Configuration (Data)
│   ├── class-supapress-groups-api.php        # Supabase API (API)
│   ├── class-supapress-groups-model.php      # BuddyPress data (Model)
│   ├── class-supapress-groups-business.php   # Sync logic (Business)
│   ├── class-supapress-groups-admin.php      # Admin interface
│   ├── class-supapress-groups-hooks.php      # WordPress hooks
│   └── class-supapress-groups-logger.php     # Logging system
├── sql/
│   └── supabase-schema.sql                   # Database schema
├── CLAUDE.md                                 # Development guidance
└── README.md                                 # This file
```

### Architecture
The plugin follows the BMAD (Business, Model, API, Data) architecture pattern:
- **Business**: Orchestrates sync operations and business logic
- **Model**: Handles BuddyPress data access and mapping
- **API**: Manages Supabase REST API communication
- **Data**: Configuration and settings management

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- BuddyPress groups bidirectional sync
- Private messages sync for app integration  
- Group membership and role synchronization
- Real-time sync with WordPress hooks
- Comprehensive admin interface
- Complete Supabase schema with RLS policies
- Youzify chat integration support