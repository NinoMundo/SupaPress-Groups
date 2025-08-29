# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with SupaPress Groups addon code.

**Version**: SupaPress Groups v1.0.0

## CRITICAL: Project Naming

**PROJECT NAME: SupaPress (NOT Supafolio)**

- ✅ CORRECT: SupaPress 
- ❌ INCORRECT: Supafolio
- ✅ CORRECT: SupaPress Groups (addon)
- ❌ INCORRECT: Supafolio Groups

This is a WordPress plugin for Supabase synchronization. The name "Supafolio" should NEVER be used in relation to this project.

## Project Overview

SupaPress Groups is a WordPress addon plugin that extends SupaPress Core to sync BuddyPress groups, private messages, and Youzify chats with Supabase for cross-platform community features. It enables bidirectional synchronization between WordPress community data and mobile/web applications.

## Development Commands

This is a WordPress addon plugin with no build system or package managers. Development is done directly with PHP files.

**No build, test, or lint commands are configured** - this is standard PHP/WordPress development without modern tooling.

## Dependencies

### Hard Dependencies
- **SupaPress Core v1.1.0+** - Required for base Supabase connectivity and user sync
- **BuddyPress** - Required for groups and messages functionality
- **WordPress 5.0+** and **PHP 7.4+**

### Optional Dependencies  
- **Youzify** - Optional for chat synchronization features

### Dependency Checking
The plugin includes comprehensive dependency checking in the main plugin file and will display admin notices if requirements are not met.

## Architecture (BMAD Pattern)

The addon follows the same BMAD architecture as SupaPress Core:

### Business Layer (`class-supapress-groups-business.php`)
- **Primary orchestration** - coordinates all sync operations between WordPress and Supabase
- **Group sync logic** - handles group creation, updates, deletion, and membership management
- **Message sync logic** - manages private message and thread synchronization  
- **Bulk sync operations** - handles batch processing for large data sets
- **Error handling and retry logic** - manages sync failures and recovery

### Model Layer (`class-supapress-groups-model.php`)
- **BuddyPress data access** - retrieves groups, members, messages, and threads
- **WordPress/Supabase ID mapping** - manages entity relationships via custom mapping table
- **Sync logging** - tracks all sync operations with detailed status and error information
- **Youzify integration** - optional data access for chat features
- **Batch data operations** - optimized queries for performance

### API Layer (`class-supapress-groups-api.php`) 
- **Supabase REST API integration** - handles all HTTP communication with Supabase
- **Groups API methods** - create, read, update, delete groups and memberships
- **Messages API methods** - sync messages, threads, and participants
- **Real-time subscriptions** - placeholder for Supabase realtime integration
- **Error handling** - comprehensive API error management and logging

### Data Layer (`class-supapress-groups-config.php`)
- **Configuration management** - addon-specific settings storage and validation
- **SupaPress Core integration** - inherits base configuration from core plugin
- **Feature toggles** - enables/disables groups, messages, and chat sync
- **Privacy controls** - configurable group privacy level synchronization
- **Performance settings** - batch sizes, retry limits, etc.

## Key Implementation Details

### Plugin Integration  
- **Hooks into SupaPress Core** - uses `supapress_admin_menu_items` action to add admin pages
- **WordPress plugin architecture** - follows standard activation/deactivation/uninstall patterns
- **Database table creation** - creates addon-specific tables for sync logging and entity mapping
- **Settings management** - separate option storage from core SupaPress settings

### Real-time Sync System
- **WordPress hooks integration** - listens for BuddyPress group and message actions
- **Delayed execution** - uses WordPress scheduled events to prevent race conditions
- **Conflict resolution** - implements timestamp-based conflict resolution
- **Privacy filtering** - respects group privacy settings for sync decisions

### Cross-platform Data Model
- **Bidirectional mapping** - maintains WordPress ID ↔ Supabase ID relationships
- **Platform source tracking** - tracks whether data originated from WordPress or app
- **Timestamp management** - handles created/updated timestamps for conflict resolution
- **Entity relationships** - maintains referential integrity across platforms

### WordPress Integration Patterns
- **BuddyPress API usage** - uses standard BuddyPress functions and hooks
- **WordPress best practices** - follows WordPress coding standards, security practices
- **Admin interface** - uses WordPress admin UI patterns with tabbed interface
- **AJAX operations** - secure nonce-verified AJAX for admin operations

### Supabase Schema Design
- **Comprehensive RLS policies** - row-level security for group privacy and message access
- **Optimized indexes** - performance indexes for common query patterns  
- **Real-time enabled** - configured for Supabase real-time subscriptions
- **Referential integrity** - foreign keys maintain data consistency
- **Automatic triggers** - database triggers for member counts and timestamps

## Error Handling Strategy

### Logging System
- **Structured logging** - consistent log format with context data
- **Multiple log levels** - debug, info, warning, error with appropriate filtering
- **WordPress debug integration** - integrates with WordPress debug logging
- **Cache-based recent logs** - maintains recent log cache for admin display

### Sync Failure Management  
- **Comprehensive error tracking** - logs all sync failures with detailed context
- **Dependency validation** - checks for required user Supabase IDs before sync
- **Privacy filtering** - validates group privacy settings before sync attempts
- **Graceful degradation** - continues operation even when individual syncs fail

## Testing and Validation

### Built-in Test Functions
- **Connection testing** - validates Supabase API connectivity
- **BuddyPress availability** - checks for required BuddyPress components
- **Configuration validation** - ensures proper setup before sync operations
- **Dependency checking** - validates all plugin requirements

### Admin Interface
- **Real-time status** - displays current sync status and last sync times
- **Manual sync triggers** - allows admin-initiated sync operations
- **Detailed logging** - tabbed interface showing sync logs and performance
- **Requirement validation** - visual indicators for plugin requirements

## Performance Considerations

### Batch Processing
- **Configurable batch sizes** - admin-configurable batch processing (5-100 items)
- **Memory management** - processes large datasets in chunks to prevent timeouts
- **Rate limiting** - respects Supabase rate limits and quotas

### Real-time Efficiency
- **Delayed execution** - prevents race conditions with WordPress scheduled events
- **Selective sync** - only syncs items that meet privacy and configuration criteria
- **Change detection** - uses timestamps to avoid unnecessary sync operations