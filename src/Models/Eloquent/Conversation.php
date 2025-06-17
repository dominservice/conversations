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


use Dominservice\Conversations\Traits\HasUuidPrimary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Conversation
 * @package Dominservice\Conversations\Models\Eloquent
 */
class Conversation extends Model
{
    use HasUuidPrimary;
    use SoftDeletes;

    public const GROUP = 'group';
    public const COUPLE = 'couple';

    public const TYPE_SINGLE = 'single';
    public const TYPE_GROUP = 'group';
    public const TYPE_MAIL = 'mail';
    public const TYPE_SUPPORT = 'support';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'type_id',
    ];

    protected $dates = [
        'cteated_at', 'updated_at'
    ];

//    private $unreadedMessagesCount;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversations');
    }

//    protected function serializeDate($date)
//    {
//        return ($date != null) ?  $date->format('Y-m-d H:i:s') : null;
//    }

    public function users()
    {
        $userModel = \Config::get('conversations.user_model', \App\Models\User::class);

        return $this->belongsToMany(
            $userModel,
            'conversation_users',
            'conversation_uuid',
            get_user_key(),
            'uuid',
            'uuid'
        );
    }

    public function participants()
    {
        $userUuid = \Auth::check() ? \Auth::user()->{\Auth::user()->getKeyName()} : '';

        return $this->users()->where(get_user_key(), '!=', $userUuid);
    }

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_uuid', 'uuid');
    }

    public function relations()
    {
        return $this->hasMany(ConversationRelation::class, 'conversation_uuid', 'uuid');
    }

    public function type()
    {
        return $this->hasOne(ConversationType::class, 'id', 'type_id');
    }

    public function lastMessage()
    {
        return $this->hasOne(ConversationMessage::class, 'conversation_uuid', 'uuid');
    }

    public function owner()
    {
        return $this->hasOne(config('conversations.user_model'), 'uuid', 'owner_uuid');
    }

    public function getNumOfUsers()
    {
        return $this->users->count() ?? 0;
    }

    public function getNumOfMessages()
    {
        return $this->messages->count() ?? 0;
    }

    public function getTheOtherUser($userUuid = null)
    {
        $userUuid = !$userUuid && \Auth::check() ? \Auth::user()->{\Auth::user()->getKeyName()} : $userUuid;

        return $this->users()->where(get_user_key(), '!=', $userUuid)->get();
    }

    public function getFirstMessage()
    {
        return !empty($this->messages) ? $this->messages->first() : null;
    }

    /**
     * @return ConversationMessage
     */
    public function getLastMessage()
    {
        return !empty($this->messages) ? $this->messages->last() : null;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        if ($this->getNumOfUsers() > 2) {
            return self::GROUP;
        }
        return self::COUPLE;
    }

    public function getCountUnreadedMessages()
    {
        if ($this->unreadedMessagesCount === null) {
            $this->unreadedMessagesCount = conversation_unread_count_per_id($this->uuid);
        }

        return (int)$this->unreadedMessagesCount;
    }

    public function hasUnreadedMessages()
    {
        return $this->getCountUnreadedMessages() > 0;
    }

    public function getLastMessageDateHuman()
    {
        $lastMessage = $this->messages()->orderByDesc('created_at')->first();

        return now()->diffForHumans($lastMessage->created_at, true);
    }

    public function setType($typeName)
    {
        if ($type = ConversationType::where('name', $typeName)->first()) {
            $this->type_id = $type->id;
            $this->save();
        }
    }
}
