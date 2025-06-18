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
 * Class ConversationMessageReaction
 * @package Dominservice\Conversations\Models\Eloquent
 */
class ConversationMessageReaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'message_id',
        'reaction',
        // user_id or user_uuid will be filled based on the user model's key type
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversation_message_reactions', 'conversation_message_reactions');
    }

    /**
     * Get the message that owns the reaction.
     */
    public function message()
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id');
    }

    /**
     * Get the user who created the reaction.
     */
    public function user()
    {
        $userModel = config('conversations.user_model');
        $userKey = get_user_key();

        return $this->belongsTo($userModel, $userKey);
    }

    /**
     * Scope a query to only include reactions by a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, $userId)
    {
        $userKey = get_user_key();
        return $query->where($userKey, $userId);
    }

    /**
     * Scope a query to only include reactions with a specific emoji.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $reaction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithReaction($query, $reaction)
    {
        return $query->where('reaction', $reaction);
    }
}
