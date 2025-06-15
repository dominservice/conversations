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

namespace Dominservice\Conversations\Models\Eloquent;


use Illuminate\Database\Eloquent\Model;

/**
 * Class MessageStatus
 * @package Dominservice\Conversations\Models\Eloquent
 */
class ConversationMessageStatus extends Model
{
    public $timestamps = false;
    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversation_message_statuses');
    }

    public function message() {
        return $this->hasOne(ConversationMessage::class, 'id', 'message_id');
    }

    /**
     * Get the user that owns the message status.
     */
    public function user() {
        $userModel = config('conversations.user_model');
        $userKey = get_user_key();

        return $this->belongsTo($userModel, $userKey);
    }
}
