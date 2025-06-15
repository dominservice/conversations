# Conversations Routes

This package includes a set of API routes for managing conversations and messages. You can customize these routes to fit your application's needs.

## Default Routes

By default, the package registers the following routes:

```
GET    /api/conversations                           - Get all conversations
POST   /api/conversations                           - Create a conversation
GET    /api/conversations/{uuid}                    - Get a specific conversation
DELETE /api/conversations/{uuid}                    - Delete a conversation
GET    /api/conversations/{uuid}/messages           - Get all messages in a conversation
POST   /api/conversations/{uuid}/messages           - Add a message to a conversation
GET    /api/conversations/{uuid}/messages/unread    - Get unread messages in a conversation
POST   /api/conversations/{uuid}/messages/{id}/read - Mark a message as read
POST   /api/conversations/{uuid}/messages/{id}/unread - Mark a message as unread
DELETE /api/conversations/{uuid}/messages/{id}      - Delete a message
POST   /api/conversations/{uuid}/typing             - Send typing indicator
```

## Publishing Routes

To customize the routes, you need to publish them to your application:

```bash
php artisan vendor:publish --provider="Dominservice\Conversations\ConversationsServiceProvider" --tag="routes"
```

This will copy the routes file to the `routes/conversation-api.php` file in your application.

## Customizing Routes

After publishing, you can edit the `routes/conversation-api.php` file to customize the routes. The package will use your custom routes instead of the default ones.

You can:

- Change the route URIs
- Add middleware
- Add new routes
- Remove existing routes
- Modify the controller methods

### Example: Changing the Route Prefix

```php
// routes/conversation-api.php
use Illuminate\Support\Facades\Route;
use Dominservice\Conversations\Http\Controllers\ConversationsController;
use Dominservice\Conversations\Http\Controllers\MessagesController;

$prefix = 'api/chat'; // Changed from 'api/conversations'
$middleware = config('conversations.api.middleware', ['api', 'auth:api']);

Route::group(['prefix' => $prefix, 'middleware' => $middleware], function () {
    // Routes remain the same
    Route::get('/', [ConversationsController::class, 'index']);
    // ...
});
```

### Example: Adding Custom Middleware

```php
// routes/conversation-api.php
use Illuminate\Support\Facades\Route;
use Dominservice\Conversations\Http\Controllers\ConversationsController;
use Dominservice\Conversations\Http\Controllers\MessagesController;

$prefix = config('conversations.api.prefix', 'api/conversations');
$middleware = ['api', 'auth:api', 'custom.middleware']; // Added custom middleware

Route::group(['prefix' => $prefix, 'middleware' => $middleware], function () {
    // Routes remain the same
    Route::get('/', [ConversationsController::class, 'index']);
    // ...
});
```

### Example: Adding a New Route

```php
// routes/conversation-api.php
use Illuminate\Support\Facades\Route;
use Dominservice\Conversations\Http\Controllers\ConversationsController;
use Dominservice\Conversations\Http\Controllers\MessagesController;
use App\Http\Controllers\CustomController;

$prefix = config('conversations.api.prefix', 'api/conversations');
$middleware = config('conversations.api.middleware', ['api', 'auth:api']);

Route::group(['prefix' => $prefix, 'middleware' => $middleware], function () {
    // Original routes
    Route::get('/', [ConversationsController::class, 'index']);
    // ...
    
    // New custom route
    Route::get('/stats', [CustomController::class, 'getConversationStats']);
});
```

## Disabling API Routes

If you want to disable the API routes entirely, you can set the `api.enabled` option to `false` in the `config/conversations.php` file:

```php
'api' => [
    'enabled' => false,
    // ...
],
```

## Using Your Own Controllers

If you want to use your own controllers instead of the package's controllers, you can publish the routes and update them to use your controllers:

```php
// routes/conversation-api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyConversationsController;
use App\Http\Controllers\MyMessagesController;

$prefix = config('conversations.api.prefix', 'api/conversations');
$middleware = config('conversations.api.middleware', ['api', 'auth:api']);

Route::group(['prefix' => $prefix, 'middleware' => $middleware], function () {
    // Using your own controllers
    Route::get('/', [MyConversationsController::class, 'index']);
    Route::post('/', [MyConversationsController::class, 'store']);
    // ...
});
```