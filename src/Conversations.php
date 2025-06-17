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
 * @version   3.0.0
 */

namespace Dominservice\Conversations;

use DB;
use Dominservice\Conversations\Models\Eloquent\Conversation;
use Dominservice\Conversations\Models\Eloquent\ConversationRelation;
use Dominservice\Conversations\Models\Eloquent\ConversationUser;
use Dominservice\Conversations\Models\Eloquent\ConversationMessage;
use Dominservice\Conversations\Models\Eloquent\ConversationMessageStatus;
use Dominservice\Conversations\Broadcasting\BroadcastManager;
use Dominservice\Conversations\Events\ConversationCreated;
use Dominservice\Conversations\Events\MessageSent;
use Dominservice\Conversations\Events\MessageRead;
use Dominservice\Conversations\Events\MessageDeleted;
use Dominservice\Conversations\Events\UserTyping;
use Illuminate\Support\Str;

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
     * The broadcast manager instance.
     *
     * @var \Dominservice\Conversations\Broadcasting\BroadcastManager
     */
    protected $broadcastManager;

    /**
     * Conversations constructor.
     * 
     * @param \Dominservice\Conversations\Broadcasting\BroadcastManager|null $broadcastManager
     */
    public function __construct(BroadcastManager $broadcastManager = null) {
        $this->messagesTable = DB::getTablePrefix() . (new ConversationMessage())->getTable();
        $this->messagesStatusTable = DB::getTablePrefix() . (new ConversationMessageStatus())->getTable();
        $this->broadcastManager = $broadcastManager;
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

        // Execute before_create_conversation hooks
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_create_conversation', [
            'users' => $users,
            'relation_type' => $relationType,
            'relation_id' => $relationId,
            'content' => $content,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return false;
        }

        if (count((array)$users) > 1) {
            $conversation = new Conversation();
            $conversation->owner_uuid = \Auth::user()->{\Auth::user()->getKeyName()};
            $conversation->save();
            $this->setRelations($conversation, $relationType, $relationId);
            $this->setUsers($conversation, $users);

            // Broadcast the conversation created event
            if ($this->broadcastManager && $this->broadcastManager->enabled()) {
                $this->broadcastManager->broadcast(new ConversationCreated($conversation, $users));
            }

            if (!empty($content)) {
                $this->addMessage($conversation->uuid, $content);
            }

            // Execute after_create_conversation hooks
            app('Dominservice\Conversations\Hooks\HookManager')->execute('after_create_conversation', [
                'conversation' => $conversation,
                'users' => $users,
                'relation_type' => $relationType,
                'relation_id' => $relationId,
                'content' => $content,
            ]);

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
     * @param array $attachments
     * @param int|null $parentId
     * @return ConversationMessage|false|int
     */
    public function addMessage($convUuid, $content, $addUser = false, $getObject = false, $attachments = [], $parentId = null)
    {
        // Execute before_add_message hooks
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_add_message', [
            'conversation_uuid' => $convUuid,
            'content' => $content,
            'add_user' => $addUser,
            'attachments' => $attachments,
            'parent_id' => $parentId,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return false;
        }

        if (!empty($convUuid)
            && ((!empty($content) || !empty($attachments)))
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

            // Set parent_id if this is a reply
            if (!is_null($parentId)) {
                $message->parent_id = $parentId;
            }

            // Set message type based on whether attachments are present
            $message->message_type = !empty($attachments) ? ConversationMessage::TYPE_ATTACHMENT : ConversationMessage::TYPE_TEXT;

            $message->save();

            // Process attachments if any
            if (!empty($attachments)) {
                $this->processAttachments($message, $attachments);
            }

            //get all users in conversation
            $usersInConv = $conversation->users ?? [];

            //and add msg status for each user in conversation
            $dataMessageStatuses = [];

            foreach ($usersInConv as $userInConv) {
                if (!empty($userInConv->{\Auth::user()->getKeyName()})) {
                    $dataMessageStatuses[] = [
                        get_user_key() => $userInConv->{\Auth::user()->getKeyName()},
                        'message_id' => $message->id,
                        'self' => $userInConv->id == $userId ? 1 : 0,
                        'status' => $userInConv->id == $userId ? self::READ : self::UNREAD,
                    ];
                }
            }

            \DB::table((new ConversationMessageStatus)->getTable())->insert($dataMessageStatuses);

            // Broadcast the message sent event
            if ($this->broadcastManager && $this->broadcastManager->enabled()) {
                $this->broadcastManager->broadcast(new MessageSent($message));
            }

            // Execute after_add_message hooks
            app('Dominservice\Conversations\Hooks\HookManager')->execute('after_add_message', [
                'message' => $message,
                'conversation' => $conversation,
                'user_id' => $userId,
                'content' => $content,
                'attachments' => $attachments,
                'parent_id' => $parentId,
            ]);

            if ($getObject) {
                return $message;
            }
            return (int)$message->id;
        }

        return false;
    }

    /**
     * Process and store attachments for a message.
     *
     * @param ConversationMessage $message
     * @param array $attachments
     * @return void
     */
    protected function processAttachments(ConversationMessage $message, array $attachments)
    {
        $attachmentService = app('Dominservice\Conversations\Services\AttachmentService');

        foreach ($attachments as $attachment) {
            if ($attachment instanceof \Illuminate\Http\UploadedFile) {
                $attachmentService->storeAttachment($attachment, $message->id);
            }
        }
    }

    /**
     * Add a message with attachments to a conversation.
     *
     * @param string $convUuid
     * @param string $content
     * @param array $attachments
     * @param bool $addUser
     * @param bool $getObject
     * @param int|null $parentId
     * @return ConversationMessage|false|int
     */
    public function addMessageWithAttachments($convUuid, $content, array $attachments, $addUser = false, $getObject = false, $parentId = null)
    {
        return $this->addMessage($convUuid, $content, $addUser, $getObject, $attachments, $parentId);
    }

    /**
     * Reply to a message in a conversation.
     *
     * @param int $parentMessageId The ID of the message being replied to
     * @param string $content The content of the reply
     * @param bool $getObject Whether to return the message object
     * @param array $attachments Optional attachments for the reply
     * @return ConversationMessage|false|int
     */
    public function replyToMessage($parentMessageId, $content, $getObject = false, $attachments = [])
    {
        // Get the parent message
        $parentMessage = ConversationMessage::find($parentMessageId);

        if (!$parentMessage) {
            return false;
        }

        // Get the conversation UUID from the parent message
        $conversationUuid = $parentMessage->conversation_uuid;

        // Add the reply message with the parent_id set
        return $this->addMessage($conversationUuid, $content, false, $getObject, $attachments, $parentMessageId);
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
            $rT = DB::getTablePrefix().(new ConversationRelation)->getTable();
            $parentCol = 'parent';

            if (Str::isUlid($relationId)) {
                $parentCol = 'ulid_parent';
            } elseif(Str::isUuid($relationId)) {
                $parentCol = 'uuid_parent';
            }

            $query->whereRaw(DB::Raw("(SELECT COUNT(`{$parentCol}_id`)
                    FROM `{$rT}`
                    WHERE `{$rT}`.`conversation_uuid`=`{$uT}`.`conversation_uuid`
                     AND `{$rT}`.`{$parentCol}_id`='{$relationId}'
                     AND `{$rT}`.`{$parentCol}_type`='{$relationType}'
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
        // Execute before_delete_conversation hooks
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_delete_conversation', [
            'conversation_uuid' => $convUuid,
            'user_id' => $userId,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return;
        }

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

        $conversationDeleted = false;
        if ($noDeletedCount === 0 && $con = Conversation::uuid($convUuid)) {
            $con->messages()->delete();
            $con->relations()->delete();
            ConversationUser::where('conversation_uuid', $convUuid)->delete();
            $con->delete();
            $conversationDeleted = true;
        }

        // Execute after_delete_conversation hooks
        app('Dominservice\Conversations\Hooks\HookManager')->execute('after_delete_conversation', [
            'conversation_uuid' => $convUuid,
            'user_id' => $userId,
            'conversation_deleted' => $conversationDeleted,
        ]);
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
        // Execute before_mark_as_read hooks
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_mark_as_read', [
            'conversation_uuid' => $convUuid,
            'message_id' => $msgId,
            'user_id' => $userId,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return;
        }

        $this->markAs($convUuid, $msgId, $userId, self::READ);

        // Broadcast the message read event
        if ($this->broadcastManager && $this->broadcastManager->enabled()) {
            $this->broadcastManager->broadcast(new MessageRead($convUuid, $msgId, $userId));
        }

        // Execute after_mark_as_read hooks
        app('Dominservice\Conversations\Hooks\HookManager')->execute('after_mark_as_read', [
            'conversation_uuid' => $convUuid,
            'message_id' => $msgId,
            'user_id' => $userId,
        ]);
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
        // Execute before_mark_as_deleted hooks
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_mark_as_deleted', [
            'conversation_uuid' => $convUuid,
            'message_id' => $msgId,
            'user_id' => $userId,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return;
        }

        $this->markAs($convUuid, $msgId, $userId, self::DELETED);

        // Broadcast the message deleted event
        if ($this->broadcastManager && $this->broadcastManager->enabled()) {
            $this->broadcastManager->broadcast(new MessageDeleted($convUuid, $msgId, $userId));
        }

        // Execute after_mark_as_deleted hooks
        app('Dominservice\Conversations\Hooks\HookManager')->execute('after_mark_as_deleted', [
            'conversation_uuid' => $convUuid,
            'message_id' => $msgId,
            'user_id' => $userId,
        ]);
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
        $relations = /*$conversation->relations ??*/ collect();

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

                if (Str::isUlid($relationId)) {
                    $relation->ulid_parent_type = $relationType;
                    $relation->ulid_parent_id = $relationId;
                } elseif(Str::isUuid($relationId)) {
                    $relation->uuid_parent_type = $relationType;
                    $relation->uuid_parent_id = $relationId;
                } else {
                    $relation->parent_type = $relationType;
                    $relation->parent_id = $relationId;
                }

                $relation->save();
                $relations[] = $relation;
            }
        }
