[![Latest Version](https://img.shields.io/github/release/dominservice/conversations.svg?style=flat-square)](https://github.com/dominservice/conversations/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/dominservice/conversations.svg?style=flat-square)](https://packagist.org/packages/dominservice/conversations)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

# Laravel Conversations - Complete Messaging System

A powerful, flexible, and feature-rich messaging system for Laravel applications. Build real-time chat, messaging platforms, support systems, and communication tools with ease.

## Features

- 💬 **Complete Messaging System** - Private and group conversations with full message history
- ⚡ **Real-time Communication** - Built-in broadcasting support for instant messaging
- 🔌 **Multiple Broadcasting Drivers** - Support for Pusher, Laravel WebSockets, Firebase, MQTT, and Socket.IO
- 📎 **File Attachments** - Support for images, documents, audio, and video files with security features
- 🖼️ **Image Optimization** - Automatic image resizing, format conversion, and thumbnail generation
- 🌐 **RESTful API** - Ready-to-use API endpoints for web and mobile applications
- 🪝 **Extensible Hook System** - Customize behavior without modifying core code
- 🌍 **Multilingual Support** - Easily translate all messages to any language
- 📱 **Mobile-Friendly** - Works seamlessly with mobile applications
- 🔒 **Secure** - Built with security best practices including virus scanning for attachments

## Compatibility

| Package Version | Laravel Compatibility |
|-----------------|-----------------------|
| 1.*             | 5.6 - 9.*             |
| 2.*             | 8.* - 11.*            |
| 3.*             | 9.* - 12.*            |

> **Note:** Version 3.0.0 introduces significant new features including real-time broadcasting, hooks system, API endpoints, and multilingual support.

## Installation

### 1. Install via Composer

```bash
composer require dominservice/conversations
```

### 2. Publish Configuration and Migrations

```bash
php artisan vendor:publish --provider="Dominservice\Conversations\ConversationsServiceProvider"
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Register Service Provider (Laravel < 5.5)

For Laravel 5.5 and above, the package will be auto-discovered. For older versions, add the service provider to `config/app.php`:

```php
'providers' => [
    // Other service providers...
    Dominservice\Conversations\ConversationsServiceProvider::class,
],

'aliases' => [
    // Other aliases...
    'Conversations' => Dominservice\Conversations\Facade\Conversations::class,
]
```

## Quick Start

### Basic Usage

```php
// Create a new conversation
$conversationId = Conversations::create([$user1Id, $user2Id], null, null, 'Hello!');

// Add a message to a conversation
Conversations::addMessage($conversationId, 'How are you doing?');

// Get all conversations for a user
$conversations = Conversations::getConversations($userId);

// Get messages in a conversation
$messages = Conversations::getMessages($conversationId, $userId);
```

### Real-time Chat

Enable broadcasting in your `.env` file:

```
CONVERSATIONS_BROADCASTING_ENABLED=true
CONVERSATIONS_BROADCASTING_DRIVER=pusher
```

Then use the broadcasting features:

```php
// Broadcast that a user is typing
ConversationsBroadcasting::broadcastUserTyping($conversationId, $userId, $userName);
```

## Documentation

Comprehensive documentation is available to help you get the most out of the package:

- [Examples & Usage Guide](README-EXAMPLES.md) - Code examples and implementation guides
- [API Documentation](README-API.md) - Information about the REST API endpoints
- [Broadcasting Documentation](README-BROADCASTING.md) - Information about real-time broadcasting
- [Hooks Documentation](README-HOOKS.md) - Information about the hook system
- [Translations Documentation](README-TRANSLATIONS.md) - Information about customizing messages
- [Routes Documentation](README-ROUTES.md) - Information about customizing API routes

## Upgrading from laravel_chat

This package is a continuation of the [dominservice/laravel_chat](https://github.com/dominservice/laravel_chat) package. If you're upgrading:

1. Install this package alongside the old one
2. Run migrations (they include data migration scripts)
3. Remove the old package

Always make a backup before performing this operation!

## Testing

The package includes automated tests to ensure functionality works as expected. To run the tests:

### Requirements

- PHP with SQLite extension enabled (`php-sqlite3`)

If you don't have the SQLite extension installed, you can install it on Ubuntu/Debian with:

```bash
sudo apt-get install php-sqlite3
```

On CentOS/RHEL:

```bash
sudo yum install php-sqlite3
```

### Running Tests

```bash
composer install
vendor/bin/phpunit
```

Alternatively, you can use the provided script:

```bash
./run-tests.sh
```

## Usage Examples

Here are some common usage examples. For more detailed examples, see the [Examples & Usage Guide](README-EXAMPLES.md).

### Creating and Managing Conversations

```php
// Create a new conversation between users
$conversationId = Conversations::create([$user1Id, $user2Id], null, null, 'Initial message');

// Add a message to an existing conversation
$messageId = Conversations::addMessage($conversationId, 'Hello, how are you?');

// Create a conversation or add to existing one if it exists
$messageId = Conversations::addMessageOrCreateConversation([$user1Id, $user2Id], 'Hello!');

// Get conversation ID between specific users
$conversationId = Conversations::getIdBetweenUsers([$user1Id, $user2Id]);

// Check if a user is part of a conversation
$isUserInConversation = Conversations::existsUser($conversationId, $userId);

// Delete a conversation (for a specific user)
Conversations::delete($conversationId, $userId);
```

### Reading Messages and Conversations

```php
// Get all conversations for a user
$conversations = Conversations::getConversations($userId);

// Get messages in a conversation
$messages = Conversations::getMessages($conversationId, $userId);

// Get only unread messages
$unreadMessages = Conversations::getUnreadMessages($conversationId, $userId);

// Get count of all unread messages for a user
$unreadCount = Conversations::getUnreadCount($userId);

// Get count of unread messages in a specific conversation
$conversationUnreadCount = Conversations::getConversationUnreadCount($conversationId, $userId);
```

### Message Status Management

```php
// Mark a message as read
Conversations::markAsRead($conversationId, $messageId, $userId);

// Mark a message as unread
Conversations::markAsUnread($conversationId, $messageId, $userId);

// Mark a message as deleted
Conversations::markAsDeleted($conversationId, $messageId, $userId);

// Mark a message as archived
Conversations::markAsArchived($conversationId, $messageId, $userId);

// Mark all messages in a conversation as read
Conversations::markReadAll($conversationId, $userId);

// Mark all messages in a conversation as unread
Conversations::markUnreadAll($conversationId, $userId);
```

### Read Receipts and "Seen By" Functionality

```php
// Get all users who have read a specific message
$readBy = Conversations::getMessageReadBy($messageId);

// Get all messages in a conversation with their read status for all users
$messagesWithReadStatus = Conversations::getConversationReadBy($conversationId);

// Example of accessing read receipt information
foreach ($messagesWithReadStatus as $message) {
    echo "Message: {$message['content']}\n";
    echo "Read by {$message['read_count']} users\n";

    foreach ($message['read_by'] as $user) {
        echo "- {$user->name} ({$user->email})\n";
    }
}
```

#### API Endpoints for Read Receipts

The package provides API endpoints for retrieving read receipt information:

- `GET /api/conversations/{uuid}/messages/{messageId}/read-by` - Get all users who have read a specific message
- `GET /api/conversations/{uuid}/read-by` - Get all messages in a conversation with their read status

#### Real-time Read Receipts

When broadcasting is enabled, the `message.read` event includes information about who has read the message:

```javascript
Echo.private(`conversation.${conversationId}`)
    .listen('.message.read', (e) => {
        console.log(`Message ${e.message_id} was read by ${e.user.name}`);
        console.log(`Total readers: ${e.read_count}`);

        // Update UI to show who has seen the message
        updateSeenByList(e.message_id, e.read_by);
    });
```

### Attachments

The package supports file attachments with various security and optimization features:

```php
// Add a message with attachments
$file = $request->file('attachment');
$messageId = Conversations::addMessageWithAttachments($conversationId, 'Check out this file!', [$file]);

// Get attachments for a message
$message = ConversationMessage::with('attachments')->find($messageId);
$attachments = $message->attachments;

// Check if a message has attachments
if ($message->hasAttachments()) {
    $firstAttachment = $message->getFirstAttachment();
    $attachmentUrl = $firstAttachment->getUrlAttribute();

    // For images, get thumbnails
    if ($firstAttachment->isImage()) {
        $thumbnailUrl = $firstAttachment->getThumbnailUrl('small');
    }

    // Check if attachment requires a warning
    if ($firstAttachment->requiresWarning()) {
        // Show warning to user
    }
}
```

### Helper Functions

The package also provides helper functions for easier usage:

```php
// Create a conversation
$conversationId = conversation_create([$user1Id, $user2Id], null, null, 'Initial message');

// Add a message
$messageId = conversation_add_message($conversationId, 'Hello!');

// Get conversations
$conversations = conversations($userId);

// Get messages
$messages = conversation_messages($conversationId, $userId);

// Mark as read
conversation_mark_as_read($conversationId, $messageId, $userId);
```

## Why Choose Laravel Conversations?

- **Complete Solution**: Everything you need for a messaging system in one package
- **Highly Customizable**: Extensive configuration options and hook system
- **Well-Documented**: Comprehensive documentation with examples
- **Actively Maintained**: Regular updates and improvements
- **Production Ready**: Used in production applications
- **Mobile Compatible**: Works with web and mobile applications
- **Real-time Capable**: Built-in broadcasting support

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

- [dominservice](https://github.com/dominservice)
- [tzookb/tbmsg](https://github.com/tzookb/tbmsg) (Original inspiration)
- [All Contributors](../../contributors)
