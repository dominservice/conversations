<?php

/**
 * Conversations
 *
 * This package will allow you to add a full user messaging system
 * into your Laravel application.
 *
 * @package   Dominservice\Conversations
 * @author    DSO-IT Mateusz Domin <biuro@dso.biz.pl>
 * @copyright (c) 2021 DSO-IT Mateusz Domin
 * @license   MIT
 * @version   3.0.0
 */

return array(
    'user_model' => \App\Models\User::class,
    'user_primary_key_type' => 'int', // int | uuid
    'user_primary_key' => 'id',

    'tables' => [
        'conversations' => 'conversations',
        'conversation_relations' => 'conversation_relations',
        'conversation_users' => 'conversation_users',
        'conversation_messages' => 'conversation_messages',
        'conversation_message_statuses' => 'conversation_message_statuses',
        'conversation_types' => 'conversation_types',
        'conversation_type_translations' => 'conversation_type_translations',
        'conversation_attachments' => 'conversation_attachments',
        'conversation_message_reactions' => 'conversation_message_reactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the API settings for the conversations package.
    | You can enable/disable the API and configure the routes prefix.
    |
    */
    'api' => [
        'enabled' => env('CONVERSATIONS_API_ENABLED', true),
        'prefix' => 'api/conversations',
        'middleware' => ['api', 'auth:api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Web UI Configuration
    |--------------------------------------------------------------------------
    |
    | Optional server-rendered panel routes and views.
    |
    */
    'web' => [
        'enabled' => env('CONVERSATIONS_WEB_ENABLED', true),
        'prefix' => env('CONVERSATIONS_WEB_PREFIX', 'conversation'),
        'middleware' => ['web', 'auth'],
        'route_name_prefix' => 'conversations.web.',
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Theme and assets for package web panel.
    |
    */
    'ui' => [
        // bootstrap | tailwind
        'theme' => env('CONVERSATIONS_UI_THEME', 'bootstrap'),
        'view' => env('CONVERSATIONS_UI_VIEW', 'conversations::panel.index'),
        'per_page' => env('CONVERSATIONS_UI_PER_PAGE', 100),

        // Null = package API /api/conversations/contacts endpoint.
        'contacts_endpoint' => env('CONVERSATIONS_UI_CONTACTS_ENDPOINT'),

        'assets' => [
            'css' => env('CONVERSATIONS_UI_CSS', 'vendor/conversations/css/panel.css'),
            'js' => env('CONVERSATIONS_UI_JS', 'vendor/conversations/js/panel.js'),
        ],

        // Relation badges displayed in list and conversation header.
        // Values may be plain labels or translation keys.
        'relation_labels' => [
            'announcements' => 'Announcements',
            'assignments' => 'Assignments',
            'job_offers' => 'Job offers',
            'communities' => 'Communities',
            'miscellaneous' => 'Miscellaneous',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations Configuration
    |--------------------------------------------------------------------------
    |
    | Project-specific business rules should be enabled from config and handled
    | by callbacks, so the package can be reused across many applications.
    |
    | Callback formats:
    | - Closure
    | - "Class@method"
    | - ["Class", "method"]
    | - Invokable class name
    |
    */
    'integrations' => [
        'conversation_start' => [
            // Require that conversation participants are in contact relation.
            'require_contact_relationship' => env('CONVERSATIONS_REQUIRE_CONTACTS_FOR_START', false),

            // Optional relation fallback when no callback is configured.
            // Example: "contacts"
            'contact_relation_method' => env('CONVERSATIONS_CONTACT_RELATION_METHOD'),
            // Optional key used in whereIn() on relation query.
            // Example: "uuid"
            'contact_relation_key' => env('CONVERSATIONS_CONTACT_RELATION_KEY'),

            // Callback should return bool.
            'contact_authorizer' => null,

            // Execute automatic relation acceptance callback for participants.
            'auto_accept_relationships' => env('CONVERSATIONS_AUTO_ACCEPT_RELATIONS', false),
            'auto_acceptor' => null,

            // Require conversation owner for sensitive actions.
            'owner_required_for_title_update' => env('CONVERSATIONS_OWNER_REQUIRED_TITLE_UPDATE', true),
            'owner_required_for_add_participants' => env('CONVERSATIONS_OWNER_REQUIRED_ADD_PARTICIPANTS', true),
        ],

        'business_notifications' => [
            'enabled' => env('CONVERSATIONS_BUSINESS_NOTIFICATIONS_ENABLED', false),

            // Callback receives event + payload context.
            // Event examples:
            // - "conversation.started"
            // - "conversation.participants_added"
            // - "conversation.title_updated"
            'dispatcher' => null,
        ],

        'ui' => [
            // Optional callback returning contacts used by web panel picker.
            // Signature example: fn($authUser, $request) => array
            'contacts_provider' => null,

            // Optional callback returning conversations collection/paginator.
            // Signature example: fn($authUser, $request) => iterable
            'conversations_provider' => null,

            // Optional callback for conversation delete action.
            // Signature example: fn($conversationUuid, $authUser, $request): bool|null
            'conversation_delete' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence Configuration
    |--------------------------------------------------------------------------
    |
    | User last_seen_at touch configuration used by middleware and notification
    | throttling logic.
    |
    */
    'presence' => [
        'last_seen_touch_interval_seconds' => env('CONVERSATIONS_LAST_SEEN_TOUCH_INTERVAL_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications Configuration
    |--------------------------------------------------------------------------
    |
    | Conversation email notifications can be delayed and sent only for users
    | who remain inactive and have unread messages.
    |
    */
    'notifications' => [
        'email' => [
            'enabled' => env('CONVERSATIONS_EMAIL_NOTIFICATIONS_ENABLED', true),
            'delay_minutes' => env('CONVERSATIONS_EMAIL_NOTIFICATIONS_DELAY_MINUTES', 5),
            'require_inactive_user' => env('CONVERSATIONS_EMAIL_REQUIRE_INACTIVE_USER', true),
            'inactive_after_minutes' => env('CONVERSATIONS_EMAIL_INACTIVE_AFTER_MINUTES', 5),
            'deduplicate_via_notified_at' => env('CONVERSATIONS_EMAIL_DEDUPLICATE_VIA_NOTIFIED_AT', true),
            'cache_ttl_minutes' => env('CONVERSATIONS_EMAIL_NOTIFICATION_CACHE_TTL_MINUTES', 10080), // 7 days
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Read Receipts Configuration
    |--------------------------------------------------------------------------
    |
    | Controls read receipt payload and realtime updates for conversation UI.
    |
    */
    'read_receipts' => [
        'enabled' => env('CONVERSATIONS_READ_RECEIPTS_ENABLED', true),
        'show_unread_in_group' => env('CONVERSATIONS_READ_RECEIPTS_SHOW_UNREAD_IN_GROUP', true),
        'broadcast_on_mark_all' => env('CONVERSATIONS_READ_RECEIPTS_BROADCAST_ON_MARK_ALL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hooks Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure hooks that will be executed before and after
    | certain actions in the conversations package. You can register callbacks
    | for each hook point to execute custom code.
    |
    | Available hook points:
    | - before_create_conversation
    | - after_create_conversation
    | - before_add_message
    | - after_add_message
    | - before_mark_as_read
    | - after_mark_as_read
    | - before_mark_as_deleted
    | - after_mark_as_deleted
    | - before_delete_conversation
    | - after_delete_conversation
    |
    | Example:
    | 'hooks' => [
    |     'after_add_message' => [
    |         function ($message, $conversation) {
    |             // Your custom code here
    |         },
    |         'App\\Hooks\\ConversationHooks@afterAddMessage',
    |     ],
    | ],
    |
    */
    'hooks' => [
        // Register your hooks here
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the attachment settings for the conversations.
    | You can enable/disable attachments, set file size limits, allowed types,
    | image optimization settings, and security options.
    |
    */
    'attachments' => [
        // Enable or disable attachments
        'enabled' => env('CONVERSATIONS_ATTACHMENTS_ENABLED', true),

        // Storage disk to use for attachments (uses Laravel's filesystem configuration)
        'disk' => env('CONVERSATIONS_ATTACHMENTS_DISK', 'public'),

        // Path within the disk where attachments will be stored
        'path' => env('CONVERSATIONS_ATTACHMENTS_PATH', 'conversations/attachments'),

        // Maximum file size in kilobytes (KB)
        'max_size' => [
            'default' => 10240, // 10MB default limit
            'image' => 5120,    // 5MB for images
            'document' => 10240, // 10MB for documents
            'audio' => 20480,   // 20MB for audio files
            'video' => 51200,   // 50MB for video files
        ],

        // Allowed file extensions
        'allowed_extensions' => [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv'],
            'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
            'video' => ['mp4', 'mov', 'avi', 'wmv', 'webm'],
        ],

        // Explicitly blocked extensions (security risk files)
        'blocked_extensions' => [
            'exe', 'bat', 'cmd', 'sh', 'php', 'pl', 'py', 'js', 'jar', 'com',
            'vbs', 'reg', 'msi', 'dll', 'bin', 'apk', 'app', 'dmg'
        ],

        // Image optimization settings
        'image' => [
            // Enable or disable image optimization
            'optimize' => true,

            // Preferred image driver for Intervention Image (gd|imagick|null=auto)
            'driver' => env('CONVERSATIONS_ATTACHMENTS_IMAGE_DRIVER'),

            // Maximum dimensions for images (width x height in pixels)
            'max_dimensions' => [1920, 1080],

            // Image quality (0-100) for JPEG and WebP
            'quality' => 85,

            // Convert all images to a specific format (null to keep original format)
            // Options: 'jpg', 'png', 'webp', null
            'convert_to' => 'webp',

            // Generate thumbnails
            'thumbnails' => [
                'enabled' => true,
                'dimensions' => [
                    'small' => [320, 240],
                    'medium' => [640, 480],
                ],
            ],
        ],

        // Security settings
        'security' => [
            // Show warning for potentially unsafe files
            'show_warning' => true,

            // File types that trigger a warning
            'warning_types' => ['zip', 'rar', '7z', 'tar', 'gz'],

            // Virus scanning
            'virus_scan' => [
                'enabled' => env('CONVERSATIONS_VIRUS_SCAN_ENABLED', false),

                // Options: 'clamav', 'external'
                'driver' => env('CONVERSATIONS_VIRUS_SCAN_DRIVER', 'clamav'),

                // ClamAV settings
                'clamav' => [
                    'socket' => env('CONVERSATIONS_CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
                ],

                // External API settings (e.g., VirusTotal)
                'external' => [
                    'api_url' => env('CONVERSATIONS_VIRUS_SCAN_API_URL'),
                    'api_key' => env('CONVERSATIONS_VIRUS_SCAN_API_KEY'),
                ],
            ],
        ],

        // Hooks for custom processing
        'hooks' => [
            // Before upload validation
            'before_validate' => null,

            // After validation, before storage
            'before_store' => null,

            // After storage
            'after_store' => null,

            // Before serving the file
            'before_serve' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Editing Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the message editing settings.
    | You can enable/disable editing and set a time limit for editing messages.
    |
    */
    'message_editing' => [
        // Enable or disable message editing
        'enabled' => env('CONVERSATIONS_MESSAGE_EDITING_ENABLED', true),

        // Time limit in minutes for editing messages (null for no limit)
        'time_limit' => env('CONVERSATIONS_MESSAGE_EDITING_TIME_LIMIT', 15),

        // Whether to mark messages as edited
        'mark_as_edited' => true,

        // Whether to broadcast edit events
        'broadcast_edits' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the broadcasting settings for real-time messaging.
    | You can enable/disable broadcasting and select the driver to use.
    |
    */
    'broadcasting' => [
        'enabled' => env('CONVERSATIONS_BROADCASTING_ENABLED', false),

        // Available drivers: 'pusher', 'laravel-websockets', 'firebase', 'mqtt', 'socketio', 'null'
        'driver' => env('CONVERSATIONS_BROADCASTING_DRIVER', 'null'),

        // Channel name prefix for all conversation events
        'channel_prefix' => 'conversation',

        // Events to broadcast
        'events' => [
            'message_sent' => true,
            'message_read' => true,
            'message_deleted' => true,
            'message_edited' => true,
            'conversation_created' => true,
            'user_typing' => true,
        ],

        // Additionally broadcast selected events to user-scoped channels:
        // private-conversation.user.{user_uuid}
        'user_channel_events' => [
            'message_sent' => env('CONVERSATIONS_USER_CHANNEL_MESSAGE_SENT', true),
            'message_read' => env('CONVERSATIONS_USER_CHANNEL_MESSAGE_READ', true),
        ],

        // Driver-specific configurations
        'drivers' => [
            'pusher' => [
                // Uses Laravel's pusher configuration by default
                'use_laravel_config' => true,
                // Override Pusher options if needed
                'options' => [
                    'cluster' => env('PUSHER_APP_CLUSTER'),
                    'encrypted' => true,
                ],
            ],

            'laravel-websockets' => [
                // Uses the same configuration as Pusher
                'use_pusher_config' => true,
            ],

            'firebase' => [
                'credentials_file' => env('FIREBASE_CREDENTIALS_FILE'),
                'database_url' => env('FIREBASE_DATABASE_URL'),
            ],

            'mqtt' => [
                'host' => env('MQTT_HOST', 'localhost'),
                'port' => env('MQTT_PORT', 1883),
                'username' => env('MQTT_USERNAME'),
                'password' => env('MQTT_PASSWORD'),
                'client_id' => env('MQTT_CLIENT_ID', 'laravel_conversations'),
            ],

            'socketio' => [
                'server' => env('SOCKETIO_SERVER', 'http://localhost:6001'),
                'auth_endpoint' => '/broadcasting/auth',
            ],
        ],
    ],
);
