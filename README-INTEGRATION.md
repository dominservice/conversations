# Laravel Conversations - Integration Guides

This document provides detailed guides on how to integrate the Laravel Conversations package with various Laravel starter kits and frameworks.

## Table of Contents

- [Laravel Breeze Integration](#laravel-breeze-integration)
- [Laravel Jetstream Integration](#laravel-jetstream-integration)
- [Laravel Livewire Integration](#laravel-livewire-integration)
- [Laravel Inertia.js Integration](#laravel-inertiajs-integration)
- [Laravel Sanctum Integration](#laravel-sanctum-integration)
- [Laravel Nova Integration](#laravel-nova-integration)

## Laravel Breeze Integration

[Laravel Breeze](https://laravel.com/docs/breeze) is a minimal, simple implementation of all of Laravel's authentication features, including login, registration, password reset, email verification, and password confirmation. This guide will show you how to integrate the Conversations package with Laravel Breeze.

### Prerequisites

- A Laravel application with Breeze installed
- Laravel Conversations package installed

### Step 1: Install Laravel Breeze

If you haven't already installed Laravel Breeze, you can do so with the following commands:

```bash
composer require laravel/breeze --dev
php artisan breeze:install
npm install
npm run dev
```

### Step 2: Install Laravel Conversations

Follow the installation instructions in the [README.md](README.md#installation) file to install the Laravel Conversations package.

### Step 3: Create Chat Routes

Add the following routes to your `routes/web.php` file:

```php
use App\Http\Controllers\ChatController;

Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversationId}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::post('/chat/{conversationId}/messages', [ChatController::class, 'addMessage'])->name('chat.messages.store');
    Route::delete('/chat/{conversationId}', [ChatController::class, 'destroy'])->name('chat.destroy');
});
```

### Step 4: Create Chat Controller

Create a new controller for handling chat functionality:

```bash
php artisan make:controller ChatController
```

Then, implement the controller using the example from the [Basic Implementation](README-EXAMPLES.md#basic-implementation) section.

### Step 5: Create Chat Views

Create the necessary Blade views for your chat interface:

1. Create a directory `resources/views/chat`
2. Create the following files:
   - `resources/views/chat/index.blade.php` - List of conversations
   - `resources/views/chat/show.blade.php` - Individual conversation with messages

#### Example index.blade.php

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conversations') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-4">
                        <a href="#" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded" 
                           onclick="document.getElementById('new-conversation-modal').classList.remove('hidden'); return false;">
                            New Conversation
                        </a>
                    </div>

                    @if($conversations->isEmpty())
                        <p>You don't have any conversations yet.</p>
                    @else
                        <div class="space-y-4">
                            @foreach($conversations as $conversation)
                                <div class="border rounded p-4 hover:bg-gray-50">
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
                </div>
            </div>
        </div>
    </div>

    <!-- New Conversation Modal -->
    <div id="new-conversation-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">New Conversation</h3>
                <form method="POST" action="{{ route('chat.store') }}" class="mt-2 text-left">
                    @csrf
                    <div class="mb-4">
                        <label for="users" class="block text-gray-700 text-sm font-bold mb-2">Select Users:</label>
                        <select name="users[]" id="users" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" multiple required>
                            @foreach(\App\Models\User::where('id', '!=', auth()->id())->get() as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="message" class="block text-gray-700 text-sm font-bold mb-2">Message:</label>
                        <textarea name="message" id="message" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required></textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="button" onclick="document.getElementById('new-conversation-modal').classList.add('hidden')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### Example show.blade.php

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            @if($conversation->name)
                {{ $conversation->name }}
            @else
                {{ $conversation->participants->pluck('name')->join(', ') }}
            @endif
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex flex-col h-[60vh]">
                        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
                            @foreach($messages as $message)
                                <div class="flex {{ $message->sender_id == auth()->id() ? 'justify-end' : 'justify-start' }}">
                                    <div class="{{ $message->sender_id == auth()->id() ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800' }} rounded-lg px-4 py-2 max-w-[70%]">
                                        <div class="font-semibold text-sm">
                                            {{ $message->sender->name }}
                                        </div>
                                        <div>
                                            {{ $message->content }}
                                        </div>
                                        <div class="text-xs {{ $message->sender_id == auth()->id() ? 'text-blue-100' : 'text-gray-500' }} text-right">
                                            {{ $message->created_at->format('M j, g:i a') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="border-t p-4">
                            <form method="POST" action="{{ route('chat.messages.store', $conversation->uuid) }}" class="flex">
                                @csrf
                                <input type="text" name="message" class="flex-1 border rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type your message..." required>
                                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-r-lg">
                                    Send
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

### Step 6: Add Navigation Link

Add a link to the chat in your navigation menu. Edit `resources/views/layouts/navigation.blade.php` and add:

```blade
<div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
    <!-- Existing links -->
    
    <x-nav-link :href="route('chat.index')" :active="request()->routeIs('chat.index')">
        {{ __('Chat') }}
    </x-nav-link>
</div>
```

Also add it to the responsive menu:

```blade
<div class="pt-2 pb-3 space-y-1">
    <!-- Existing links -->
    
    <x-responsive-nav-link :href="route('chat.index')" :active="request()->routeIs('chat.index')">
        {{ __('Chat') }}
    </x-responsive-nav-link>
</div>
```

### Step 7: Implement Real-time Updates (Optional)

For real-time updates, follow the [Broadcasting Implementation](README-EXAMPLES.md#broadcasting-implementation) guide and update your Blade views to use JavaScript for real-time updates.

## Laravel Jetstream Integration

[Laravel Jetstream](https://jetstream.laravel.com/) provides a beautifully designed application scaffolding for Laravel and includes login, registration, email verification, two-factor authentication, session management, API support via Laravel Sanctum, and optional team management. This guide will show you how to integrate the Conversations package with Laravel Jetstream.

### Prerequisites

- A Laravel application with Jetstream installed
- Laravel Conversations package installed

### Step 1: Install Laravel Jetstream

If you haven't already installed Laravel Jetstream, you can do so with the following commands:

```bash
composer require laravel/jetstream
```

Then, install Jetstream with either Livewire or Inertia:

```bash
# For Livewire
php artisan jetstream:install livewire

# For Inertia
php artisan jetstream:install inertia
```

Then complete the installation:

```bash
npm install
npm run dev
php artisan migrate
```

### Step 2: Install Laravel Conversations

Follow the installation instructions in the [README.md](README.md#installation) file to install the Laravel Conversations package.

### Step 3: Create Chat Routes and Controller

Follow the same steps as in the Laravel Breeze integration to create routes and a controller.

### Step 4: Create Chat Views

#### For Jetstream with Livewire

Create Livewire components for the chat functionality:

```bash
php artisan make:livewire Chat/ConversationsList
php artisan make:livewire Chat/ConversationMessages
```

Implement these components following the examples in the [Laravel Livewire Integration](#laravel-livewire-integration) section.

#### For Jetstream with Inertia

Create Vue components for the chat functionality following the examples in the [Laravel Inertia.js Integration](#laravel-inertiajs-integration) section.

### Step 5: Add Navigation Link

Add a link to the chat in your navigation menu:

#### For Jetstream with Livewire

Edit `resources/views/navigation-menu.blade.php` and add:

```blade
<div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
    <!-- Existing links -->
    
    <x-jet-nav-link href="{{ route('chat.index') }}" :active="request()->routeIs('chat.index')">
        {{ __('Chat') }}
    </x-jet-nav-link>
</div>
```

Also add it to the responsive menu:

```blade
<div class="pt-4 pb-1 border-t border-gray-200">
    <!-- Existing links -->
    
    <x-jet-responsive-nav-link href="{{ route('chat.index') }}" :active="request()->routeIs('chat.index')">
        {{ __('Chat') }}
    </x-jet-responsive-nav-link>
</div>
```

#### For Jetstream with Inertia

Edit `resources/js/Layouts/AppLayout.vue` and add:

```vue
<jet-nav-link :href="route('chat.index')" :active="route().current('chat.index')">
    Chat
</jet-nav-link>
```

## Laravel Livewire Integration

[Laravel Livewire](https://laravel-livewire.com/) is a full-stack framework for Laravel that makes building dynamic interfaces simple, without leaving the comfort of Laravel. This guide will show you how to integrate the Conversations package with Laravel Livewire.

### Prerequisites

- A Laravel application with Livewire installed
- Laravel Conversations package installed

### Step 1: Install Laravel Livewire

If you haven't already installed Laravel Livewire, you can do so with the following commands:

```bash
composer require livewire/livewire
```

### Step 2: Install Laravel Conversations

Follow the installation instructions in the [README.md](README.md#installation) file to install the Laravel Conversations package.

### Step 3: Create Livewire Components

Create Livewire components for the chat functionality:

```bash
php artisan make:livewire Chat/ConversationsList
php artisan make:livewire Chat/ConversationMessages
```

#### Example ConversationsList Component

```php
<?php

namespace App\Http\Livewire\Chat;

use Livewire\Component;
use Dominservice\Conversations\Facade\Conversations;

class ConversationsList extends Component
{
    public $conversations;
    
    protected $listeners = [
        'conversationAdded' => 'refreshConversations',
        'echo:conversation.user.*,conversation.created' => 'refreshConversations',
    ];
    
    public function mount()
    {
        $this->refreshConversations();
    }
    
    public function refreshConversations()
    {
        $this->conversations = Conversations::getConversations(auth()->id());
    }
    
    public function render()
    {
        return view('livewire.chat.conversations-list');
    }
}
```

Create the view at `resources/views/livewire/chat/conversations-list.blade.php`:

```blade
<div>
    <div class="mb-4">
        <button wire:click="$emit('openNewConversationModal')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            New Conversation
        </button>
    </div>

    @if(empty($conversations))
        <p>You don't have any conversations yet.</p>
    @else
        <div class="space-y-4">
            @foreach($conversations as $conversation)
                <div class="border rounded p-4 hover:bg-gray-50">
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
</div>
```

#### Example ConversationMessages Component

```php
<?php

namespace App\Http\Livewire\Chat;

use Livewire\Component;
use Dominservice\Conversations\Facade\Conversations;
use Dominservice\Conversations\Facade\ConversationsBroadcasting;

class ConversationMessages extends Component
{
    public $conversationId;
    public $messages;
    public $newMessage;
    public $typingUsers = [];
    
    protected $listeners = [
        'echo:conversation.*,message.sent' => 'handleNewMessage',
        'echo:conversation.*,user.typing' => 'handleUserTyping',
    ];
    
    public function mount($conversationId)
    {
        $this->conversationId = $conversationId;
        $this->loadMessages();
    }
    
    public function loadMessages()
    {
        if (!Conversations::existsUser($this->conversationId, auth()->id())) {
            abort(403, 'You are not part of this conversation');
        }
        
        $this->messages = Conversations::getMessages($this->conversationId, auth()->id());
        
        // Mark all messages as read
        Conversations::markReadAll($this->conversationId, auth()->id());
    }
    
    public function sendMessage()
    {
        if (empty($this->newMessage)) {
            return;
        }
        
        $messageId = Conversations::addMessage($this->conversationId, $this->newMessage);
        
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
```

Create the view at `resources/views/livewire/chat/conversation-messages.blade.php`:

```blade
<div>
    <div class="flex flex-col h-[60vh]">
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
            @foreach($messages as $message)
                <div class="flex {{ $message->sender_id == auth()->id() ? 'justify-end' : 'justify-start' }}">
                    <div class="{{ $message->sender_id == auth()->id() ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800' }} rounded-lg px-4 py-2 max-w-[70%]">
                        <div class="font-semibold text-sm">
                            {{ $message->sender->name }}
                        </div>
                        <div>
                            {{ $message->content }}
                        </div>
                        <div class="text-xs {{ $message->sender_id == auth()->id() ? 'text-blue-100' : 'text-gray-500' }} text-right">
                            {{ $message->created_at->format('M j, g:i a') }}
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
                <input type="text" wire:model.defer="newMessage" class="flex-1 border rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type your message..." required>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-r-lg">
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
        });
    </script>
</div>
```

### Step 4: Create Routes and Controller

Create routes in `routes/web.php`:

```php
use App\Http\Controllers\ChatController;

Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversationId}', [ChatController::class, 'show'])->name('chat.show');
});
```

Create a controller:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Dominservice\Conversations\Facade\Conversations;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat.index');
    }
    
    public function show($conversationId)
    {
        $userId = auth()->id();
        $conversation = Conversations::get($conversationId);
        
        if (!$conversation || !Conversations::existsUser($conversationId, $userId)) {
            abort(404, 'Conversation not found');
        }
        
        return view('chat.show', compact('conversation', 'conversationId'));
    }
}
```

Create the views:

```blade
<!-- resources/views/chat/index.blade.php -->
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conversations') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
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
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @livewire('chat.conversation-messages', ['conversationId' => $conversationId])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

## Laravel Inertia.js Integration

[Inertia.js](https://inertiajs.com/) allows you to create fully client-side rendered, single-page apps, without the complexity that comes with modern SPAs. It does this by leveraging existing server-side frameworks. This guide will show you how to integrate the Conversations package with Laravel and Inertia.js.

### Prerequisites

- A Laravel application with Inertia.js installed
- Laravel Conversations package installed

### Step 1: Install Inertia.js

If you haven't already installed Inertia.js, you can do so by following the [official installation guide](https://inertiajs.com/server-side-setup).

### Step 2: Install Laravel Conversations

Follow the installation instructions in the [README.md](README.md#installation) file to install the Laravel Conversations package.

### Step 3: Create Routes and Controller

Create routes in `routes/web.php`:

```php
use App\Http\Controllers\ChatController;

Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversationId}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::post('/chat/{conversationId}/messages', [ChatController::class, 'addMessage'])->name('chat.messages.store');
});
```

Create a controller:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Dominservice\Conversations\Facade\Conversations;
use App\Models\User;

class ChatController extends Controller
{
    public function index()
    {
        $conversations = Conversations::getConversations(auth()->id());
        $users = User::where('id', '!=', auth()->id())->get();
        
        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'users' => $users,
        ]);
    }
    
    public function show($conversationId)
    {
        $userId = auth()->id();
        $conversation = Conversations::get($conversationId);
        
        if (!$conversation || !Conversations::existsUser($conversationId, $userId)) {
            abort(404, 'Conversation not found');
        }
        
        $messages = Conversations::getMessages($conversationId, $userId);
        
        // Mark all unread messages as read
        Conversations::markReadAll($conversationId, $userId);
        
        return Inertia::render('Chat/Show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'users' => 'required|array',
            'message' => 'required|string',
        ]);
        
        $users = $request->input('users');
        $message = $request->input('message');
        
        // Add current user to the conversation
        $users[] = auth()->id();
        
        // Create conversation and add initial message
        $conversationId = Conversations::create($users, null, null, $message);
        
        if (!$conversationId) {
            return back()->with('error', 'Failed to create conversation');
        }
        
        return redirect()->route('chat.show', $conversationId);
    }
    
    public function addMessage(Request $request, $conversationId)
    {
        $request->validate([
            'message' => 'required|string',
        ]);
        
        $userId = auth()->id();
        
        if (!Conversations::existsUser($conversationId, $userId)) {
            abort(403, 'You are not part of this conversation');
        }
        
        $message = $request->input('message');
        $messageId = Conversations::addMessage($conversationId, $message);
        
        if (!$messageId) {
            return back()->with('error', 'Failed to send message');
        }
        
        return back();
    }
}
```

### Step 4: Create Vue Components

Create Vue components for the chat functionality:

```bash
mkdir -p resources/js/Pages/Chat
touch resources/js/Pages/Chat/Index.vue
touch resources/js/Pages/Chat/Show.vue
```

#### Example Index.vue

```vue
<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Conversations
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <div class="mb-4">
              <button @click="showNewConversationModal = true" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                New Conversation
              </button>
            </div>

            <div v-if="conversations.length === 0" class="text-gray-500">
              You don't have any conversations yet.
            </div>
            <div v-else class="space-y-4">
              <div v-for="conversation in conversations" :key="conversation.uuid" class="border rounded p-4 hover:bg-gray-50">
                <inertia-link :href="route('chat.show', conversation.uuid)" class="block">
                  <div class="flex justify-between items-center">
                    <div>
                      <h3 class="text-lg font-semibold">
                        {{ conversation.name || conversation.participants.map(p => p.name).join(', ') }}
                      </h3>
                      <p class="text-gray-600 text-sm">
                        {{ conversation.last_message ? (conversation.last_message.content.length > 50 ? conversation.last_message.content.substring(0, 50) + '...' : conversation.last_message.content) : 'No messages yet' }}
                      </p>
                    </div>
                    <div class="text-sm text-gray-500">
                      {{ conversation.last_message ? formatDate(conversation.last_message.created_at) : formatDate(conversation.created_at) }}
                      <span v-if="conversation.unread_count > 0" class="ml-2 bg-blue-500 text-white px-2 py-1 rounded-full text-xs">
                        {{ conversation.unread_count }}
                      </span>
                    </div>
                  </div>
                </inertia-link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- New Conversation Modal -->
    <modal :show="showNewConversationModal" @close="showNewConversationModal = false">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900">
          New Conversation
        </h2>

        <form @submit.prevent="createConversation" class="mt-6">
          <div class="mb-4">
            <label for="users" class="block text-gray-700 text-sm font-bold mb-2">Select Users:</label>
            <select v-model="form.users" id="users" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" multiple required>
              <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.name }}
              </option>
            </select>
          </div>
          <div class="mb-4">
            <label for="message" class="block text-gray-700 text-sm font-bold mb-2">Message:</label>
            <textarea v-model="form.message" id="message" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required></textarea>
          </div>
          <div class="flex items-center justify-end mt-4">
            <button type="button" @click="showNewConversationModal = false" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mr-2">
              Cancel
            </button>
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" :disabled="form.processing">
              Create
            </button>
          </div>
        </form>
      </div>
    </modal>
  </app-layout>
</template>

<script>
import { defineComponent } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { useForm } from '@inertiajs/inertia-vue3'

export default defineComponent({
  components: {
    AppLayout,
    Modal
  },
  props: {
    conversations: Array,
    users: Array
  },
  setup() {
    const form = useForm({
      users: [],
      message: ''
    })

    return { form }
  },
  data() {
    return {
      showNewConversationModal: false
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleString()
    },
    createConversation() {
      this.form.post(route('chat.store'), {
        onSuccess: () => {
          this.showNewConversationModal = false
          this.form.reset()
        }
      })
    }
  }
})
</script>
```

#### Example Show.vue

```vue
<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ conversation.name || conversation.participants.map(p => p.name).join(', ') }}
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex flex-col h-[60vh]">
              <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
                <div v-for="message in messages" :key="message.id" class="flex" :class="{ 'justify-end': message.sender_id === $page.props.auth.user.id, 'justify-start': message.sender_id !== $page.props.auth.user.id }">
                  <div class="rounded-lg px-4 py-2 max-w-[70%]" :class="{ 'bg-blue-500 text-white': message.sender_id === $page.props.auth.user.id, 'bg-gray-200 text-gray-800': message.sender_id !== $page.props.auth.user.id }">
                    <div class="font-semibold text-sm">
                      {{ message.sender.name }}
                    </div>
                    <div>
                      {{ message.content }}
                    </div>
                    <div class="text-xs text-right" :class="{ 'text-blue-100': message.sender_id === $page.props.auth.user.id, 'text-gray-500': message.sender_id !== $page.props.auth.user.id }">
                      {{ formatDate(message.created_at) }}
                    </div>
                  </div>
                </div>
              </div>
              <div class="border-t p-4">
                <form @submit.prevent="sendMessage" class="flex">
                  <input v-model="form.message" type="text" class="flex-1 border rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type your message..." required>
                  <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-r-lg" :disabled="form.processing">
                    Send
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import { defineComponent } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm } from '@inertiajs/inertia-vue3'

export default defineComponent({
  components: {
    AppLayout
  },
  props: {
    conversation: Object,
    messages: Array
  },
  setup(props) {
    const form = useForm({
      message: ''
    })

    return { form }
  },
  mounted() {
    this.scrollToBottom()
    this.setupEcho()
  },
  updated() {
    this.scrollToBottom()
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleString()
    },
    scrollToBottom() {
      this.$nextTick(() => {
        if (this.$refs.messagesContainer) {
          this.$refs.messagesContainer.scrollTop = this.$refs.messagesContainer.scrollHeight
        }
      })
    },
    sendMessage() {
      this.form.post(route('chat.messages.store', this.conversation.uuid), {
        onSuccess: () => {
          this.form.reset()
        }
      })
    },
    setupEcho() {
      // If you're using Laravel Echo for real-time updates
      if (window.Echo) {
        window.Echo.private(`conversation.${this.conversation.uuid}`)
          .listen('.message.sent', (e) => {
            // Add the new message to the list if it's not from the current user
            if (e.sender_id !== this.$page.props.auth.user.id) {
              this.messages.push(e)
              this.scrollToBottom()
            }
          })
      }
    }
  }
})
</script>
```

## Laravel Sanctum Integration

[Laravel Sanctum](https://laravel.com/docs/sanctum) provides a featherweight authentication system for SPAs, mobile applications, and simple, token-based APIs. This guide will show you how to integrate the Conversations package with Laravel Sanctum for API authentication.

### Prerequisites

- A Laravel application with Sanctum installed
- Laravel Conversations package installed

### Step 1: Install Laravel Sanctum

If you haven't already installed Laravel Sanctum, you can do so with the following commands:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### Step 2: Configure Sanctum

Add Sanctum's middleware to your `app/Http/Kernel.php` file:

```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

### Step 3: Create Authentication Endpoints

Create authentication endpoints for your API:

```php
// routes/api.php
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
```

Create the AuthController:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
```

### Step 4: Use Conversations API with Sanctum

Now you can use the Conversations API with Sanctum authentication:

```php
// routes/api.php
use App\Http\Controllers\Api\ConversationController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{uuid}', [ConversationController::class, 'show']);
    Route::post('/conversations/{uuid}/messages', [ConversationController::class, 'addMessage']);
    // Add more routes as needed
});
```

Create the ConversationController:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Dominservice\Conversations\Facade\Conversations;

class ConversationController extends Controller
{
    public function index()
    {
        $conversations = Conversations::getConversations(auth()->id());
        
        return response()->json([
            'data' => $conversations
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'users' => 'required|array',
            'content' => 'required|string',
        ]);
        
        $users = $request->input('users');
        $content = $request->input('content');
        
        // Add current user to the conversation
        $users[] = auth()->id();
        
        // Create conversation and add initial message
        $conversationId = Conversations::create($users, null, null, $content);
        
        if (!$conversationId) {
            return response()->json([
                'message' => 'Failed to create conversation'
            ], 500);
        }
        
        $conversation = Conversations::get($conversationId);
        
        return response()->json([
            'data' => $conversation
        ], 201);
    }
    
    public function show($uuid)
    {
        $userId = auth()->id();
        $conversation = Conversations::get($uuid);
        
        if (!$conversation || !Conversations::existsUser($uuid, $userId)) {
            return response()->json([
                'message' => 'Conversation not found'
            ], 404);
        }
        
        $messages = Conversations::getMessages($uuid, $userId);
        
        // Mark all unread messages as read
        Conversations::markReadAll($uuid, $userId);
        
        return response()->json([
            'data' => [
                'conversation' => $conversation,
                'messages' => $messages
            ]
        ]);
    }
    
    public function addMessage(Request $request, $uuid)
    {
        $request->validate([
            'message' => 'required|string',
        ]);
        
        $userId = auth()->id();
        
        if (!Conversations::existsUser($uuid, $userId)) {
            return response()->json([
                'message' => 'You are not part of this conversation'
            ], 403);
        }
        
        $message = $request->input('message');
        $messageObj = Conversations::addMessage($uuid, $message);
        
        if (!$messageObj) {
            return response()->json([
                'message' => 'Failed to send message'
            ], 500);
        }
        
        return response()->json([
            'data' => $messageObj
        ], 201);
    }
}
```

### Step 5: Example API Usage with Sanctum

Here's an example of how to use the API with Sanctum authentication:

```javascript
// Login and get token
async function login(email, password) {
    const response = await fetch('/api/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ email, password })
    });
    
    const data = await response.json();
    
    // Store the token
    localStorage.setItem('token', data.access_token);
    
    return data;
}

// Get conversations
async function getConversations() {
    const token = localStorage.getItem('token');
    
    const response = await fetch('/api/conversations', {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
        }
    });
    
    return response.json();
}

// Create a new conversation
async function createConversation(users, content) {
    const token = localStorage.getItem('token');
    
    const response = await fetch('/api/conversations', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ users, content })
    });
    
    return response.json();
}

// Get a conversation with messages
async function getConversation(uuid) {
    const token = localStorage.getItem('token');
    
    const response = await fetch(`/api/conversations/${uuid}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
        }
    });
    
    return response.json();
}

// Send a message
async function sendMessage(uuid, message) {
    const token = localStorage.getItem('token');
    
    const response = await fetch(`/api/conversations/${uuid}/messages`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ message })
    });
    
    return response.json();
}
```

## Laravel Nova Integration

[Laravel Nova](https://nova.laravel.com/) is a beautifully designed administration panel for Laravel. This guide will show you how to integrate the Conversations package with Laravel Nova.

### Prerequisites

- A Laravel application with Nova installed
- Laravel Conversations package installed

### Step 1: Install Laravel Nova

Follow the [official installation guide](https://nova.laravel.com/docs/installation.html) to install Laravel Nova.

### Step 2: Create Nova Resources

Create Nova resources for the Conversations models:

```bash
php artisan nova:resource Conversation
php artisan nova:resource ConversationMessage
php artisan nova:resource ConversationParticipant
```

### Step 3: Implement the Resources

#### Conversation Resource

```php
<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Http\Requests\NovaRequest;

class Conversation extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \Dominservice\Conversations\Models\Conversation::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'uuid', 'name',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            
            Text::make(__('UUID'), 'uuid')
                ->sortable()
                ->readonly(),
                
            Text::make(__('Name'), 'name')
                ->sortable()
                ->nullable(),
                
            DateTime::make(__('Created At'), 'created_at')
                ->sortable()
                ->readonly(),
                
            DateTime::make(__('Updated At'), 'updated_at')
                ->sortable()
                ->readonly(),
                
            HasMany::make(__('Messages'), 'messages', ConversationMessage::class),
            
            BelongsToMany::make(__('Participants'), 'participants', User::class),
        ];
    }
}
```

#### ConversationMessage Resource

```php
<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Http\Requests\NovaRequest;

