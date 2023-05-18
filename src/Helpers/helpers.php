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
     * @param $convUuid
     * @param $content
     * @param false $addUser
     * @param false $getObject
     * @return \Dominservice\Conversations\Models\Eloquent\ConversationMessage|false|int
     */
    function conversation_add_message($convUuid, $content, $addUser = false, $getObject = false)
    {
        return (new Dominservice\Conversations\Conversations)->addMessage($convUuid, $content, $addUser, $getObject);
    }
}

if (!function_exists('conversation_user_exists')) {
    /**
     * @param $convUuid
     * @param null $userId
     * @return bool
     */
    function conversation_user_exists($convUuid, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        return (new Dominservice\Conversations\Conversations)->existsUser($convUuid, $userId);
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

if (!function_exists('conversation_unread_count_per_id')) {
    /**
     * @param $convUuid
     * @param null $userId
     * @return mixed
     */
    function conversation_unread_count_per_id($convUuid, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        return (new Dominservice\Conversations\Conversations)->getConversationUnreadCount($convUuid, $userId);
    }
}

if (!function_exists('conversation_delete')) {
    /**
     * @param $convUuid
     * @param null $userId
     */
    function conversation_delete($convUuid, $userId = null)
    {
        if (!empty($convUuid)) {
            $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
            (new Dominservice\Conversations\Conversations)->delete($convUuid, $userId);
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
     * @param $convUuid
     * @param null $userId
     * @param bool $newToOld
     * @param null $limit
     * @param null $start
     * @return mixed
     */
    function conversation_messages($convUuid, $userId = null, $newToOld = true, $limit = null, $start = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        return (new Dominservice\Conversations\Conversations)->getMessages($convUuid, $userId, $newToOld, $limit, $start);
    }
}

if (!function_exists('conversation_messages_unread')) {
    /**
     * @param $convUuid
     * @param null $userId
     * @param bool $newToOld
     * @return mixed
     */
    function conversation_messages_unread($convUuid, $userId = null, $newToOld = true, $limit = null, $start = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        return (new Dominservice\Conversations\Conversations)->getUnreadMessages($convUuid, $userId, $newToOld, $limit, $start);
    }
}

// Mark messages as DELETED | UNREAD | READ | ARCHIVED

if (!function_exists('conversation_mark_as_archived')) {
    /**
     * @param $convUuid
     * @param $msgId
     * @param null $userId
     */
    function conversation_mark_as_archived($convUuid, $msgId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        (new Dominservice\Conversations\Conversations)->markAsArchived($convUuid, $msgId, $userId);
    }
}

if (!function_exists('conversation_mark_as_deleted')) {
    /**
     * @param $convUuid
     * @param $msgId
     * @param null $userId
     */
    function conversation_mark_as_deleted($convUuid, $msgId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        (new Dominservice\Conversations\Conversations)->markAsDeleted($convUuid, $msgId, $userId);
    }
}

if (!function_exists('conversation_mark_as_unread')) {
    /**
     * @param $convUuid
     * @param $msgId
     * @param null $userId
     */
    function conversation_mark_as_unread($convUuid, $msgId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        (new Dominservice\Conversations\Conversations)->markAsUnread($convUuid, $msgId, $userId);
    }
}

if (!function_exists('conversation_mark_as_read')) {
    /**
     * @param $convUuid
     * @param $msgId
     * @param null $userId
     */
    function conversation_mark_as_read($convUuid, $msgId, $userId = null)
    {
        $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
        (new Dominservice\Conversations\Conversations)->markAsRead($convUuid, $msgId, $userId);
    }
}

if (!function_exists('conversation_mark_as_read_all')) {
    /**
     * @param $convUuid
     * @param null|int $userId
     */
    function conversation_mark_as_read_all($convUuid, $userId = null)
    {
        if (!empty($convUuid)) {
            $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
            (new Dominservice\Conversations\Conversations)->markReadAll($convUuid, $userId);
        }
    }
}

if (!function_exists('conversation_mark_as_unread_all')) {
    /**
     * @param $convUuid
     * @param null $userId
     */
    function conversation_mark_as_unread_all($convUuid, $userId = null)
    {
        if (!empty($convUuid)) {
            $userId = !$userId && \Auth::check() ? \Auth::user()->id : $userId;
            (new Dominservice\Conversations\Conversations)->markUnreadAll($convUuid, $userId);
        }
    }
}

if (!function_exists('get_sender_key')) {
    /**
     * @param $convUuid
     * @param null $userId
     */
    function get_sender_key()
    {
        $userModel = new (config('conversations.user_model'));
        return $userModel->getKeyType() === 'uuid' ? 'sender_uuid' : 'sender_id';
    }
}

if (!function_exists('get_user_key')) {
    /**
     * @param $convUuid
     * @param null $userId
     */
    function get_user_key()
    {
        $userModel = new (config('conversations.user_model'));
        return $userModel->getKeyType() === 'uuid' ? 'user_uuid' : 'user_id';
    }
}
