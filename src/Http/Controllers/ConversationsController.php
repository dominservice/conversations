<?php

namespace Dominservice\Conversations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Dominservice\Conversations\Facade\ConversationsHooks;

class ConversationsController extends Controller
{
    /**
     * Display a listing of the conversations.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $relationType = $request->input('relation_type');
        $relationId = $request->input('relation_id');

        $conversations = app('conversations')->getConversations($userId, $relationType, $relationId);

        // Execute hook after retrieving conversations
        ConversationsHooks::execute('after_get_conversations', [
            'conversations' => $conversations,
            'user_id' => $userId,
            'relation_type' => $relationType,
            'relation_id' => $relationId,
        ]);

        return response()->json([
            'data' => $conversations,
        ]);
    }

    /**
     * Store a newly created conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'users' => 'required|array',
            'users.*' => 'required',
            'content' => 'nullable|string',
            'relation_type' => 'nullable|string',
            'relation_id' => 'nullable',
        ]);

        $users = $request->input('users');
        $content = $request->input('content');
        $relationType = $request->input('relation_type');
        $relationId = $request->input('relation_id');

        $conversation = app('conversations')->create($users, $relationType, $relationId, $content, true);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.create_failed'),
            ], 422);
        }

        return response()->json([
            'data' => $conversation,
            'message' => trans('conversations::conversations.conversation.created'),
        ], 201);
    }

    /**
     * Display the specified conversation.
     *
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
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

        // Load relations and users
        $conversation->load(['users', 'relations']);
        app('conversations')->getRelations($conversation);

        // Execute hook after retrieving conversation
        ConversationsHooks::execute('after_get_conversation', [
            'conversation' => $conversation,
            'user_id' => $userId,
        ]);

        return response()->json([
            'data' => $conversation,
        ]);
    }

    /**
     * Remove the specified conversation.
     *
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
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

        app('conversations')->delete($uuid, $userId);

        return response()->json([
            'message' => trans('conversations::conversations.conversation.deleted'),
        ]);
    }
}
