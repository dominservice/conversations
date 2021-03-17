<?php

/**
 * Data Locale Parser
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

if (!function_exists('conversation_create')) {
    /**
     * @param $users
     * @param null $relationType
     * @param null $relationId
     * @param null $content
     * @param false $getObject
     * @return bool|\Dominservice\Conversations\Models\Eloquent\Conversation|int
     */
    function conversation_create($users, $relationType = null, $relationId = null, $content = null, $getObject = false)
    {
        return (new Dominservice\Conversations\Conversations)->create($users, $relationType, $relationId, $content, $getObject);
    }
}

if (!function_exists('conversation_add_or_create')) {
    /**
     * @param $users
     * @param $content
     * @param null $relationType
     * @param null $relationId
     * @param false $getObject
     * @return false|int
     */
    function conversation_add_or_create($users, $content, $relationType = null, $relationId = null)
    {
        return (new Dominservice\Conversations\Conversations)->addMessageOrCreateConversation($users, $content, $relationType, $relationId);
    }
}

if (!function_exists('conversation_add_message')) {
    /**
     * @param $convId
     * @param $content
     * @param false $addUser
     * @param false $getObject
     * @return \Dominservice\Conversations\Models\Eloquent\Message|false|int
     */
    function conversation_add_message($convId, $content, $addUser = false, $getObject = false)
    {
        return (new Dominservice\Conversations\Conversations)->addMessage($convId, $content, $addUser, $getObject);
    }
}

if (!function_exists('conversation_user_exists')) {
    /**
     * @param $convId
     * @param null $userId
     * @return bool
     */
    function conversation_user_exists($convId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        return (new Dominservice\Conversations\Conversations)->existsUser($convId, $userId);
    }
}

if (!function_exists('conversation_id_between')) {
    /**
     * @param $users
     * @return false|int
     */
    function conversation_id_between($users, $relationType = null, $relationId = null)
    {
        return (new Dominservice\Conversations\Conversations)->getIdBetweenUsers($users, $relationType, $relationId);
    }
}

if (!function_exists('conversation_unread_count')) {
    /**
     * @param null $userId
     * @return mixed
     */
    function conversation_unread_count($userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        return (new Dominservice\Conversations\Conversations)->getUnreadCount($userId);
    }
}

if (!function_exists('conversation_delete')) {
    /**
     * @param $convId
     * @param null $userId
     */
    function conversation_delete($convId, $userId = null)
    {
        if (!empty($convId)) {
            $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
            (new Dominservice\Conversations\Conversations)->delete($convId, $userId);
        }
    }
}

if (!function_exists('conversations')) {
    /**
     * @param null $userId
     * @param null $relationType
     * @param null $relationId
     * @return array
     */
    function conversations($userId = null, $relationType = null, $relationId = null, $withUsersList = true)
    {
        $users = collect([]);
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        $conversations = (new Dominservice\Conversations\Conversations)->getConversations($userId, $relationType, $relationId);
        if ($withUsersList) {
            foreach ($conversations as $conv) {
                if ($conv->users) {
                    foreach ($conv->users as $user) {
                        if (empty($users[$user->id]) && $user->id !== \Auth::user()->id) {
                            $users[$user->id] = $user;
                        }
                    }
                }
            }
            return ['conversations' => $conversations, 'users' => $users];
        }
        return $conversations;
    }
}

if (!function_exists('conversation_messages')) {
    /**
     * @param $convId
     * @param null $userId
     * @param bool $newToOld
     * @param null $limit
     * @param null $start
     * @return mixed
     */
    function conversation_messages($convId, $userId = null, $newToOld = true, $limit = null, $start = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        return (new Dominservice\Conversations\Conversations)->getMessages($convId, $userId, $newToOld, $limit, $start);
    }
}

if (!function_exists('conversation_messages_unread')) {
    /**
     * @param $convId
     * @param null $userId
     * @param bool $newToOld
     * @return mixed
     */
    function conversation_messages_unread($convId, $userId = null, $newToOld = true, $limit = null, $start = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        return (new Dominservice\Conversations\Conversations)->getUnreadMessages($convId, $userId, $newToOld, $limit, $start);
    }
}

// Mark messages as DELETED | UNREAD | READ | ARCHIVED

if (!function_exists('conversation_mark_as_archived')) {
    /**
     * @param $msgId
     * @param null $userId
     */
    function conversation_mark_as_archived($msgId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        (new Dominservice\Conversations\Conversations)->markAsArchived($msgId, $userId);
    }
}

if (!function_exists('conversation_mark_as_deleted')) {
    /**
     * @param $msgId
     * @param null $userId
     */
    function conversation_mark_as_deleted($msgId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        (new Dominservice\Conversations\Conversations)->markAsDeleted($msgId, $userId);
    }
}

if (!function_exists('conversation_mark_as_unread')) {
    /**
     * @param $msgId
     * @param null $userId
     */
    function conversation_mark_as_unread($msgId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        (new Dominservice\Conversations\Conversations)->markAsUnread($msgId, $userId);
    }
}

if (!function_exists('conversation_mark_as_read')) {
    /**
     * @param $msgId
     * @param null $userId
     */
    function conversation_mark_as_read($msgId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        (new Dominservice\Conversations\Conversations)->markAsRead($msgId, $userId);
    }
}

if (!function_exists('conversation_mark_as_read_all')) {
    /**
     * @param $convId
     * @param null $userId
     */
    function conversation_mark_as_read_all($convId, $userId = null)
    {
        if (!empty($convId)) {
            $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
            (new Dominservice\Conversations\Conversations)->markReadAll($convId, $userId);
        }
    }
}

if (!function_exists('conversation_mark_as_unread_all')) {
    /**
     * @param $convId
     * @param null $userId
     */
    function conversation_mark_as_unread_all($convId, $userId = null)
    {
        if (!empty($convId)) {
            $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
            (new Dominservice\Conversations\Conversations)->markUnreadAll($convId, $userId);
        }
    }
}