class ConversationMessage extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \Dominservice\Conversations\Models\ConversationMessage::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'content',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            
            BelongsTo::make(__('Conversation'), 'conversation', Conversation::class)
                ->sortable(),
                
            BelongsTo::make(__('Sender'), 'sender', User::class)
                ->sortable(),
                
            Textarea::make(__('Content'), 'content')
                ->alwaysShow(),
                
            Boolean::make(__('Is Edited'), 'is_edited')
                ->sortable(),
                
            DateTime::make(__('Created At'), 'created_at')
                ->sortable()
                ->readonly(),
                
            DateTime::make(__('Updated At'), 'updated_at')
                ->sortable()
                ->readonly(),
        ];
    }
}
```

#### ConversationParticipant Resource

```php
<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

class ConversationParticipant extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \Dominservice\Conversations\Models\ConversationParticipant::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            
            BelongsTo::make(__('Conversation'), 'conversation', Conversation::class)
                ->sortable(),
                
            BelongsTo::make(__('User'), 'user', User::class)
                ->sortable(),
                
            DateTime::make(__('Created At'), 'created_at')
                ->sortable()
                ->readonly(),
                
            DateTime::make(__('Updated At'), 'updated_at')
                ->sortable()
                ->readonly(),
        ];
    }
}
```

### Step 4: Create a Nova Tool (Optional)

For a more integrated experience, you can create a custom Nova tool for managing conversations:

```bash
php artisan nova:tool ConversationsManager
```

Follow the instructions to set up the tool and implement a custom interface for managing conversations within Nova.

## Conclusion

These integration guides should help you get started with integrating the Laravel Conversations package into your Laravel applications. Each guide provides a step-by-step approach to integrating with different Laravel starter kits and frameworks.

For more detailed examples and usage information, refer to the [Examples & Usage Guide](README-EXAMPLES.md).