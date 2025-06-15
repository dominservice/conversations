<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Dominservice\Conversations\Facade\Conversations;

class ChatController extends Controller
{
    /**
     * Display a list of all conversations for the current user.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $userId = Auth::id();
        $conversations = Conversations::getConversations($userId);
        
        return view('chat.index', compact('conversations'));
    }
    
    /**
     * Display a specific conversation with its messages.
     *
     * @param  string  $conversationId
     * @return \Illuminate\View\View
     */
    public function show($conversationId)
    {
        $userId = Auth::id();
        $conversation = Conversations::get($conversationId);
        
        if (!$conversation || !Conversations::existsUser($conversationId, $userId)) {
            abort(404, 'Conversation not found');
        }
        
        $messages = Conversations::getMessages($conversationId, $userId);
        
        // Mark all unread messages as read
        Conversations::markReadAll($conversationId, $userId);
        
        return view('chat.show', compact('conversation', 'messages'));
    }
    
    /**
     * Create a new conversation with selected users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'users' => 'required|array',
            'message' => 'required|string',
        ]);
        
        $users = $request->input('users');
        $message = $request->input('message');
        
        // Create conversation and add initial message
        $conversationId = Conversations::create($users, null, null, $message);
        
        if (!$conversationId) {
            return back()->with('error', 'Failed to create conversation');
        }
        
        return redirect()->route('chat.show', $conversationId)
            ->with('success', 'Conversation created successfully');
    }
    
    /**
     * Add a new message to an existing conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $conversationId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addMessage(Request $request, $conversationId)
    {
        $request->validate([
            'message' => 'required|string',
        ]);
        
        $userId = Auth::id();
        
        if (!Conversations::existsUser($conversationId, $userId)) {
            abort(403, 'You are not part of this conversation');
        }
        
        $message = $request->input('message');
        $messageId = Conversations::addMessage($conversationId, $message);
        
        if (!$messageId) {
            return back()->with('error', 'Failed to send message');
        }
        
        return back()->with('success', 'Message sent successfully');
    }
    
    /**
     * Delete a conversation for the current user.
     *
     * @param  string  $conversationId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($conversationId)
    {
        $userId = Auth::id();
        
        if (!Conversations::existsUser($conversationId, $userId)) {
            abort(403, 'You are not part of this conversation');
        }
        
        Conversations::delete($conversationId, $userId);
        
        return redirect()->route('chat.index')
            ->with('success', 'Conversation deleted successfully');
    }
}