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

namespace Dominservice\Conversations\Models\Eloquent;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Message
 * @package Dominservice\Conversations\Models\Eloquent
 */
class ConversationMessage extends Model
{
    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversation_messages');
    }
    public function sender() {
        $userModel = \Config::get('conversations.user_model', \App\Models\User::class);

        if ((new $userModel)->getKeyType() === 'uuid') {
            $userRelation = 'sender_uuid';
        } else {
            $userRelation = 'sender_id';
        }
        return $this->hasOne($userModel, 'id', $userRelation);
    }

    public function status() {
        return $this->hasMany(ConversationMessageStatus::class, 'message_id', 'id');
    }

    public function statusForUser($userId = null) {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;

        if (!empty($this->status)) {
            foreach ($this->status as $status) {
                if ((int)$status->user_id === (int)$userId) {
                    return $status;
                }
            }
        }
        return null;
    }
}
