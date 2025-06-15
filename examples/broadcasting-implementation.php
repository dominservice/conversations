<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Dominservice\Conversations\Facade\Conversations;
use Dominservice\Conversations\Facade\ConversationsBroadcasting;

class RealTimeChatController extends Controller
{
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
        
        return view('chat.realtime', compact('conversation', 'messages'));
    }
    
    /**
     * Add a new message to an existing conversation with real-time broadcasting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMessage(Request $request, $conversationId)
    {
        $request->validate([
            'message' => 'required|string',
        ]);
        
        $userId = Auth::id();
        
        if (!Conversations::existsUser($conversationId, $userId)) {
            return response()->json(['error' => 'You are not part of this conversation'], 403);
        }
        
        $message = $request->input('message');
        $messageObj = Conversations::addMessage($conversationId, $message, false, true);
        
        if (!$messageObj) {
            return response()->json(['error' => 'Failed to send message'], 500);
        }
        
        // The message will be automatically broadcast by the package
        // through the MessageSent event
        
        return response()->json([
            'success' => true,
            'message' => $messageObj
        ]);
    }
    
    /**
     * Mark a message as read with real-time broadcasting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $conversationId
     * @param  int  $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $conversationId, $messageId)
    {
        $userId = Auth::id();
        
        if (!Conversations::existsUser($conversationId, $userId)) {
            return response()->json(['error' => 'You are not part of this conversation'], 403);
        }
        
        Conversations::markAsRead($conversationId, $messageId, $userId);
        
        // The read status will be automatically broadcast by the package
        // through the MessageRead event
        
        return response()->json(['success' => true]);
    }
    
    /**
     * Send a typing indicator with real-time broadcasting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function typing(Request $request, $conversationId)
    {
        $userId = Auth::id();
        $userName = Auth::user()->name;
        
        if (!Conversations::existsUser($conversationId, $userId)) {
            return response()->json(['error' => 'You are not part of this conversation'], 403);
        }
        
        // Broadcast that the user is typing
        ConversationsBroadcasting::broadcastUserTyping($conversationId, $userId, $userName);
        
        return response()->json(['success' => true]);
    }
}

// In your JavaScript file (e.g., resources/js/chat.js)
/*
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true
});

const conversationId = document.getElementById('conversation-id').value;
const userId = document.getElementById('user-id').value;

// Listen for new messages
window.Echo.private(`conversation.${conversationId}`)
    .listen('.message.sent', (e) => {
        console.log('New message received:', e);
        
        // Add the message to the UI
        if (e.sender_id !== userId) {
            addMessageToChat(e.content, e.sender_id, e.created_at, false);
            
            // Mark the message as read
            markMessageAsRead(e.id);
        }
    })
    .listen('.message.read', (e) => {
        console.log('Message read:', e);
        
        // Update read status in UI
        if (e.user_id !== userId) {
            updateReadStatus(e.message_id, e.user_id);
        }
    })
    .listen('.user.typing', (e) => {
        console.log('User typing:', e);
        
        // Show typing indicator
        if (e.user_id !== userId) {
            showTypingIndicator(e.user_name);
            
            // Hide typing indicator after 3 seconds
            setTimeout(() => {
                hideTypingIndicator();
            }, 3000);
        }
    });

// Listen for new conversations
window.Echo.private(`conversation.user.${userId}`)
    .listen('.conversation.created', (e) => {
        console.log('New conversation:', e);
        
        // Add notification or update conversation list
        addNewConversationNotification(e.conversation_uuid, e.participant_ids);
    });

// Send typing indicator when user is typing
const messageInput = document.getElementById('message-input');
let typingTimeout;

messageInput.addEventListener('input', () => {
    clearTimeout(typingTimeout);
    
    // Send typing event
    fetch(`/chat/${conversationId}/typing`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });
    
    // Set a timeout to avoid sending too many events
    typingTimeout = setTimeout(() => {
        // Do nothing, just clear the timeout
    }, 3000);
});

// Function to send a message
function sendMessage(content) {
    fetch(`/chat/${conversationId}/messages`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ message: content })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear input field
            messageInput.value = '';
            
            // Add message to UI (optional, as it will also come through the broadcast)
            addMessageToChat(content, userId, new Date().toISOString(), true);
        }
    });
}

// Function to mark a message as read
function markMessageAsRead(messageId) {
    fetch(`/chat/${conversationId}/messages/${messageId}/read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });
}

// UI Helper functions (implement these according to your UI)
function addMessageToChat(content, senderId, timestamp, isMine) {
    // Add message to the chat UI
}

function updateReadStatus(messageId, userId) {
    // Update read status in the UI
}

function showTypingIndicator(userName) {
    // Show typing indicator in the UI
}

function hideTypingIndicator() {
    // Hide typing indicator in the UI
}

function addNewConversationNotification(conversationId, participantIds) {
    // Add notification for new conversation
}
*/