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

namespace Dominservice\Conversations;

use DB;
use Dominservice\Conversations\Models\Eloquent\Conversation;
use Dominservice\Conversations\Models\Eloquent\ConversationRelation;
use Dominservice\Conversations\Models\Eloquent\ConversationUser;
use Dominservice\Conversations\Models\Eloquent\Message;
use Dominservice\Conversations\Models\Eloquent\MessageStatus;
use Illuminate\Support\Facades\Config;

/**
 * Class Conversations
 * @package Dominservice\Conversations
 */
class Conversations
{

    const DELETED = 0;
    const UNREAD = 1;
    const READ = 2;
    const ARCHIVED = 3;

    protected $messagesTable;
    protected $messagesStatusTable;

    /**
     * Conversations constructor.
     */
    public function __construct() {
        $this->messagesTable = DB::getTablePrefix() . (new Message())->getTable();
        $this->messagesStatusTable = DB::getTablePrefix() . (new MessageStatus())->getTable();
    }

    /**
     * @param $users
     * @param null $relationType
     * @param null $relationId
     * @param null $content
     * @param false $getObject
     * @return Conversation|int|bool
     */
    public function create($users, $relationType = null, $relationId = null, $content = null, $getObject = false)
    {
        $users = $this->userIds($users);

        if (count((array)$users) > 1) {
            $conversation = new Conversation();
            $conversation->save();
            $this->setRelations($conversation, $relationType, $relationId);
            $this->setUsers($conversation, $users);

            if (!empty($content)) {
                $this->addMessage($conversation->id, $content);
            }
            if ($getObject) {
                return $conversation;
            }

            return $conversation->id;
        }
        return false;
    }

    /**
     * @param $convId
     * @param $content
     * @param false $addUser
     * @param false $getObject
     * @return Message|false|int
     */
    public function addMessage($convId, $content, $addUser = false, $getObject = false)
    {
        if (!empty($convId) && !empty($content) && $conversation = $this->get($convId)) {
            $conversation->save();
            $userId = auth()->user()->id;

            if (!$this->existsUser($convId, $userId)) {
                if ($addUser) {
                    $this->setUsers($conversation, $users);
                } else {
                    return false;
                }
            }

            $message = new Message();
            $message->sender_id = $userId;
            $message->conversation_id = $convId;
            $message->content = $content;
            $message->save();

            //get all users in conversation
            $usersInConv = $conversation->users()->get() ?? [];

            //and add msg status for each user in conversation
            foreach ($usersInConv as $userInConv) {
                $messageStatus = new MessageStatus();
                $messageStatus->user_id = $userInConv->id;
                $messageStatus->message_id = $message->id;
                if ($userInConv->id == $userId) { //its the sender user
                    $messageStatus->self = 1;
                    $messageStatus->status = self::READ;
                } else { //other users in conv
                    $messageStatus->self = 0;
                    $messageStatus->status = self::UNREAD;
                }
                $messageStatus->save();
            }

            if ($getObject) {
                return $message;
            }
            return (int)$message->id;
        }

        return false;
    }

    /**
     * @param $users
     * @param $content
     * @param null $relationType
     * @param null $relationId
     * @return false|int
     */
    public function addMessageOrCreateConversation($users, $content, $relationType = null, $relationId = null)
    {
        $users = $this->userIds($users);
        $conv = $this->getIdBetweenUsers($users, $relationType, $relationId);
        if ($conv === null) {
            $conv = $this->create($users, $relationType, $relationId);
        }
        if($conv) {
            return $this->addMessage($conv, $content, true);
        }

        return false;
    }

