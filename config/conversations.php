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
