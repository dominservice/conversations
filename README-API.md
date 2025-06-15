# Conversations API

This package includes a RESTful API for managing conversations and messages. The API allows you to create, read, update, and delete conversations and messages, as well as mark messages as read or unread.

## Configuration

The API is enabled by default. You can disable it or configure it in the `config/conversations.php` file:

```php
'api' => [
    'enabled' => env('CONVERSATIONS_API_ENABLED', true),
    'prefix' => 'api/conversations',
    'middleware' => ['api', 'auth:api'],
],
```

## Available Endpoints

### Conversations

#### Get all conversations

```
GET /api/conversations
```

Query parameters:
- `relation_type` (optional): Filter conversations by relation type
- `relation_id` (optional): Filter conversations by relation ID

Response:
```json
{
    "data": [
        {
            "uuid": "123e4567-e89b-12d3-a456-426614174000",
            "owner_uuid": "user-123",
            "created_at": "2023-01-01T00:00:00.000000Z",
            "updated_at": "2023-01-01T00:00:00.000000Z",
            "users": [
                {
                    "uuid": "user-123",
                    "name": "John Doe"
                },
                {
                    "uuid": "user-456",
                    "name": "Jane Smith"
                }
            ],
            "count_unread": 5
        }
    ]
}
```

#### Create a conversation

```
POST /api/conversations
```

Request body:
```json
{
    "users": ["user-123", "user-456"],
    "content": "Hello, how are you?",
    "relation_type": "App\\Models\\Project",
    "relation_id": 1
}
```

Response:
```json
{
    "data": {
        "uuid": "123e4567-e89b-12d3-a456-426614174000",
        "owner_uuid": "user-123",
        "created_at": "2023-01-01T00:00:00.000000Z",
        "updated_at": "2023-01-01T00:00:00.000000Z"
    },
    "message": "Conversation created successfully"
}
```

#### Get a conversation

```
GET /api/conversations/{uuid}
```

Response:
```json
{
    "data": {
        "uuid": "123e4567-e89b-12d3-a456-426614174000",
        "owner_uuid": "user-123",
        "created_at": "2023-01-01T00:00:00.000000Z",
        "updated_at": "2023-01-01T00:00:00.000000Z",
        "users": [
            {
                "uuid": "user-123",
                "name": "John Doe"
            },
            {
                "uuid": "user-456",
                "name": "Jane Smith"
            }
        ],
        "relations": [
            {
                "type": "App\\Models\\Project",
                "id": 1
            }
        ]
    }
}
```

#### Delete a conversation

```
DELETE /api/conversations/{uuid}
```

Response:
```json
{
    "message": "Conversation deleted successfully"
}
```

### Messages

#### Get all messages in a conversation

```
GET /api/conversations/{uuid}/messages
```

Query parameters:
- `order` (optional): Order of messages, `asc` (default) or `desc`
- `limit` (optional): Limit the number of messages
- `start` (optional): Offset for pagination

Response:
```json
{
    "data": [
        {
            "message_id": 1,
            "content": "Hello, how are you?",
            "status": 2,
            "created_at": "2023-01-01T00:00:00.000000Z",
            "user_id": "user-123"
        }
    ]
}
```

#### Create a message in a conversation

```
POST /api/conversations/{uuid}/messages
```

Request body:
```json
{
    "content": "Hello, how are you?"
}
```

Response:
```json
{
    "data": {
        "id": 1,
        "conversation_uuid": "123e4567-e89b-12d3-a456-426614174000",
        "user_id": "user-123",
        "content": "Hello, how are you?",
        "created_at": "2023-01-01T00:00:00.000000Z",
        "updated_at": "2023-01-01T00:00:00.000000Z"
    },
    "message": "Message sent successfully"
}
```

#### Get unread messages in a conversation

```
GET /api/conversations/{uuid}/messages/unread
```

Query parameters:
- `order` (optional): Order of messages, `asc` (default) or `desc`
- `limit` (optional): Limit the number of messages
- `start` (optional): Offset for pagination

Response:
```json
{
    "data": [
        {
            "msg_id": 1,
            "content": "Hello, how are you?",
            "status": 1,
            "created_at": "2023-01-01T00:00:00.000000Z",
            "user_id": "user-456"
        }
    ]
}
```

#### Mark a message as read

```
POST /api/conversations/{uuid}/messages/{messageId}/read
```

Response:
```json
{
    "message": "Message marked as read"
}
```

#### Mark a message as unread

```
POST /api/conversations/{uuid}/messages/{messageId}/unread
```

Response:
```json
{
    "message": "Message marked as unread"
}
```

#### Delete a message

```
DELETE /api/conversations/{uuid}/messages/{messageId}
```

Response:
```json
{
    "message": "Message deleted successfully"
}
```

#### Send typing indicator

```
POST /api/conversations/{uuid}/typing
```

Request body:
```json
{
    "user_name": "John Doe"
}
```

Response:
```json
{
    "message": "Typing indicator sent"
}
```

## Custom Code Execution

The API controllers include hooks at key points to allow for custom code execution. You can register callbacks for these hooks in the `config/conversations.php` file or using the `ConversationsHooks` facade.

### Available Hooks in API Controllers

- `after_get_conversations`: Executed after retrieving conversations
- `after_get_conversation`: Executed after retrieving a conversation
- `after_get_messages`: Executed after retrieving messages

### Example

```php
// In a service provider
use Dominservice\Conversations\Facade\ConversationsHooks;

public function boot()
{
    ConversationsHooks::register('after_get_conversations', function ($data) {
        // Modify the conversations data
        foreach ($data['conversations'] as $conversation) {
            $conversation->custom_attribute = 'custom value';
        }
    });
}
```

Or in the config file:

```php
'hooks' => [
    'after_get_conversations' => [
        function ($data) {
            // Modify the conversations data
            foreach ($data['conversations'] as $conversation) {
                $conversation->custom_attribute = 'custom value';
            }
        },
    ],
],
```

## Authentication

The API uses Laravel's authentication system. By default, it uses the `api` and `auth:api` middleware, but you can configure this in the `config/conversations.php` file.

## Error Handling

The API returns appropriate HTTP status codes and error messages for different scenarios:

- `404 Not Found`: When a conversation or message is not found
- `403 Forbidden`: When a user is not authorized to access a conversation or message
- `422 Unprocessable Entity`: When validation fails or an operation fails