# Conversations Examples

This document provides examples of how to use the Conversations package in your Laravel application.

## Table of Contents

- [Basic Implementation](#basic-implementation)
- [Broadcasting Implementation](#broadcasting-implementation)
- [Hooks Implementation](#hooks-implementation)
- [API Usage](#api-usage)

## Basic Implementation

The following example shows how to implement a basic chat system using the Conversations package.

```php
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
```

## Broadcasting Implementation

The following example shows how to implement real-time chat using the broadcasting features of the Conversations package.

```php
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
```

### JavaScript for Real-Time Chat

```javascript
// In your JavaScript file (e.g., resources/js/chat.js)
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
```

## Hooks Implementation

The following example shows how to use the hook system to extend the functionality of the Conversations package.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Dominservice\Conversations\Facade\ConversationsHooks;
use App\Notifications\NewMessageNotification;
use App\Notifications\ConversationCreatedNotification;
use App\Services\ContentFilterService;
use App\Services\ExternalChatService;
use Illuminate\Support\Facades\Log;

class ConversationsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register hooks for the conversations package
        $this->registerConversationHooks();
    }

    /**
     * Register hooks for the conversations package.
     *
     * @return void
     */
    protected function registerConversationHooks()
    {
        // Hook: Before adding a message - Content filtering
        ConversationsHooks::register('before_add_message', function ($data) {
            $content = $data['content'];
            
            // Use a content filter service to check for prohibited content
            $contentFilter = app(ContentFilterService::class);
            if ($contentFilter->containsProhibitedContent($content)) {
                Log::warning('Message blocked due to prohibited content', [
                    'content' => $content,
                    'conversation_uuid' => $data['conversation_uuid'],
                ]);
                
                return false; // Abort the operation
            }
        });

        // Hook: After adding a message - Send notifications
        ConversationsHooks::register('after_add_message', function ($data) {
            $message = $data['message'];
            $conversation = $data['conversation'];
            $senderId = $data['user_id'];
            
            // Notify all users in the conversation except the sender
            foreach ($conversation->users as $user) {
                if ($user->id != $senderId) {
                    $user->notify(new NewMessageNotification($message, $conversation));
                }
            }
            
            // Log the message for analytics
            Log::info('New message added', [
                'message_id' => $message->id,
                'conversation_uuid' => $conversation->uuid,
                'sender_id' => $senderId,
            ]);
        });

        // Hook: After creating a conversation - Send notifications
        ConversationsHooks::register('after_create_conversation', function ($data) {
            $conversation = $data['conversation'];
            $users = $data['users'];
            
            // Notify all users about the new conversation
            foreach ($conversation->users as $user) {
                if ($user->id != $conversation->owner_uuid) {
                    $user->notify(new ConversationCreatedNotification($conversation));
                }
            }
            
            // Log the conversation creation
            Log::info('New conversation created', [
                'conversation_uuid' => $conversation->uuid,
                'owner_id' => $conversation->owner_uuid,
                'users' => $users,
            ]);
        });
    }
}
```

## API Usage

The following examples show how to use the Conversations API from client applications.

### JavaScript/Fetch API

```javascript
// Configuration
const API_BASE_URL = 'https://your-laravel-app.com/api/conversations';
const API_TOKEN = 'your-api-token'; // Get this from your authentication system

// Helper function for API requests
async function apiRequest(endpoint, method = 'GET', data = null) {
    const url = `${API_BASE_URL}${endpoint}`;
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${API_TOKEN}`
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    const response = await fetch(url, options);
    
    if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'API request failed');
    }
    
    return response.json();
}

// Get all conversations
async function getConversations() {
    try {
        const result = await apiRequest('');
        console.log('Conversations:', result.data);
        return result.data;
    } catch (error) {
        console.error('Error fetching conversations:', error);
    }
}

// Create a new conversation
async function createConversation(userIds, initialMessage) {
    try {
        const data = {
            users: userIds,
            content: initialMessage
        };
        
        const result = await apiRequest('', 'POST', data);
        console.log('Conversation created:', result.data);
        return result.data;
    } catch (error) {
        console.error('Error creating conversation:', error);
    }
}

// Send a message to a conversation
async function sendMessage(conversationId, content) {
    try {
        const data = { message: content };
        const result = await apiRequest(`/${conversationId}/messages`, 'POST', data);
        console.log('Message sent:', result.data);
        return result.data;
    } catch (error) {
        console.error(`Error sending message to conversation ${conversationId}:`, error);
    }
}
```

### PHP/Guzzle

```php
<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ConversationsApiClient
{
    private $client;
    private $baseUrl;
    private $apiToken;
    
    public function __construct($baseUrl, $apiToken)
    {
        $this->baseUrl = $baseUrl;
        $this->apiToken = $apiToken;
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiToken
            ]
        ]);
    }
    
    public function getConversations()
    {
        try {
            $response = $this->client->get('');
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function createConversation(array $userIds, $initialMessage)
    {
        try {
            $response = $this->client->post('', [
                'json' => [
                    'users' => $userIds,
                    'content' => $initialMessage
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function sendMessage($conversationId, $content)
    {
        try {
            $response = $this->client->post("/{$conversationId}/messages", [
                'json' => [
                    'message' => $content
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    // Other methods...
}

// Usage example
$apiClient = new ConversationsApiClient(
    'https://your-laravel-app.com/api/conversations',
    'your-api-token'
);

// Create a new conversation
$newConversation = $apiClient->createConversation([2, 3], 'Hello from PHP client!');
```

For more detailed examples, see the example files in the `examples` directory:

- [Basic Implementation](examples/basic-implementation.php)
- [Broadcasting Implementation](examples/broadcasting-implementation.php)
- [Hooks Implementation](examples/hooks-implementation.php)
- [API Usage](examples/api-usage.php)