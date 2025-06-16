<?php

/**
 * This example demonstrates how to integrate the Laravel Conversations package with Laravel Breeze.
 * It includes the necessary controller methods and route definitions.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Dominservice\Conversations\Facade\Conversations;
use Dominservice\Conversations\Facade\ConversationsBroadcasting;
use App\Models\User;

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
        $users = User::where('id', '!=', $userId)->get();
        
        return view('chat.index', compact('conversations', 'users'));
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
        
        // Add current user to the conversation
        $users[] = Auth::id();
        
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

/**
 * Example route definitions for Laravel Breeze integration.
 * Add these routes to your routes/web.php file.
 */

// Route::middleware(['auth'])->group(function () {
//     Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
//     Route::get('/chat/{conversationId}', [ChatController::class, 'show'])->name('chat.show');
//     Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
//     Route::post('/chat/{conversationId}/messages', [ChatController::class, 'addMessage'])->name('chat.messages.store');
//     Route::delete('/chat/{conversationId}', [ChatController::class, 'destroy'])->name('chat.destroy');
//     Route::post('/chat/{conversationId}/typing', [ChatController::class, 'typing'])->name('chat.typing');
// });

/**
 * Example JavaScript for handling real-time updates with Laravel Echo.
 * Add this to your JavaScript file (e.g., resources/js/chat.js).
 */

/*
document.addEventListener('DOMContentLoaded', function() {
    const conversationId = document.getElementById('conversation-id')?.value;
    const userId = document.getElementById('user-id')?.value;
    
    if (conversationId && userId && window.Echo) {
        // Listen for new messages
        window.Echo.private(`conversation.${conversationId}`)
            .listen('.message.sent', (e) => {
                console.log('New message received:', e);
                
                // Add the message to the UI if it's not from the current user
                if (e.sender_id !== userId) {
                    addMessageToChat(e);
                    
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
        
        // Set up typing indicator
        const messageInput = document.getElementById('message-input');
        let typingTimeout;
        
        if (messageInput) {
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
        }
    }
    
    // Helper functions for UI updates
    function addMessageToChat(message) {
        const messagesContainer = document.getElementById('messages-container');
        if (!messagesContainer) return;
        
        const messageElement = document.createElement('div');
        messageElement.className = 'flex justify-start';
        messageElement.innerHTML = `
            <div class="bg-gray-200 text-gray-800 rounded-lg px-4 py-2 max-w-[70%]">
                <div class="font-semibold text-sm">${message.sender.name}</div>
                <div>${message.content}</div>
                <div class="text-xs text-gray-500 text-right">
                    ${new Date(message.created_at).toLocaleString()}
                </div>
            </div>
        `;
        
        messagesContainer.appendChild(messageElement);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    function markMessageAsRead(messageId) {
        fetch(`/api/conversations/${conversationId}/messages/${messageId}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
    }
    
    function updateReadStatus(messageId, userId) {
        // Update the UI to show that a user has read a message
        const readIndicator = document.querySelector(`[data-message-id="${messageId}"] .read-indicator`);
        if (readIndicator) {
            readIndicator.classList.remove('hidden');
        }
    }
    
    function showTypingIndicator(userName) {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.textContent = `${userName} is typing...`;
            typingIndicator.classList.remove('hidden');
        }
    }
    
    function hideTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.classList.add('hidden');
        }
    }
});
*/