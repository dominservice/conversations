<?php

/**
 * This example demonstrates how to integrate the Laravel Conversations package with Laravel Jetstream.
 * It includes the necessary controller methods, Livewire components, and route definitions.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Dominservice\Conversations\Facade\Conversations as ConversationsFacadeController;
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
        return view('chat.index');
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
        $conversation = ConversationsFacadeController::get($conversationId);

        if (!$conversation || !ConversationsFacadeController::existsUser($conversationId, $userId)) {
            abort(404, 'Conversation not found');
        }

        return view('chat.show', compact('conversation', 'conversationId'));
    }
}

/**
 * Example Livewire component for displaying a list of conversations.
 * Create this file at app/Http/Livewire/Chat/ConversationsList.php
 */

namespace App\Http\Livewire\Chat;

use Livewire\Component;
use Dominservice\Conversations\Facade\Conversations as ConversationsFacadeList;
use App\Models\User;

class ConversationsList extends Component
{
    public $conversations;
    public $users;
    public $showNewConversationModal = false;
    public $selectedUsers = [];
    public $newMessage = '';

    protected $listeners = [
        'conversationAdded' => 'refreshConversations',
        'echo:conversation.user.*,conversation.created' => 'refreshConversations',
    ];

    public function mount()
    {
        $this->refreshConversations();
        $this->users = User::where('id', '!=', auth()->id())->get();
    }

    public function refreshConversations()
    {
        $this->conversations = ConversationsFacadeList::getConversations(auth()->id());
    }

    public function openNewConversationModal()
    {
        $this->showNewConversationModal = true;
    }

    public function closeNewConversationModal()
    {
        $this->showNewConversationModal = false;
        $this->selectedUsers = [];
        $this->newMessage = '';
    }

    public function createConversation()
    {
        $this->validate([
            'selectedUsers' => 'required|array|min:1',
            'newMessage' => 'required|string',
        ]);

        $users = $this->selectedUsers;
        $users[] = auth()->id();

        $conversationId = ConversationsFacadeList::create($users, null, null, $this->newMessage);

        if (!$conversationId) {
            session()->flash('error', 'Failed to create conversation');
            return;
        }

        $this->closeNewConversationModal();
        $this->refreshConversations();

        $this->emit('conversationAdded');

        return redirect()->route('chat.show', $conversationId);
    }

    public function render()
    {
        return view('livewire.chat.conversations-list');
    }
}

/**
 * Example Livewire component for displaying and sending messages in a conversation.
 * Create this file at app/Http/Livewire/Chat/ConversationMessages.php
 */

namespace App\Http\Livewire\Chat;

use Livewire\Component as LivewireComponent;
use Dominservice\Conversations\Facade\Conversations as ConversationsFacade;
use Dominservice\Conversations\Facade\ConversationsBroadcasting;

class ConversationMessages extends LivewireComponent
{
    public $conversationId;
    public $conversation;
    public $messages;
    public $newMessage = '';
    public $typingUsers = [];

    protected $listeners = [
        'echo:conversation.*,message.sent' => 'handleNewMessage',
        'echo:conversation.*,user.typing' => 'handleUserTyping',
        'echo:conversation.*,message.read' => 'handleMessageRead',
    ];

    public function mount($conversationId)
    {
        $this->conversationId = $conversationId;
        $this->conversation = ConversationsFacade::get($conversationId);
        $this->loadMessages();
    }

    public function loadMessages()
    {
        if (!ConversationsFacade::existsUser($this->conversationId, auth()->id())) {
            abort(403, 'You are not part of this conversation');
        }

        $this->messages = ConversationsFacade::getMessages($this->conversationId, auth()->id());

        // Mark all messages as read
        ConversationsFacade::markReadAll($this->conversationId, auth()->id());
    }

