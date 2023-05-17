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
    'user_primary_key' => 'id',

    'related' => [],

    'tables' => [
        'conversations' => 'conversations',
        'conversation_relations' => 'conversation_relations',
        'conversation_users' => 'conversation_users',
        'conversation_messages' => 'conversation_messages',
        'conversation_message_statuses' => 'conversation_message_statuses',
    ],
);