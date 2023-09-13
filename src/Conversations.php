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
use Dominservice\Conversations\Models\Eloquent\ConversationMessage;
use Dominservice\Conversations\Models\Eloquent\ConversationMessageStatus;
use function PHPUnit\Framework\isInstanceOf;

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
        $this->messagesTable = DB::getTablePrefix() . (new ConversationMessage())->getTable();
        $this->messagesStatusTable = DB::getTablePrefix() . (new ConversationMessageStatus())->getTable();
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
            $conversation->owner_uuid = \Auth::user()->{\Auth::user()->getKeyName()};
            $conversation->save();
            $this->setRelations($conversation, $relationType, $relationId);
            $this->setUsers($conversation, $users);

            if (!empty($content)) {
                $this->addMessage($conversation->uuid, $content);
            }
            if ($getObject) {
                return $conversation;
            }

            return $conversation->uuid;
        }
        return false;
    }

    /**
     * @param $convUuid
     * @param $content
     * @param false $addUser
     * @param false $getObject
     * @return ConversationMessage|false|int
     */
    public function addMessage($convUuid, $content, $addUser = false, $getObject = false)
    {
        if (!empty($convUuid)
            && !empty($content)
            && (($convUuid instanceof Conversation && $conversation = $convUuid)
                || (is_string($convUuid) && $conversation = $this->get($convUuid)))
        ) {
//        if (!empty($convUuid) && !empty($content) && $conversation = $this->get($convUuid)) {
            $conversation->save();

            $userId = \Auth::user()->{\Auth::user()->getKeyName()};

            if (!$this->existsUser($conversation->uuid, $userId)) {
                if ($addUser) {
                    $this->setUsers($conversation, $userId);
                } else {
                    return false;
                }
            }

            $message = new ConversationMessage();
            $message->{get_sender_key()} = $userId;
            $message->conversation_uuid = $conversation->uuid;
            $message->content = $content;
            $message->save();

            //get all users in conversation
//            $usersInConv = $conversation->users()->get() ?? [];
            $usersInConv = $conversation->users ?? [];

            //and add msg status for each user in conversation
            $dataMessageStatuses = [];

            foreach ($usersInConv as $userInConv) {
                $dataMessageStatuses[] = [
                    get_user_key() => $userInConv->{\Auth::user()->getKeyName()},
                    'message_id' => $message->id,
                    'self' => $userInConv->id == $userId ? 1 : 0,
                    'status' => $userInConv->id == $userId ? self::READ : self::UNREAD,
                ];
//                $messageStatus = new ConversationMessageStatus();
//                $messageStatus->{get_user_key()} = $userInConv->{\Auth::user()->getKeyName()};
//                $messageStatus->message_id = $message->id;
//                if ($userInConv->id == $userId) { //its the sender user
//                    $messageStatus->self = 1;
//                    $messageStatus->status = self::READ;
//                } else { //other users in conv
//                    $messageStatus->self = 0;
//                    $messageStatus->status = self::UNREAD;
//                }
//                $messageStatus->save();
            }

            \DB::table((new ConversationMessageStatus)->getTable())->insert($dataMessageStatuses);

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
        $query = ConversationUser::select('conversation_uuid');
        $uT = DB::getTablePrefix().(new ConversationUser)->getTable();
        $cT = DB::getTablePrefix().(new Conversation)->getTable();

        if (!empty($relationType) && !empty($relationId)) {
            $query->whereRaw(DB::Raw("(SELECT COUNT(`parent_id`)
                    FROM `{$cT}`
                    WHERE `{$cT}`.`conversation_uuid`=`{$uT}`.`conversation_uuid`
                     AND `{$cT}`.`parent_id`='{$relationId}'
                     AND `{$cT}`.`parent_type`='{$relationType}'
                ) > 0"));
        }

        $query->whereIn(get_user_key(), $users)
            ->havingRaw("(SELECT COUNT(`user_uuid`) FROM `{$uT}` u
                WHERE u.`conversation_uuid` = `{$uT}`.`conversation_uuid`) = " . count($users))
            ->groupBy('conversation_uuid')
            ->havingRaw("COUNT(DISTINCT conversation_uuid)=" . 1)
            ->havingRaw("COUNT(DISTINCT user_uuid)=" . count($users))
        ;
//dd(sql_with_bindings($query));
        if ($results = $query->first()) {
            return $results->conversation_uuid;
        }

        return false;
    }

    /**
     * @param $convUuid
     * @param $userId
     * @return bool
     */
    public function existsUser($convUuid, $userId): bool
    {
        $resp = ConversationUser::where(get_user_key(), $userId)
            ->where('conversation_uuid', $convUuid)
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
        return ConversationMessageStatus::where(get_user_key(), $userId)->where('status', self::UNREAD)->count();
    }

    /**
     * @param $convUuid
     * @param $userId
     * @return mixed
     */
    public function getConversationUnreadCount($convUuid, $userId)
    {
        return ConversationMessageStatus::whereHas('message', function ($q) use ($userId, $convUuid) {
            $q->where(get_sender_key(), '!=', $userId);
            $q->where('conversation_uuid', $convUuid);
        })
            ->where(get_user_key(), $userId)
            ->where('status', self::UNREAD)
            ->count();
    }

    /**
     * @param $convUuid
     * @param $userId
     */
    public function delete($convUuid, $userId)
    {
        $messageStatuses = ConversationMessageStatus::whereHas('message', function ($q) use ($convUuid) {
            $q->where('conversation_uuid', $convUuid);
        })
            ->where(get_user_key(), $userId)
            ->get();

        if($messageStatuses) {
            foreach ($messageStatuses as $messageStatus) {
                $messageStatus->status = self::DELETED;
                $messageStatus->save();
            }
        }

        $noDeletedCount = ConversationMessageStatus::whereHas('message', function ($q) use ($convUuid) {
            $q->where('conversation_uuid', $convUuid);
        })
            ->whereNotIn('status', [self::DELETED, self::ARCHIVED])
            ->count();

        if ($noDeletedCount === 0 && $con = Conversation::uuid($convUuid)) {
            $con->messages()->delete();
            $con->relations()->delete();
            ConversationUser::where('conversation_uuid', $convUuid)->delete();
            $con->delete();
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
        $cT = DB::getTablePrefix().(new Conversation)->getTable();
        $conversation = Conversation::with(['users', 'relations'])
            ->whereRaw(DB::Raw("(SELECT COUNT(`message_id`)
                    FROM `{$this->messagesTable}`
                    INNER JOIN `{$this->messagesStatusTable}` ON `{$this->messagesTable}`.`id`=`{$this->messagesStatusTable}`.`message_id`
                    WHERE `".get_user_key()."`='{$userId}' AND `{$this->messagesStatusTable}`.`status` NOT IN ('".self::DELETED."', '".self::ARCHIVED."')
                ) > 0"))
            ->select('*', DB::Raw("(SELECT COUNT(`message_id`)
                    FROM `{$this->messagesTable}`
                    INNER JOIN `{$this->messagesStatusTable}` ON `{$this->messagesTable}`.`id`=`{$this->messagesStatusTable}`.`message_id`
                    WHERE `".get_user_key()."`='{$userId}' AND `{$this->messagesStatusTable}`.`status`=('".self::UNREAD."')
                        AND `{$this->messagesTable}`.`conversation_uuid`=`{$cT}`.`id`
                ) as count_unread"));

        if ($relationType !== null && $relationId !== null) {
            $relT = (new ConversationRelation)->getTable();
            $cT = (new Conversation)->getTable();
            $conversation->select($cT.'.*');
            $conversation->join($relT, $relT.'.conversation_uuid', $cT.'.id');
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
     * @param $convUuid
     * @param $userId
     * @param bool $newToOld
     * @param null $limit
     * @param null $start
     * @return mixed
     */
    public function getMessages($convUuid, $userId, $newToOld = true, $limit = null, $start = null)
    {
        if ($newToOld) {
            $orderBy = 'asc';
        } else {
            $orderBy = 'desc';
        }
        $messageT = (new ConversationMessage())->getTable();
        $messageStatusT = (new ConversationMessageStatus())->getTable();
        $output =  ConversationMessageStatus::select(
            DB::Raw("`{$this->messagesTable}`.`id` as `message_id`"),
            $messageT.'.content',
            $messageStatusT.'.status',
            $messageT.'.created_at',
            DB::Raw("`{$this->messagesTable}`.`".get_sender_key()."` as `".get_user_key()."`")
        )
            ->join($messageT, $messageT.'.id', $messageStatusT.'.message_id')
            ->where($messageT.'.conversation_uuid', $convUuid)
            ->where($messageStatusT.'.'.get_user_key(), $userId)
            ->whereNotIn($messageStatusT.'.status', [self::DELETED, self::ARCHIVED])
            ->orderBy($messageT.'.created_at', $orderBy);

        if (!is_null($limit) && !is_null($start)) {
            $output->offset($start)->limit($limit);
        }

        return $output->get();
    }

    /**
     * @param $convUuid
     * @param $userId
     * @param bool $newToOld
     * @param null $limit
     * @param null $start
     * @return mixed
     */
    public function getUnreadMessages($convUuid, $userId, $newToOld = true, $limit = null, $start = null)
    {
        if ($newToOld) {
            $orderBy = 'desc';
        } else {
            $orderBy = 'asc';
        }
        $messageT = (new ConversationMessage())->getTable();
        $messageStatusT = (new ConversationMessageStatus())->getTable();
        $output = ConversationMessageStatus::select(
            DB::Raw("`{$this->messagesTable}`.`id` as `msg_id`"),
            $this->messagesTable.'.content',
            $this->messagesStatusTable.'.status',
            $this->messagesTable.'.created_at',
            DB::Raw("`{$this->messagesTable}`.`".get_sender_key()."` as `".get_user_key()."`")
        )
            ->join($messageT, $messageT.'.id', $messageStatusT.'.message_id')
            ->where($messageT.'.conversation_uuid', $convUuid)
            ->where($messageStatusT.'.'.get_user_key(), $userId)
            ->where($messageStatusT.'.status', self::UNREAD)
            ->orderBy($messageT.'.created_at', $orderBy);

        if (!is_null($limit) && !is_null($start)) {
            $output->offset($start)->limit($limit);
        }

        return $output->get();
    }

    /**
     * @param $convUuid
     * @param $msgId
     * @param $userId
     * @param $status
     */
    public function markAs($convUuid, $msgId, $userId, $status): void
    {
        $messageStatus = ConversationMessageStatus::whereHas('message', function ($q) use ($userId, $convUuid) {
            $q->where(get_sender_key(), '!=', $userId);
            $q->where('conversation_uuid', $convUuid);
        })
            ->where('status', '!=', $status)
            ->where('message_id', $msgId)
            ->where(get_user_key(), $userId)
            ->first();

        if (is_int($status)
            && $status >= 0
            && $status <= 3
            && $messageStatus
        ) {
            $messageStatus->status = $status;
            $messageStatus->save();
        }
    }

    /**
     * @param $convUuid
     * @param $msgId
     * @param $userId
     */
    public function markAsRead($convUuid, $msgId, $userId): void
    {
        $this->markAs($convUuid, $msgId, $userId, self::READ);
    }

    /**
     * @param $convUuid
     * @param $msgId
     * @param $userId
     */
    public function markAsUnread($convUuid, $msgId, $userId): void
    {
        $this->markAs($convUuid, $msgId, $userId, self::UNREAD);
    }

    /**
     * @param $convUuid
     * @param $msgId
     * @param $userId
     */
    public function markAsDeleted($convUuid, $msgId, $userId): void
    {
        $this->markAs($convUuid, $msgId, $userId, self::DELETED);
    }

    /**
     * @param $convUuid
     * @param $msgId
     * @param $userId
     */
    public function markAsArchived($convUuid, $msgId, $userId): void
    {
        $this->markAs($convUuid, $msgId, $userId, self::ARCHIVED);
    }

    /**
     * @param $convUuid
     * @param $userId
     */
    public function markReadAll($convUuid, $userId)
    {
        $messageStatuses = ConversationMessageStatus::whereHas('message', function ($q) use ($userId, $convUuid) {
            $q->where(get_sender_key(), '!=', $userId);
            $q->where('conversation_uuid', $convUuid);
        })
            ->where('status', self::UNREAD)
            ->where(get_user_key(), $userId)
            ->get();

        if($messageStatuses) {
            foreach ($messageStatuses as $messageStatus) {
                $messageStatus->status = self::READ;
                $messageStatus->save();
            }
        }
    }

    /**
     * @param $convUuid
     * @param $userId
     */
    public function markUnreadAll($convUuid, $userId)
    {
        $messageStatuses = ConversationMessageStatus::whereHas('message', function ($q) use ($userId, $convUuid) {
            $q->where(get_sender_key(), '!=', $userId);
            $q->where('conversation_uuid', '!=', $convUuid);
        })
            ->where('status', self::READ)
            ->where(get_user_key(), $userId)
            ->get();

        if($messageStatuses) {
            foreach ($messageStatuses as $messageStatus) {
                $messageStatus->status = self::UNREAD;
                $messageStatus->save();
            }
        }
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function get($uuid)
    {
        return Conversation::uuid($uuid);
    }

    /**
     * @param $conversation
     * @return mixed
     */
    public function getRelations(&$conversation)
    {
        if (empty($conversation->relations)) {
            $conversation->relations = $conversation->relations()->with(['parent', 'uuidParent'])->get();
        }

        if (!empty($conversation->relations)) {
            $relations = collect();
            foreach ($conversation->relations as $relation) {
                if ($relation->parent) {
                    $relations->push($relation->parent);
                }

                if ($relation->uuidParent) {
                    $relations->push($relation->uuidParent);
                }

                $conversation->relationsList = $relations;
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
                && !$exists = ConversationRelation::where('conversation_uuid', $conversation->uuid)
                    ->where('parent_type', $relationType)
                    ->where('parent_id', $relationId)
                    ->first()
            ) {
                $relation = new ConversationRelation();
                $relation->conversation_uuid = $conversation->uuid;
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
        } elseif (!$exists = ConversationUser::where('conversation_uuid', $conversation->uuid)
            ->where(get_user_key(), $userId)->first()
        ) {
            $conversationUser = new ConversationUser();
            $conversationUser->conversation_uuid = $conversation->uuid;
            $conversationUser->{get_user_key()} = $userId;
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
        if (!in_array(auth()->user()->{auth()->user()->getKeyName()}, $users)) {
            $users[] = auth()->user()->{auth()->user()->getKeyName()};
        }

        return $users;
    }
}
