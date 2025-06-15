<?php

namespace Dominservice\Conversations\Http\Controllers;

use Dominservice\Conversations\Models\Eloquent\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Dominservice\Conversations\Facade\ConversationsHooks;

class MessagesController extends Controller
{
    /**
     * Display a listing of the messages in a conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $uuid)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $newToOld = $request->input('order', 'asc') === 'asc';
        $limit = $request->input('limit');
        $start = $request->input('start');

        $messages = app('conversations')->getMessages($uuid, $userId, $newToOld, $limit, $start);

        // Execute hook after retrieving messages
        ConversationsHooks::execute('after_get_messages', [
            'messages' => $messages,
            'conversation_uuid' => $uuid,
            'user_id' => $userId,
        ]);

        return response()->json([
            'data' => $messages,
        ]);
    }

    /**
     * Store a newly created message in the conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $uuid)
    {
        $request->validate([
            'content' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:' . config('conversations.attachments.max_size.default', 10240),
            'parent_id' => 'nullable|integer|exists:' . config('conversations.tables.conversation_messages') . ',id',
        ]);

        // Either content or attachments must be present
        if (empty($request->input('content')) && !$request->hasFile('attachments')) {
            return response()->json([
                'message' => trans('conversations::conversations.message.content_or_attachment_required'),
            ], 422);
        }

        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $content = $request->input('content', '');
        $attachments = $request->file('attachments', []);
        $parentId = $request->input('parent_id');

        // Use addMessageWithAttachments if attachments are present
        if (!empty($attachments)) {
            $message = app('conversations')->addMessageWithAttachments($uuid, $content, $attachments, false, true, $parentId);
        } else {
            $message = app('conversations')->addMessage($uuid, $content, false, true, [], $parentId);
        }

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.create_failed'),
            ], 422);
        }

        // Load attachments if present
        if ($message->hasAttachments()) {
            $message->load('attachments');
        }

        return response()->json([
            'data' => $message,
            'message' => trans('conversations::conversations.message.sent'),
        ], 201);
    }

    /**
     * Get attachments for a message.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function attachments($uuid, $messageId)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $message = ConversationMessage::with('attachments')
            ->where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        return response()->json([
            'data' => $message->attachments,
        ]);
    }

    /**
     * Display a listing of unread messages in a conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function unread(Request $request, $uuid)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $newToOld = $request->input('order', 'asc') === 'asc';
        $limit = $request->input('limit');
        $start = $request->input('start');

        $messages = app('conversations')->getUnreadMessages($uuid, $userId, $newToOld, $limit, $start);

        return response()->json([
            'data' => $messages,
        ]);
    }

    /**
     * Mark a message as read.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($uuid, $messageId)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        app('conversations')->markAsRead($uuid, $messageId, $userId);

        return response()->json([
            'message' => trans('conversations::conversations.message.marked_read'),
        ]);
    }

    /**
     * Mark a message as unread.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsUnread($uuid, $messageId)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        app('conversations')->markAsUnread($uuid, $messageId, $userId);

        return response()->json([
            'message' => trans('conversations::conversations.message.marked_unread'),
        ]);
    }

    /**
     * Remove the specified message.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid, $messageId)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        app('conversations')->markAsDeleted($uuid, $messageId, $userId);

        return response()->json([
            'message' => trans('conversations::conversations.message.deleted'),
        ]);
    }

    /**
     * Edit a message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $uuid, $messageId)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Check if message exists and belongs to this conversation
        $message = \Dominservice\Conversations\Models\Eloquent\ConversationMessage::where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        // Check if message is editable
        if (!app('conversations')->isMessageEditable($messageId, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_editable'),
            ], 403);
        }

        // Edit the message
        $content = $request->input('content');
        $updatedMessage = app('conversations')->editMessage($messageId, $content, $userId);

        if (!$updatedMessage) {
            return response()->json([
                'message' => trans('conversations::conversations.message.edit_failed'),
            ], 422);
        }

        return response()->json([
            'data' => $updatedMessage,
            'message' => trans('conversations::conversations.message.edited'),
        ]);
    }

    /**
     * Check if a message is editable.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEditable($uuid, $messageId)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Check if message exists and belongs to this conversation
        $message = \Dominservice\Conversations\Models\Eloquent\ConversationMessage::where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        $isEditable = app('conversations')->isMessageEditable($messageId, $userId);
        $timeLimit = config('conversations.message_editing.time_limit');
        $editableUntil = $timeLimit ? $message->created_at->addMinutes($timeLimit) : null;

        return response()->json([
            'data' => [
                'is_editable' => $isEditable,
                'editable_flag' => $message->editable,
                'time_limit' => $timeLimit,
                'editable_until' => $editableUntil ? $editableUntil->toIso8601String() : null,
                'has_been_edited' => $message->hasBeenEdited(),
                'edited_at' => $message->edited_at ? $message->edited_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Broadcast that the user is typing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function typing(Request $request, $uuid)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $userName = $request->input('user_name');

        app('conversations')->broadcastUserTyping($uuid, $userId, $userName);

        return response()->json([
            'message' => trans('conversations::conversations.message.typing_sent'),
        ]);
    }

    /**
     * Get all users who have read a specific message.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function readBy($uuid, $messageId)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Check if the message belongs to the conversation
        $message = \Dominservice\Conversations\Models\Eloquent\ConversationMessage::where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        $readBy = app('conversations')->getMessageReadBy($messageId);

        return response()->json([
            'data' => [
                'message_id' => $messageId,
                'read_by' => $readBy,
                'read_count' => $readBy->count(),
            ],
        ]);
    }

    /**
     * Get all messages in a conversation with their read status for all users.
     *
     * @param  string  $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function conversationReadBy($uuid)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $messages = app('conversations')->getConversationReadBy($uuid);

        return response()->json([
            'data' => $messages,
        ]);
    }

    /**
     * Get all messages in a thread.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function thread($uuid, $messageId)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Check if the message exists and belongs to this conversation
        $message = \Dominservice\Conversations\Models\Eloquent\ConversationMessage::where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        // Get all messages in the thread
        $threadMessages = $message->getThreadMessages();

        // Load additional relationships for each message
        $threadMessages->each(function ($message) {
            $message->load(['sender', 'attachments']);
        });

        return response()->json([
            'data' => [
                'thread_root_id' => $message->getThreadRoot()->id,
                'messages' => $threadMessages,
            ],
        ]);
    }

    /**
     * Reply to a specific message in a conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reply(Request $request, $uuid, $messageId)
    {
        $request->validate([
            'content' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:' . config('conversations.attachments.max_size.default', 10240),
        ]);

        // Either content or attachments must be present
        if (empty($request->input('content')) && !$request->hasFile('attachments')) {
            return response()->json([
                'message' => trans('conversations::conversations.message.content_or_attachment_required'),
            ], 422);
        }

        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Check if the message exists and belongs to this conversation
        $parentMessage = \Dominservice\Conversations\Models\Eloquent\ConversationMessage::where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$parentMessage) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        $content = $request->input('content', '');
        $attachments = $request->file('attachments', []);

        // Use replyToMessage method
        if (!empty($attachments)) {
            // For attachments, we need to use addMessageWithAttachments with parent_id
            $message = app('conversations')->addMessageWithAttachments($uuid, $content, $attachments, false, true, $messageId);
        } else {
            $message = app('conversations')->replyToMessage($messageId, $content, true);
        }

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.create_failed'),
            ], 422);
        }

        // Load attachments if present
        if ($message->hasAttachments()) {
            $message->load('attachments');
        }

        return response()->json([
            'data' => $message,
            'message' => trans('conversations::conversations.message.reply_sent'),
        ], 201);
    }

    /**
     * Get all reactions for a message.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reactions($uuid, $messageId)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Check if the message belongs to the conversation
        $message = \Dominservice\Conversations\Models\Eloquent\ConversationMessage::where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        $reactions = app('conversations')->getMessageReactions($messageId);
        $reactionsSummary = app('conversations')->getMessageReactionsSummary($messageId);

        return response()->json([
            'data' => [
                'message_id' => $messageId,
                'reactions' => $reactions,
                'summary' => $reactionsSummary,
            ],
        ]);
    }

    /**
     * Add a reaction to a message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addReaction(Request $request, $uuid, $messageId)
    {
        $request->validate([
            'reaction' => 'required|string|max:50',
        ]);

        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Check if the message belongs to the conversation
        $message = \Dominservice\Conversations\Models\Eloquent\ConversationMessage::where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        $reaction = $request->input('reaction');
        $reactionModel = app('conversations')->addReaction($messageId, $reaction, $userId);

        if (!$reactionModel) {
            return response()->json([
                'message' => trans('conversations::conversations.reaction.create_failed'),
            ], 422);
        }

        return response()->json([
            'data' => $reactionModel,
            'message' => trans('conversations::conversations.reaction.added'),
        ], 201);
    }

    /**
     * Remove a reaction from a message.
     *
     * @param  string  $uuid
     * @param  int  $messageId
     * @param  string  $reaction
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeReaction($uuid, $messageId, $reaction)
    {
        $userId = Auth::id();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Check if the message belongs to the conversation
        $message = \Dominservice\Conversations\Models\Eloquent\ConversationMessage::where('id', $messageId)
            ->where('conversation_uuid', $uuid)
            ->first();

        if (!$message) {
            return response()->json([
                'message' => trans('conversations::conversations.message.not_found'),
            ], 404);
        }

        $success = app('conversations')->removeReaction($messageId, $reaction, $userId);

        if (!$success) {
            return response()->json([
                'message' => trans('conversations::conversations.reaction.remove_failed'),
            ], 422);
        }

        return response()->json([
            'message' => trans('conversations::conversations.reaction.removed'),
        ]);
    }
}