    public function sendMessage()
    {
        $this->validate([
            'newMessage' => 'required|string',
        ]);

        $messageId = ConversationsFacade::addMessage($this->conversationId, $this->newMessage);

        if (!$messageId) {
            session()->flash('error', 'Failed to send message');
            return;
        }

        $this->newMessage = '';
        $this->loadMessages();
    }

    public function handleNewMessage($event)
    {
        if ($event['conversation_uuid'] === $this->conversationId) {
            $this->loadMessages();
        }
    }

    public function handleUserTyping($event)
    {
        if ($event['conversation_uuid'] === $this->conversationId && $event['user_id'] !== auth()->id()) {
            $this->typingUsers[$event['user_id']] = [
                'name' => $event['user_name'],
                'timestamp' => now()->timestamp,
            ];

            // Remove typing indicator after 3 seconds
            $this->dispatchBrowserEvent('typing-timeout', [
                'userId' => $event['user_id'],
            ]);
        }
    }

    public function handleMessageRead($event)
    {
        if ($event['conversation_uuid'] === $this->conversationId) {
            // Update read status in UI
            $this->dispatchBrowserEvent('message-read', [
                'messageId' => $event['message_id'],
                'userId' => $event['user_id'],
                'userName' => $event['user_name'],
            ]);
        }
    }

    public function removeTypingUser($userId)
    {
        if (isset($this->typingUsers[$userId])) {
            unset($this->typingUsers[$userId]);
        }
    }

    public function updatedNewMessage()
    {
        // Broadcast typing event
        ConversationsBroadcasting::broadcastUserTyping($this->conversationId, auth()->id(), auth()->user()->name);
    }

    public function render()
    {
        return view('livewire.chat.conversation-messages');
    }
}

/**
 * Example Blade view for the conversations list component.
 * Create this file at resources/views/livewire/chat/conversations-list.blade.php
 */

/*
<div>
    <div class="mb-4">
        <button wire:click="openNewConversationModal" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
            New Conversation
        </button>
    </div>

    @if(empty($conversations))
        <p class="text-gray-500">You don't have any conversations yet.</p>
    @else
        <div class="space-y-4">
            @foreach($conversations as $conversation)
                <div class="border rounded-lg p-4 hover:bg-gray-50">
                    <a href="{{ route('chat.show', $conversation->uuid) }}" class="block">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold">
                                    @if($conversation->name)
                                        {{ $conversation->name }}
                                    @else
                                        {{ $conversation->participants->pluck('name')->join(', ') }}
                                    @endif
                                </h3>
                                <p class="text-gray-600 text-sm">
                                    {{ $conversation->last_message ? Str::limit($conversation->last_message->content, 50) : 'No messages yet' }}
                                </p>
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $conversation->last_message ? $conversation->last_message->created_at->diffForHumans() : $conversation->created_at->diffForHumans() }}
                                @if($conversation->unread_count > 0)
                                    <span class="ml-2 bg-blue-500 text-white px-2 py-1 rounded-full text-xs">
                                        {{ $conversation->unread_count }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <!-- New Conversation Modal -->
    <x-jet-dialog-modal wire:model="showNewConversationModal">
        <x-slot name="title">
            New Conversation
        </x-slot>

        <x-slot name="content">
            <div class="mt-4">
                <x-jet-label for="selectedUsers" value="{{ __('Select Users') }}" />
                <select wire:model="selectedUsers" id="selectedUsers" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm" multiple>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <x-jet-input-error for="selectedUsers" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-jet-label for="newMessage" value="{{ __('Message') }}" />
                <textarea wire:model="newMessage" id="newMessage" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm" rows="3"></textarea>
                <x-jet-input-error for="newMessage" class="mt-2" />
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="closeNewConversationModal" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-jet-secondary-button>

            <x-jet-button class="ml-2" wire:click="createConversation" wire:loading.attr="disabled">
                {{ __('Create') }}
            </x-jet-button>
        </x-slot>
    </x-jet-dialog-modal>
</div>
*/

