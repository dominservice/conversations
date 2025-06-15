<?php

namespace Dominservice\Conversations\Http\Controllers;

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
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $uuid)
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

        $content = $request->input('content', '');
        $attachments = $request->file('attachments', []);

        // Use addMessageWithAttachments if attachments are present
        if (!empty($attachments)) {
            $message = app('conversations')->addMessageWithAttachments($uuid, $content, $attachments, false, true);
        } else {
            $message = app('conversations')->addMessage($uuid, $content, false, true);
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
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
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
     * Broadcast that the user is typing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
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
}