//        $conversation->relations->push($relations);
    }

    /**
     * @param $conversation
     * @param $userId
     */
    private function setUsers(&$conversation, $userId)
    {
        $users = /*$conversation->users ?? */collect();

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
//            $users->push($conversationUser);
        }
//        $conversation->users->push($users);
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

    /**
     * Broadcast that a user is typing in a conversation.
     *
     * @param  string  $conversationUuid
     * @param  mixed  $userId
     * @param  string|null  $userName
     * @return void
     */
    public function broadcastUserTyping(string $conversationUuid, $userId = null, ?string $userName = null): void
    {
        if ($this->broadcastManager && $this->broadcastManager->enabled()) {
            if ($userId === null && \Auth::check()) {
                $user = \Auth::user();
                $userId = $user->{$user->getKeyName()};
            } elseif ($userId === null) {
                $userId = 'unknown';
            }
            $this->broadcastManager->broadcast(new UserTyping($conversationUuid, $userId, $userName));
        }
    }

    /**
     * Edit a message in a conversation.
     *
     * @param int $messageId The ID of the message to edit
     * @param string $newContent The new content for the message
     * @param mixed|null $userId The ID of the user editing the message (defaults to authenticated user)
     * @return bool|ConversationMessage Returns the updated message on success, false on failure
     */
    public function editMessage(int $messageId, string $newContent, $userId = null)
    {
        // Get the user ID if not provided
        $userId = $userId ?? \Auth::user()->{\Auth::user()->getKeyName()};

        // Get the message
        $message = ConversationMessage::find($messageId);

        // Check if the message exists
        if (!$message) {
            return false;
        }

        // Check if the message is editable
        if (!$message->isEditable($userId)) {
            return false;
        }

        // Execute before_edit_message hooks
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_edit_message', [
            'message_id' => $messageId,
            'old_content' => $message->content,
            'new_content' => $newContent,
            'user_id' => $userId,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return false;
        }

        // Update the message
        $message->content = $newContent;
        $message->edited_at = now();
        $message->save();

        // Broadcast the message edited event if enabled
        if ($this->broadcastManager && $this->broadcastManager->enabled() && 
            config('conversations.message_editing.broadcast_edits', true)) {
            $this->broadcastManager->broadcast(new Events\MessageEdited($message, $userId));
        }

        // Execute after_edit_message hooks
        app('Dominservice\Conversations\Hooks\HookManager')->execute('after_edit_message', [
            'message' => $message,
            'user_id' => $userId,
            'old_content' => $hookResult['old_content'] ?? null,
        ]);

        return $message;
    }

    /**
     * Check if a message is editable by a user.
     *
     * @param int $messageId The ID of the message to check
     * @param mixed|null $userId The ID of the user (defaults to authenticated user)
     * @return bool True if the message is editable, false otherwise
     */
    public function isMessageEditable(int $messageId, $userId = null): bool
    {
        // Get the user ID if not provided
        $userId = $userId ?? \Auth::user()->{\Auth::user()->getKeyName()};

        // Get the message
        $message = ConversationMessage::find($messageId);

        // Check if the message exists and is editable
        return $message && $message->isEditable($userId);
    }

    /**
     * Set whether a message is editable.
     *
     * @param int $messageId The ID of the message
     * @param bool $editable Whether the message should be editable
     * @return bool True on success, false on failure
     */
    public function setMessageEditable(int $messageId, bool $editable): bool
    {
        // Get the message
        $message = ConversationMessage::find($messageId);

        // Check if the message exists
        if (!$message) {
            return false;
        }

        // Update the editable flag
        $message->editable = $editable;
        return $message->save();
    }

    /**
     * Get all users who have read a specific message.
     *
     * @param  int  $messageId
     * @return \Illuminate\Support\Collection
     */
    public function getMessageReadBy(int $messageId)
    {
        $userModel = config('conversations.user_model');
        $userKey = get_user_key();

        $readStatuses = ConversationMessageStatus::where('message_id', $messageId)
            ->where('status', self::READ)
            ->where('self', 0) // Exclude the sender
            ->with(['user' => function($query) {
                $query->select('id', 'name', 'email'); // Add any other user fields you want to include
            }])
            ->get();

        return $readStatuses->map(function($status) {
            return $status->user;
        })->filter(); // Remove any null values
    }

    /**
     * Get all messages in a conversation with their read status for all users.
     *
     * @param  string  $conversationUuid
     * @return \Illuminate\Support\Collection
     */
    public function getConversationReadBy(string $conversationUuid)
    {
        $messages = ConversationMessage::where('conversation_uuid', $conversationUuid)
            ->with(['status' => function($query) {
                $query->where('status', self::READ)
                    ->where('self', 0) // Exclude the sender
                    ->with(['user' => function($query) {
                        $query->select('id', 'name', 'email'); // Add any other user fields you want to include
                    }]);
            }])
            ->get();

        return $messages->map(function($message) {
            $readBy = $message->status->map(function($status) {
                return $status->user;
            })->filter(); // Remove any null values

            return [
                'id' => $message->id,
                'content' => $message->content,
                'sender_id' => $message->{get_sender_key()},
                'created_at' => $message->created_at,
                'read_by' => $readBy,
                'read_count' => $readBy->count(),
            ];
        });
    }

    /**
     * Add a reaction to a message.
     *
     * @param int $messageId
     * @param string $reaction
     * @param mixed|null $userId
     * @return \Dominservice\Conversations\Models\Eloquent\ConversationMessageReaction|false
     */
    public function addReaction(int $messageId, string $reaction, $userId = null)
    {
        $userId = $userId ?? \Auth::user()->{\Auth::user()->getKeyName()};
        $message = ConversationMessage::find($messageId);

        if (!$message) {
            return false;
        }

        // Check if user is part of the conversation
        if (!$this->existsUser($message->conversation_uuid, $userId)) {
            return false;
        }

        // Execute before_add_reaction hooks
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_add_reaction', [
            'message_id' => $messageId,
            'reaction' => $reaction,
            'user_id' => $userId,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return false;
        }

        // Check if the user has already reacted with this emoji
        $existingReaction = Models\Eloquent\ConversationMessageReaction::where('message_id', $messageId)
            ->where(get_user_key(), $userId)
            ->where('reaction', $reaction)
            ->first();

        if ($existingReaction) {
            return $existingReaction; // User already reacted with this emoji
        }

        // Create the reaction
        $reactionModel = new Models\Eloquent\ConversationMessageReaction();
        $reactionModel->message_id = $messageId;
        $reactionModel->{get_user_key()} = $userId;
        $reactionModel->reaction = $reaction;
        $reactionModel->save();

        // Broadcast the reaction added event if broadcasting is enabled
        if ($this->broadcastManager && $this->broadcastManager->enabled()) {
            // We'll create this event class later
            $this->broadcastManager->broadcast(new Events\MessageReactionAdded(
                $message->conversation_uuid,
                $messageId,
                $userId,
                $reaction
            ));
        }

        // Execute after_add_reaction hooks
        app('Dominservice\Conversations\Hooks\HookManager')->execute('after_add_reaction', [
            'reaction' => $reactionModel,
            'message' => $message,
            'user_id' => $userId,
        ]);

        return $reactionModel;
    }

    /**
     * Remove a reaction from a message.
     *
     * @param int $messageId
     * @param string $reaction
     * @param mixed|null $userId
     * @return bool
     */
    public function removeReaction(int $messageId, string $reaction, $userId = null)
    {
        $userId = $userId ?? \Auth::user()->{\Auth::user()->getKeyName()};
        $message = ConversationMessage::find($messageId);

        if (!$message) {
            return false;
        }

        // Check if user is part of the conversation
        if (!$this->existsUser($message->conversation_uuid, $userId)) {
            return false;
        }

        // Execute before_remove_reaction hooks
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_remove_reaction', [
            'message_id' => $messageId,
            'reaction' => $reaction,
            'user_id' => $userId,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return false;
        }

        // Find the reaction
        $reactionModel = Models\Eloquent\ConversationMessageReaction::where('message_id', $messageId)
            ->where(get_user_key(), $userId)
            ->where('reaction', $reaction)
            ->first();

        if (!$reactionModel) {
            return false; // Reaction doesn't exist
        }

        // Delete the reaction
        $reactionModel->delete();

        // Broadcast the reaction removed event if broadcasting is enabled
        if ($this->broadcastManager && $this->broadcastManager->enabled()) {
            // We'll create this event class later
            $this->broadcastManager->broadcast(new Events\MessageReactionRemoved(
                $message->conversation_uuid,
                $messageId,
                $userId,
                $reaction
            ));
        }

        // Execute after_remove_reaction hooks
        app('Dominservice\Conversations\Hooks\HookManager')->execute('after_remove_reaction', [
            'message_id' => $messageId,
            'reaction' => $reaction,
            'user_id' => $userId,
            'message' => $message,
        ]);

        return true;
    }

    /**
     * Get all reactions for a message.
     *
     * @param int $messageId
     * @return \Illuminate\Support\Collection
     */
    public function getMessageReactions(int $messageId)
    {
        $message = ConversationMessage::with('reactions.user')->find($messageId);

        if (!$message) {
            return collect();
        }

        return $message->reactions;
    }

    /**
     * Get a summary of reactions for a message, grouped by emoji with count.
     *
     * @param int $messageId
     * @return \Illuminate\Support\Collection
     */
    public function getMessageReactionsSummary(int $messageId)
    {
        $message = ConversationMessage::find($messageId);

        if (!$message) {
            return collect();
        }

        return $message->getReactionsSummary();
    }
}
