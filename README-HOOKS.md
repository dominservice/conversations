# Conversations Hooks

This package includes a powerful hook system that allows you to execute custom code at key points in the conversation and message lifecycle. This enables you to extend and customize the behavior of the package without modifying its core code.

## Available Hook Points

The following hook points are available in the Conversations class:

### Conversation Hooks

- `before_create_conversation`: Executed before creating a conversation
- `after_create_conversation`: Executed after creating a conversation
- `before_delete_conversation`: Executed before deleting a conversation
- `after_delete_conversation`: Executed after deleting a conversation
- `after_get_conversation`: Executed after retrieving a conversation
- `after_get_conversations`: Executed after retrieving conversations

### Message Hooks

- `before_add_message`: Executed before adding a message
- `after_add_message`: Executed after adding a message
- `before_mark_as_read`: Executed before marking a message as read
- `after_mark_as_read`: Executed after marking a message as read
- `before_mark_as_deleted`: Executed before marking a message as deleted
- `after_mark_as_deleted`: Executed after marking a message as deleted
- `after_get_messages`: Executed after retrieving messages

## Registering Hooks

You can register hooks in two ways:

### 1. Using the Configuration File

You can register hooks in the `config/conversations.php` file:

```php
'hooks' => [
    'after_add_message' => [
        function ($data) {
            // Your custom code here
            $message = $data['message'];
            $conversation = $data['conversation'];
            
            // Example: Log the message
            \Log::info("New message added: {$message->content} in conversation {$conversation->uuid}");
        },
        'App\\Hooks\\ConversationHooks@afterAddMessage',
    ],
],
```

### 2. Using the ConversationsHooks Facade

You can also register hooks programmatically using the `ConversationsHooks` facade:

```php
use Dominservice\Conversations\Facade\ConversationsHooks;

// In a service provider's boot method
public function boot()
{
    ConversationsHooks::register('after_add_message', function ($data) {
        // Your custom code here
        $message = $data['message'];
        $conversation = $data['conversation'];
        
        // Example: Send a notification
        $user = \App\Models\User::find($data['user_id']);
        $user->notify(new \App\Notifications\NewMessage($message));
    });
}
```

## Hook Parameters

Each hook receives different parameters depending on the context. Here are the parameters for each hook:

### Conversation Hooks

#### before_create_conversation
```php
[
    'users' => $users, // Array of user IDs
    'relation_type' => $relationType, // Relation type (optional)
    'relation_id' => $relationId, // Relation ID (optional)
    'content' => $content, // Initial message content (optional)
]
```

#### after_create_conversation
```php
[
    'conversation' => $conversation, // The created conversation
    'users' => $users, // Array of user IDs
    'relation_type' => $relationType, // Relation type (optional)
    'relation_id' => $relationId, // Relation ID (optional)
    'content' => $content, // Initial message content (optional)
]
```

#### before_delete_conversation
```php
[
    'conversation_uuid' => $convUuid, // Conversation UUID
    'user_id' => $userId, // User ID
]
```

#### after_delete_conversation
```php
[
    'conversation_uuid' => $convUuid, // Conversation UUID
    'user_id' => $userId, // User ID
    'conversation_deleted' => $conversationDeleted, // Whether the conversation was actually deleted
]
```

#### after_get_conversation
```php
[
    'conversation' => $conversation, // The retrieved conversation
    'user_id' => $userId, // User ID
]
```

#### after_get_conversations
```php
[
    'conversations' => $conversations, // The retrieved conversations
    'user_id' => $userId, // User ID
    'relation_type' => $relationType, // Relation type (optional)
    'relation_id' => $relationId, // Relation ID (optional)
]
```

### Message Hooks

#### before_add_message
```php
[
    'conversation_uuid' => $convUuid, // Conversation UUID
    'content' => $content, // Message content
    'add_user' => $addUser, // Whether to add the user to the conversation if not already a member
]
```

#### after_add_message
```php
[
    'message' => $message, // The created message
    'conversation' => $conversation, // The conversation
    'user_id' => $userId, // User ID
    'content' => $content, // Message content
]
```

#### before_mark_as_read
```php
[
    'conversation_uuid' => $convUuid, // Conversation UUID
    'message_id' => $msgId, // Message ID
    'user_id' => $userId, // User ID
]
```

#### after_mark_as_read
```php
[
    'conversation_uuid' => $convUuid, // Conversation UUID
    'message_id' => $msgId, // Message ID
    'user_id' => $userId, // User ID
]
```

#### before_mark_as_deleted
```php
[
    'conversation_uuid' => $convUuid, // Conversation UUID
    'message_id' => $msgId, // Message ID
    'user_id' => $userId, // User ID
]
```

#### after_mark_as_deleted
```php
[
    'conversation_uuid' => $convUuid, // Conversation UUID
    'message_id' => $msgId, // Message ID
    'user_id' => $userId, // User ID
]
```

#### after_get_messages
```php
[
    'messages' => $messages, // The retrieved messages
    'conversation_uuid' => $convUuid, // Conversation UUID
    'user_id' => $userId, // User ID
]
```

## Aborting Operations

You can abort an operation by returning `false` from a "before" hook. For example:

```php
ConversationsHooks::register('before_add_message', function ($data) {
    // Check if the message contains prohibited content
    if (str_contains($data['content'], 'prohibited')) {
        // Abort the operation
        return false;
    }
});
```

## Managing Hooks

The `ConversationsHooks` facade provides methods for managing hooks:

```php
// Register a single hook
ConversationsHooks::register('hook_name', $callback);

// Register multiple hooks
ConversationsHooks::registerMany('hook_name', [$callback1, $callback2]);

// Clear all hooks for a specific hook point
ConversationsHooks::clear('hook_name');

// Clear all hooks
ConversationsHooks::clearAll();
```

## Example Use Cases

Here are some example use cases for hooks:

### Logging

```php
ConversationsHooks::register('after_add_message', function ($data) {
    \Log::info("New message added: {$data['message']->content} in conversation {$data['conversation']->uuid}");
});
```

### Notifications

```php
ConversationsHooks::register('after_add_message', function ($data) {
    $conversation = $data['conversation'];
    $message = $data['message'];
    $senderId = $data['user_id'];
    
    // Notify all users in the conversation except the sender
    foreach ($conversation->users as $user) {
        if ($user->id != $senderId) {
            $user->notify(new \App\Notifications\NewMessage($message));
        }
    }
});
```

### Content Filtering

```php
ConversationsHooks::register('before_add_message', function ($data) {
    $content = $data['content'];
    
    // Filter out prohibited words
    $prohibitedWords = ['bad', 'words', 'list'];
    foreach ($prohibitedWords as $word) {
        if (str_contains(strtolower($content), $word)) {
            return false; // Abort the operation
        }
    }
});
```

### Custom Data

```php
ConversationsHooks::register('after_get_conversation', function ($data) {
    $conversation = $data['conversation'];
    
    // Add custom data to the conversation
    $conversation->last_activity = now()->diffForHumans();
    $conversation->participant_names = $conversation->users->pluck('name')->join(', ');
});
```

### Integration with External Services

```php
ConversationsHooks::register('after_add_message', function ($data) {
    $message = $data['message'];
    
    // Send the message to an external service
    \Http::post('https://external-service.com/api/messages', [
        'conversation_id' => $message->conversation_uuid,
        'user_id' => $message->{get_sender_key()},
        'content' => $message->content,
        'timestamp' => $message->created_at->toIso8601String(),
    ]);
});
```