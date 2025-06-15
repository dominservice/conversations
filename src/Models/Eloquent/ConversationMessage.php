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
 * Class Message
 * @package Dominservice\Conversations\Models\Eloquent
 */
class ConversationMessage extends Model
{
    const TYPE_TEXT = 'text';
    const TYPE_ANCHOR = 'anchor';
    const TYPE_ATTACHMENT = 'attachment';

    protected $dates = [
        'cteated_at', 'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'message_type' => 'string',
    ];
    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversation_messages');
    }

    protected function serializeDate($date)
    {
        return ($date != null) ?  $date->format('Y-m-d H:i:s') : null;
    }

    public function sender() {
        $userModel = \Config::get('conversations.user_model', \App\Models\User::class);

        return $this->hasOne($userModel, (new $userModel)->getKeyType() === 'uuid' ? 'uuid' : 'id', get_sender_key());
    }

    public function status() {
        return $this->hasMany(ConversationMessageStatus::class, 'message_id', 'id');
    }

    public function statusForUser($userId = null) {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;

        if (!empty($this->status)) {
            foreach ($this->status as $status) {
                if ((int)$status->{get_user_key()} === (int)$userId) {
                    return $status;
                }
            }
        }
        return null;
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments()
    {
        return $this->hasMany(ConversationAttachment::class, 'message_id');
    }

    /**
     * Check if the message has attachments.
     *
     * @return bool
     */
    public function hasAttachments()
    {
        return $this->message_type === self::TYPE_ATTACHMENT && $this->attachments()->count() > 0;
    }

    /**
     * Get the first attachment of the message.
     *
     * @return \Dominservice\Conversations\Models\Eloquent\ConversationAttachment|null
     */
    public function getFirstAttachment()
    {
        return $this->attachments()->first();
    }

    /**
     * Get the reactions for the message.
     */
    public function reactions()
    {
        return $this->hasMany(ConversationMessageReaction::class, 'message_id');
    }

    /**
     * Check if the message has reactions.
     *
     * @return bool
     */
    public function hasReactions()
    {
        return $this->reactions()->count() > 0;
    }

    /**
     * Get reactions grouped by emoji with count.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getReactionsSummary()
    {
        return $this->reactions()
            ->select('reaction', \DB::raw('count(*) as count'))
            ->groupBy('reaction')
            ->get();
    }

    /**
     * Check if a user has reacted to this message with a specific emoji.
     *
     * @param mixed $userId
     * @param string $reaction
     * @return bool
     */
    public function hasUserReaction($userId, $reaction = null)
    {
        $query = $this->reactions()->byUser($userId);

        if ($reaction !== null) {
            $query->withReaction($reaction);
        }

        return $query->exists();
    }
}
