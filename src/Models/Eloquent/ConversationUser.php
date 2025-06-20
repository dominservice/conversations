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
 * Class ConversationUser
 * @package Dominservice\Conversations\Models\Eloquent
 */
class ConversationUser extends Model
{
    public $timestamps = false;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversation_users');
    }
}
