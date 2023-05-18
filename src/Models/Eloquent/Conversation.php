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
 * Class Conversation
 * @package Dominservice\Conversations\Models\Eloquent
 */
class Conversation  extends Model
{
    const GROUP = 'group';
    const COUPLE = 'couple';

    const TYPE_SINGLE = 'single';
    const TYPE_GROUP = 'group';
    const TYPE_MAIL = 'mail';
    const TYPE_SUPPORT = 'support';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'type',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversations');
    }

    public function users() {
        $userModel = \Config::get('conversations.user_model', \App\Models\User::class);

        return $this->belongsToMany($userModel,
            'conversation_users',
            'conversation_uuid',
            get_user_key()
        );
    }

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_uuid', 'id');
    }

    public function relations()
    {
        return $this->hasMany(ConversationRelation::class, 'conversation_uuid', 'id');
    }

    function getNumOfUsers()
    {
        return $this->users->count() ?? 0;
    }

    function getNumOfMessages()
    {
        return $this->messages->count() ?? 0;
    }

    function getTheOtherUser($userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;

        if($users = !empty($this->users) ? clone $this->users : null) {
            foreach ($users as $id=>$user) {
                if ((int)$user->id === (int)$userId) {
                    $users->forget($id);
                }
            }
        }

        return $users;
    }

    function getFirstMessage()
    {
        return !empty($this->messages) ? $this->messages->first() : null;
    }

    /**
     * @return ConversationMessage
     */
    function getLastMessage()
    {
        return !empty($this->messages) ? $this->messages->last() : null;
    }

    /**
     * @return mixed
     */
    public function getType() {
        if ( $this->getNumOfUsers() > 2 )
            return self::GROUP;
        return self::COUPLE;
    }
}