    /**
     * @param array $users
     * @param null $relationType
     * @param null $relationId
     * @return false|int
     */
    public function getIdBetweenUsers(array $users, $relationType = null, $relationId = null)
    {
        $results = ConversationUser::select('conversation_id');
        if (!empty($relationType) && !empty($relationId)) {
            $uT = DB::getTablePrefix().(new ConversationUser)->getTable();
            $cT = DB::getTablePrefix().(new Conversation)->getTable();
            $results->whereRaw(DB::Raw("(SELECT COUNT(`parent_id`)
                    FROM `{$cT}`
                    WHERE `{$cT}`.`conversation_id`=`{$uT}`.`conversation_id`
                     AND `{$cT}`.`parent_id`='{$relationId}'
                     AND `{$cT}`.`parent_type`='{$relationType}'
                ) > 0"));
        }
        $results->whereIn('user_id', $users)
            ->groupBy('conversation_id')
            ->havingRaw("COUNT(conversation_id)=2");

        if ($results = $results->first()) {
            return (int)$results->conversation_id;
        }

        return false;
    }

    /**
     * @param $convId
     * @param $userId
     * @return bool
     */
    public function existsUser($convId, $userId): bool
    {
        $resp = ConversationUser::where('user_id', $userId)
            ->where('conversation_id', $convId)
            ->count();
        if($resp) {
            return true;
        }
        return false;
    }

    /**
     * @param $userId
     * @return mixed
     */
    public function getUnreadCount($userId)
    {
        return MessageStatus::where('user_id', $userId)->where('status', self::UNREAD)->count();
    }

    /**
     * @param int $convId
     * @param $userId
     * @return mixed
     */
    public function getConversationUnreadCount(int $convId, $userId)
    {
        return MessageStatus::whereRaw(DB::Raw("message_id IN (SELECT `msg`.`id`
              FROM `{$this->messagesTable}` `msg`
              WHERE `msg`.`conversation_id`='{$convId}')"))
            ->where('user_id', $userId)
            ->where('status', self::UNREAD)
            ->count();
    }

    /**
     * @param $convId
     * @param $userId
     */
    public function delete($convId, $userId)
    {
        $messageStatuses = MessageStatus::whereIn('message_id', DB::Raw("SELECT `msg`.`id`
              FROM `{$this->messagesTable}` `msg`
              WHERE `msg`.`conversation_id`='{$convId}'"))
            ->where('user_id', $userId)
            ->get();

        if($messageStatuses) {
            foreach ($messageStatuses as $messageStatus) {
                $messageStatus->status = self::DELETED;
                $messageStatus->save();
            }
        }

        $noDeletedCount = MessageStatus::whereIn('message_id', DB::Raw("SELECT `msg`.`id`
              FROM `{$this->messagesTable}` `msg`
              WHERE `msg`.`conversation_id`='{$convId}'"))
            ->whereNotIn('status', [self::DELETED, self::ARCHIVED])
            ->count();

        if ($noDeletedCount === 0 && $con = Conversation::find($convId)) {
            $users = ConversationUser::where('conversations_id', $convId)->get();
            $messages = $con->messages;
            $relations = $con->relations;
            $statuses = MessageStatus::whereIn('message_id', DB::Raw("SELECT `msg`.`id`
                  FROM `{$this->messagesTable}` `msg`
                  WHERE `msg`.`conversation_id`='{$convId}'"))
                ->get();
            foreach ($users as $user) {
                $user->delete();
            }
            foreach ($messages as $message) {
                $message->delete();
            }
            foreach ($relations as $relation) {
                $relation->delete();
            }
            foreach ($statuses as $statuse) {
                $statuse->delete();
            }
        }
    }

    /**
     * @param $userId
     * @param null $relationType
     * @param null $relationId
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getConversations($userId, $relationType = null, $relationId = null)
    {
        $cT = get_db_prefix().(new Conversation)->getTable();
        $conversation = Conversation::with(['users', 'relations'])
            ->whereRaw(DB::Raw("(SELECT COUNT(`message_id`)
                    FROM `{$this->messagesTable}`
                    INNER JOIN `{$this->messagesStatusTable}` ON `{$this->messagesTable}`.`id`=`{$this->messagesStatusTable}`.`message_id`
                    WHERE `user_id`='{$userId}' AND `{$this->messagesStatusTable}`.`status` NOT IN ('".self::DELETED."', '".self::ARCHIVED."')
                ) > 0"))
            ->select('*', DB::Raw("(SELECT COUNT(`message_id`)
                    FROM `{$this->messagesTable}`
                    INNER JOIN `{$this->messagesStatusTable}` ON `{$this->messagesTable}`.`id`=`{$this->messagesStatusTable}`.`message_id`
                    WHERE `user_id`='{$userId}' AND `{$this->messagesStatusTable}`.`status`=('".self::UNREAD."')
                        AND `{$this->messagesTable}`.`conversation_id`=`{$cT}`.`id`
                ) as count_unread"));

        if ($relationType !== null && $relationId !== null) {
            $relT = (new ConversationRelation)->getTable();
            $cT = (new Conversation)->getTable();
            $conversation->select($cT.'.*');
            $conversation->join($relT, $relT.'.conversation_id', $cT.'.id');
            $conversation->where($relT.'.parent_type', $relationType)->where($relT.'.parent_id', $relationId);
        }

        $data = $conversation->orderBy('updated_at', 'desc')->get();

        foreach ($data as &$datum) {
            $datum->human_date = now()->diffForHumans($datum->updated_at, true);
            $this->getRelations($datum);
        }

        return $data;
    }

    /**
     * @param $convId
     * @param $userId
     * @param bool $newToOld
     * @param null $limit
     * @param null $start
     * @return mixed
     */
    public function getMessages($convId, $userId, $newToOld = true, $limit = null, $start = null)
    {
        if ($newToOld) {
            $orderBy = 'desc';
        } else {
            $orderBy = 'asc';
        }
        $messageT = (new Message())->getTable();
        $messageStatusT = (new MessageStatus())->getTable();
        $output =  MessageStatus::select(
            DB::Raw("`{$this->messagesTable}`.`id` as `message_id`"),
            $messageT.'.content',
            $messageStatusT.'.status',
            $messageT.'.created_at',
            DB::Raw("`{$this->messagesTable}`.`sender_id` as `user_id`")
        )
            ->join($messageT, $messageT.'.id', $messageStatusT.'.message_id')
            ->where($messageT.'.conversation_id', $convId)
            ->where($messageStatusT.'.user_id', $userId)
            ->whereNotIn($messageStatusT.'.status', [self::DELETED, self::ARCHIVED])
            ->orderBy($messageT.'.created_at', $orderBy);

        if (!is_null($limit) && !is_null($start)) {
            $output->offset($start)->limit($limit);
        }

        return $output->get();
    }

    /**
     * @param $convId
     * @param $userId
     * @param bool $newToOld
     * @param null $limit
     * @param null $start
     * @return mixed
     */
    public function getUnreadMessages($convId, $userId, $newToOld = true, $limit = null, $start = null)
    {
        if ($newToOld) {
            $orderBy = 'desc';
        } else {
            $orderBy = 'asc';
        }
        $messageT = (new Message())->getTable();
        $messageStatusT = (new MessageStatus())->getTable();
        $output = MessageStatus::select(
            DB::Raw("`{$this->messagesTable}`.`id` as `msg_id`"),
            $this->messagesTable.'.content',
            $this->messagesStatusTable.'.status',
            $this->messagesTable.'.created_at',
            DB::Raw("`{$this->messagesTable}`.`sender_id` as `user_id`")
        )
            ->join($messageT, $messageT.'.id', $messageStatusT.'.message_id')
            ->where($messageT.'.conversation_id', $convId)
            ->where($messageStatusT.'.user_id', $userId)
            ->where($messageStatusT.'.status', self::UNREAD)
            ->orderBy($messageT.'.created_at', $orderBy);

        if (!is_null($limit) && !is_null($start)) {
            $output->offset($start)->limit($limit);
        }

        return $output->get();
    }

    /**
     * @param $msgId
     * @param $userId
     * @param $status
     */
    public function markAs($msgId, $userId, $status): void
    {
        if (is_int($status)
            && $status >= 0
            && $status <= 3
            && $messageStatus = MessageStatus::where('user_id', $userId)->where('message_id', $msgId)->first()
        ) {
            $messageStatus->status = $status;
            $messageStatus->save();
        }
    }

    /**
     * @param $msgId
     * @param $userId
     */
    public function markAsRead($msgId, $userId): void
    {
        $this->markAs($msgId, $userId, self::READ);
    }

    /**
     * @param $msgId
     * @param $userId
     */
    public function markAsUnread($msgId, $userId): void
    {
        $this->markAs($msgId, $userId, self::UNREAD);
    }

    /**
     * @param $msgId
     * @param $userId
     */
    public function markAsDeleted($msgId, $userId): void
    {
        $this->markAs($msgId, $userId, self::DELETED);
    }

    /**
     * @param $msgId
     * @param $userId
     */
    public function markAsArchived($msgId, $userId): void
    {
        $this->markAs($msgId, $userId, self::ARCHIVED);
    }

    /**
     * @param $convId
     * @param $userId
     */
    public function markReadAll($convId, $userId)
    {
        $messageStatuses = MessageStatus::whereRaw(DB::Raw("message_id IN (SELECT `id`
              FROM `{$this->messagesTable}`
              WHERE `conversation_id`='{$convId}'
              AND `sender_id`!='{$userId}')"))
            ->where('status', self::UNREAD)
            ->where('user_id', $userId)
            ->get();

        if($messageStatuses) {
            foreach ($messageStatuses as $messageStatus) {
                $messageStatus->status = self::READ;
                $messageStatus->save();
            }
        }
    }

    /**
     * @param $convId
     * @param $userId
     */
    public function markUnreadAll($convId, $userId)
    {
        $messagesT = DB::getTablePrefix() . (new Message())->getTable();
        $messageStatuses = MessageStatus::whereRaw(DB::Raw("message_id IN (SELECT `id`
              FROM `{$messagesT}`
              WHERE `conversation_id`='{$convId}'
              AND `sender_id`!='{$userId}')"))
            ->where('status', self::READ)
            ->where('user_id', $userId)
            ->get();

        if($messageStatuses) {
            foreach ($messageStatuses as $messageStatus) {
                $messageStatus->status = self::UNREAD;
                $messageStatus->save();
            }
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public function get($id)
    {
        return Conversation::find($id);
    }

    /**
     * @param $conversation
     * @return mixed
     */
    public function getRelations(&$conversation)
    {
        $config = \Config::get('conversations.related', []);

        if (empty($conversation->relations)) {
            $conversation->relations = $conversation->relations()->get();
        }

        if (!empty($conversation->relations)) {
            foreach ($conversation->relations as $id=>$relation) {
                if (!empty($config[$relation->parent_type])) {
                    $data = $config[$relation->parent_type]::where('id', $relation->parent_id)->first();
                    $data->relation_type = $relation->parent_type;
                    $conversation->relations[$id] = $data;
                }
            }
        }

        return $conversation;
    }

    /**
     * @param $conversation
     * @param null $relationType
     * @param null $relationId
     */
    private function setRelations($conversation, $relationType = null, $relationId = null)
    {
        $relations = $conversation->relations ?? collect();

        if (!is_null($relationType)) {
            if (is_array($relationType)) {
                foreach ($relationType as $list) {
                    foreach ($list as $type=>$id) {
                        $this->setRelations($conversation, $type, $id);
                    }                }
            } elseif (!is_null($relationId)
                && !$exists = ConversationRelation::where('conversation_id', $conversation->id)
                    ->where('parent_type', $relationType)
                    ->where('parent_id', $relationId)
                    ->first()
            ) {
                $relation = new ConversationRelation();
                $relation->conversation_id = $conversation->id;
                $relation->parent_type = $relationType;
                $relation->parent_id = $relationId;

                $relation->save();
                $relations[] = $relation;
            }
        }
        $conversation->relations->push($relations);
    }

    /**
     * @param $conversation
     * @param $userId
     */
    private function setUsers(&$conversation, $userId)
    {
        $users = $conversation->users ?? collect();

        if (is_array($userId)) {
            foreach ($userId as $id) {
                $this->setUsers($conversation, $id);
            }
        } elseif (!$exists = ConversationUser::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)->first()
        ) {
            $conversationUser = new ConversationUser();
            $conversationUser->conversation_id = $conversation->id;
            $conversationUser->user_id = $userId;
            $conversationUser->save();
            $users[] = $conversationUser;
        }
        $conversation->users->push($users);
    }

    /**
     * @param $users
     * @return array
     */
    private function userIds($users): array
    {
        if (!is_array($users)) {
            $users = [$users];
        }
        if (!in_array(auth()->user()->id, $users)) {
            $users[] = auth()->user()->id;
        }

        return $users;
    }
}