/**
 * Example Blade view for the conversation messages component.
 * Create this file at resources/views/livewire/chat/conversation-messages.blade.php
 */

/*
<div>
    <div class="flex flex-col h-[60vh]">
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
            @foreach($messages as $message)
                <div class="flex {{ $message->sender_id == auth()->id() ? 'justify-end' : 'justify-start' }}">
                    <div class="{{ $message->sender_id == auth()->id() ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800' }} rounded-lg px-4 py-2 max-w-[70%]" data-message-id="{{ $message->id }}">
                        <div class="font-semibold text-sm">
                            {{ $message->sender->name }}
                        </div>
                        <div>
                            {{ $message->content }}
                        </div>
                        <div class="text-xs {{ $message->sender_id == auth()->id() ? 'text-blue-100' : 'text-gray-500' }} text-right flex items-center justify-end">
                            <span>{{ $message->created_at->format('M j, g:i a') }}</span>
                            <span class="ml-2 read-indicator {{ $message->sender_id != auth()->id() ? 'hidden' : '' }}">
                                <!-- Read indicator icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if(count($typingUsers) > 0)
            <div class="px-4 py-2 text-sm text-gray-500 italic">
                {{ collect($typingUsers)->pluck('name')->join(', ') }} {{ count($typingUsers) > 1 ? 'are' : 'is' }} typing...
            </div>
        @endif

        <div class="border-t p-4">
            <form wire:submit.prevent="sendMessage" class="flex">
                <input type="text" wire:model.defer="newMessage" class="flex-1 border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-l-md shadow-sm" placeholder="Type your message..." required>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-r-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                    Send
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:load', function () {
            // Scroll to bottom of messages container
            const messagesContainer = document.getElementById('messages-container');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // Listen for typing timeout event
            window.addEventListener('typing-timeout', event => {
                setTimeout(() => {
                    @this.removeTypingUser(event.detail.userId);
                }, 3000);
            });

            // Listen for message read event
            window.addEventListener('message-read', event => {
                const messageElement = document.querySelector(`[data-message-id="${event.detail.messageId}"]`);
                if (messageElement) {
                    const readIndicator = messageElement.querySelector('.read-indicator');
                    if (readIndicator) {
                        readIndicator.classList.remove('hidden');
                    }
                }
            });
        });
    </script>
</div>
*/

/**
 * Example Blade views for the chat pages.
 * Create these files at resources/views/chat/index.blade.php and resources/views/chat/show.blade.php
 */

/*
<!-- resources/views/chat/index.blade.php -->
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conversations') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @livewire('chat.conversations-list')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<!-- resources/views/chat/show.blade.php -->
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $conversation->name ?? $conversation->participants->pluck('name')->join(', ') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @livewire('chat.conversation-messages', ['conversationId' => $conversationId])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
*/

/**
 * Example route definitions for Laravel Jetstream integration.
 * Add these routes to your routes/web.php file.
 */

// Route::middleware(['auth:sanctum', 'verified'])->group(function () {
//     Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
//     Route::get('/chat/{conversationId}', [ChatController::class, 'show'])->name('chat.show');
// });

/**
 * Example navigation menu item.
 * Add this to your resources/views/navigation-menu.blade.php file inside the navigation links section.
 */

/*
<!-- Navigation Links -->
<div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
    <!-- ... other links ... -->

    <x-jet-nav-link href="{{ route('chat.index') }}" :active="request()->routeIs('chat.index')">
        {{ __('Chat') }}
    </x-jet-nav-link>
</div>

<!-- Responsive Navigation Menu -->
<div class="pt-4 pb-1 border-t border-gray-200">
    <!-- ... other links ... -->

    <x-jet-responsive-nav-link href="{{ route('chat.index') }}" :active="request()->routeIs('chat.index')">
        {{ __('Chat') }}
    </x-jet-responsive-nav-link>
</div>
*/
