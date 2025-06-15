# Conversations Broadcasting

This package includes real-time broadcasting capabilities for conversations, allowing you to create interactive chat applications with features like typing indicators, read receipts, and instant message delivery.

## Configuration

Broadcasting is disabled by default. To enable it, set the `CONVERSATIONS_BROADCASTING_ENABLED` environment variable to `true` or update the configuration file:

```php
// config/conversations.php
return [
    // ...
    'broadcasting' => [
        'enabled' => true,
        // ...
    ],
];
```

## Available Drivers

The package supports multiple broadcasting drivers:

- **Pusher**: Uses Pusher Channels for real-time communication
- **Laravel WebSockets**: Uses the beyondcode/laravel-websockets package
- **Firebase**: Uses Firebase Realtime Database
- **MQTT**: Uses MQTT protocol for IoT and mobile applications
- **Socket.IO**: Uses Socket.IO for real-time communication
- **Null**: Disables broadcasting (default)

To select a driver, set the `CONVERSATIONS_BROADCASTING_DRIVER` environment variable or update the configuration:

```php
// config/conversations.php
return [
    // ...
    'broadcasting' => [
        'driver' => 'pusher', // or 'laravel-websockets', 'firebase', 'mqtt', 'socketio', 'null'
        // ...
    ],
];
```

## Driver-Specific Configuration

Each driver has its own configuration options. See the `config/conversations.php` file for details.

### Pusher

```php
'pusher' => [
    'use_laravel_config' => true, // Uses Laravel's pusher configuration
    'options' => [
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'encrypted' => true,
    ],
],
```

### Laravel WebSockets

```php
'laravel-websockets' => [
    'use_pusher_config' => true, // Uses the same configuration as Pusher
],
```

### Firebase

```php
'firebase' => [
    'credentials_file' => env('FIREBASE_CREDENTIALS_FILE'),
    'database_url' => env('FIREBASE_DATABASE_URL'),
],
```

### MQTT

```php
'mqtt' => [
    'host' => env('MQTT_HOST', 'localhost'),
    'port' => env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'client_id' => env('MQTT_CLIENT_ID', 'laravel_conversations'),
],
```

### Socket.IO

```php
'socketio' => [
    'server' => env('SOCKETIO_SERVER', 'http://localhost:6001'),
    'auth_endpoint' => '/broadcasting/auth',
],
```

## Broadcast Events

The package broadcasts the following events:

- **message.sent**: When a new message is sent
- **message.read**: When a message is marked as read
- **message.deleted**: When a message is deleted
- **conversation.created**: When a new conversation is created
- **user.typing**: When a user is typing

You can enable or disable specific events in the configuration:

```php
'events' => [
    'message_sent' => true,
    'message_read' => true,
    'message_deleted' => true,
    'conversation_created' => true,
    'user_typing' => true,
],
```

## Usage

### Listening for Events (JavaScript)

```javascript
// Using Laravel Echo with Pusher
window.Echo.private(`conversation.${conversationUuid}`)
    .listen('.message.sent', (e) => {
        console.log('New message:', e);
        // Update UI with new message
    })
    .listen('.message.read', (e) => {
        console.log('Message read:', e);
        // Update read status in UI
    })
    .listen('.user.typing', (e) => {
        console.log('User typing:', e);
        // Show typing indicator
    });

// Listen for new conversations
window.Echo.private(`conversation.user.${userId}`)
    .listen('.conversation.created', (e) => {
        console.log('New conversation:', e);
        // Add new conversation to UI
    });
```

### Broadcasting User Typing Event

You can broadcast when a user is typing using the `broadcastUserTyping` method:

```php
// Using the Conversations instance
$conversations = app('conversations');
$conversations->broadcastUserTyping($conversationUuid);

// Using the facade
use Dominservice\Conversations\Facade\ConversationsBroadcasting;
ConversationsBroadcasting::broadcastUserTyping($conversationUuid);
```

## Mobile Support

The broadcasting system is designed to work with both web and mobile applications:

- **Web**: Use Laravel Echo with Pusher or Laravel WebSockets
- **Mobile**: 
  - For React Native, use the Pusher JS client
  - For Flutter, use the Pusher Flutter plugin
  - For native iOS/Android, use the respective Pusher SDKs
  - Alternatively, use Firebase, MQTT, or Socket.IO which have strong mobile support

## Example: Setting Up Laravel Echo

```javascript
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true
});
```

## Example: Setting Up Firebase

For Firebase, you'll need to include the Firebase SDK in your application and configure it:

```javascript
// Web
import firebase from 'firebase/app';
import 'firebase/database';

const firebaseConfig = {
    apiKey: "YOUR_API_KEY",
    authDomain: "YOUR_AUTH_DOMAIN",
    databaseURL: "YOUR_DATABASE_URL",
    projectId: "YOUR_PROJECT_ID",
    storageBucket: "YOUR_STORAGE_BUCKET",
    messagingSenderId: "YOUR_MESSAGING_SENDER_ID",
    appId: "YOUR_APP_ID"
};

firebase.initializeApp(firebaseConfig);
const database = firebase.database();

// Listen for new messages
const conversationRef = database.ref(`channels/private_conversation.${conversationUuid}/events`);
conversationRef.on('child_added', (snapshot) => {
    const event = snapshot.val();
    if (event.name === 'message.sent') {
        console.log('New message:', event.data);
        // Update UI with new message
    }
});
```

## Dependencies

Depending on the driver you choose, you may need to install additional packages. These packages are listed as suggestions in the `composer.json` file and are not installed by default.

### Installing Driver Dependencies

You can install the required package for your chosen driver using Composer:

- **Pusher**: `composer require pusher/pusher-php-server`
- **Laravel WebSockets**: `composer require beyondcode/laravel-websockets`
- **Firebase**: `composer require kreait/firebase-php`
- **MQTT**: `composer require php-mqtt/client`
- **Socket.IO**: No additional packages required (uses Guzzle HTTP client)

### Moving Suggested Dependencies to Required Dependencies

If you want to make a driver dependency a permanent part of your project, you can move it from the "suggest" section to the "require" section in your `composer.json` file.

For example, to add Firebase as a required dependency (and remove it from suggestions):

```json
{
    "require": {
        "php": ">=8.1",
        "laravel/framework": "^9|^10|^11|^12",
        "intervention/image": "^3.0",
        "astrotomic/laravel-translatable": "^11.0",
        "kreait/firebase-php": "^6.0"
    },
    "suggest": {
        "pusher/pusher-php-server": "Required to use the Pusher broadcast driver (^7.0).",
        "beyondcode/laravel-websockets": "Required to use the Laravel WebSockets broadcast driver (^1.13).",
        "php-mqtt/client": "Required to use the MQTT broadcast driver (^1.0)."
    }
}
```

Note that in this example, we've added `kreait/firebase-php` to the `require` section and removed it from the `suggest` section.

After updating your `composer.json` file, run `composer update` to install the required package.
