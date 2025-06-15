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
 * @version   1.0.0
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
