[![Latest Version](https://img.shields.io/github/release/dominservice/conversations.svg?style=flat-square)](https://github.com/dominservice/conversations/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/dominservice/conversations.svg?style=flat-square)](https://packagist.org/packages/dominservice/conversations)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

# Conversations
This package will allow you to add a full user messaging system into your Laravel application.

### Notice
This package is for Laravel

| Version | Compatibility |
|---------|---------------|
| 1.*     | 5.6 -->  9.*  |
| 2.*     | 8.* --> 10.*  |

### IMPORTANT
This package is a continuation of the
[dominservice/laravel_chat](https://github.com/dominservice/laravel_chat) package, due to significant structural changes I decided to create a separate repository.
If you have a previous version, you can uninstall it while retaining the database contents, the new package includes a migration moving the data to the new structure.
Remember to make a backup before performing this operation!

## Installation
```
composer require dominservice/conversations
```
Or place manually in composer.json:
```
"require": {
    "dominservice/conversations": "^1.0"
}
```
Run:
```
composer update
```
Add the service provider to `config/app.php`

```php
'providers' => [
    Dominservice\Conversations\ConversationsServiceProvider::class,
],

(...)

'aliases' => [
    'Conversations' => Dominservice\Conversations\Facade\Conversations::class,
]
```
Publish config:

```
php artisan vendor:publish --provider="Dominservice\Conversations\ConversationsServiceProvider"
```
Migrate
```
php artisan migrate
```
### REMEMBER
Configure the package in the __config/conversations.php__ file 

# Testing

The package includes automated tests to ensure functionality works as expected. To run the tests:

## Requirements

- PHP with SQLite extension enabled (`php-sqlite3`)

If you don't have the SQLite extension installed, you can install it on Ubuntu/Debian with:

```bash
sudo apt-get install php-sqlite3
```

On CentOS/RHEL:

```bash
sudo yum install php-sqlite3
```

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

Alternatively, you can use the provided script:

```bash
./run-tests.sh
```

# Usage

#### __Create New Conversation:__
```php
$convs = (new Dominservice\Conversations\Conversations)->create($users, $relationType = null, $relationId = null, $content = null, $getObject = false);
```
Or short with helper
```php
$convs = conversation_create($users, $relationType = null, $relationId = null, $content = null, $getObject = false);
```
if ``` $getObject === true ``` you get conversation object "Dominservice\Conversations\Entities\Conversation" with all relations and users, else method return onlu conversation ID
#### __Add message to Conversation if exists or create:__
```php
$messageId = (new Dominservice\Conversations\Conversations)->addMessageOrCreateConversation($users, $content, $relationType = null, $relationId = null);
```
Or short with helper
```php
$messageId = conversation_add_or_create($users, $content, $relationType = null, $relationId = null);
```
#### __Add message to Conversation:__
```php
$messageId = (new Dominservice\Conversations\Conversations)->addMessage($convUuid, $content, $addUser = false);
```
Or short with helper
```php
$messageId = conversation_add_message($convUuid, $content, $addUser = false);
```
#### __Get Conversation ID between users:__
```php
$conversationId = (new Dominservice\Conversations\Conversations)->getIdBetweenUsers(array $users, $relationType = null, $relationId = null);
```
Or short with helper
```php
$conversationId = conversation_id_between($users, $relationType = null, $relationId = null);
```
#### __Check exists user in Conversation:__
```php
$existsUser = (new Dominservice\Conversations\Conversations)->existsUser($convUuid, $userId);
```
Or short with helper
```php
$existsUser = conversation_user_exists($convUuid, $userId = null);
```
On helper if userId is null, userId = \Auth::user()->id
#### __Get count all unreaded messages:__
```php
$count = (new Dominservice\Conversations\Conversations)->getUnreadCount($userId);
```
Or short with helper
```php
$count = conversation_unread_count($userId = null);
```
On helper if userId is null, userId = \Auth::user()->id
#### __Get count unreaded messages in specific conversation:__
```php
$count = (new Dominservice\Conversations\Conversations)->getConversationUnreadCount($convUuid, $userId);
```
Or short with helper
```php
$count = conversation_unread_count_per_id($convUuid, $userId = null);
```
On helper if userId is null, userId = \Auth::user()->id
#### __Delete Conversation:__
This method tes status to DELETED for all messages in conversation for selected user.
If all messages for all users has status DELETED remove permanently all values for conversation.
```php
(new Dominservice\Conversations\Conversations)->delete($convUuid, $userId);
```
Or short with helper
```php
conversation_delete($convUuid, $userId = null);
```
On helper if userId is null, userId = \Auth::user()->id

#### __Get all Conversations for specyfic user:__
```php
$conversations = (new Dominservice\Conversations\Conversations)->getConversations($userId, $relationType = null, $relationId = null);
```
This will return you a "Illuminate\Support\Collection" of "Dominservice\Conversations\Entities\Conversation" objects.
And foreach Conversation there, you will have the last message of the conversation, and the users of the conversation.
Example:
```php
foreach ( $conversations as $conv ) {
    $getNumOfUsers = $conv->getNumOfUsers();
    $users = $conv->users; /* Collection */

    /* $lastMessage Dominservice\Conversations\Entities\Message */
    $lastMessage = $conv->getLastMessage();

    $senderId = $lastMessage->sender;
    $content = $lastMessage->content;
    $status = $lastMessage->status;
}
```
Or short with helper
```php
$conversations = conversations($userId = null, $relationType = null, $relationId = null, $withUsersList = true);
```
On helper if userId is null, userId = \Auth::user()->id, and helper set array  with users adn conversations.
#### __Get messages of conversation:__

```php
$messages = (new Dominservice\Conversations\Conversations)->getMessages($convUuid, $userId, $newToOld = true, $limit = null, $start = null);
```
Or short with helper
```php
$messages = conversation_messages($convUuid, $userId = null, $newToOld = true, $limit = null, $start = null);
```
On helper if userId is null, userId = \Auth::user()->id
#### __Get unread messages of conversation:__

```php
$messages = (new Dominservice\Conversations\Conversations)->getUnreadMessages($convUuid, $userId, $newToOld = true, $limit = null, $start = null);
```
Or short with helper
```php
$messages = conversation_messages_unread($convUuid, $userId = null, $newToOld = true, $limit = null, $start = null);
```
On helper if userId is null, userId = \Auth::user()->id
#### Set status for message:
Mark messages. If `userId` is `null` then set current user id.
```php
conversation_mark_as_archived($convUuid, $msgId, $userId = null);
conversation_mark_as_deleted($convUuid, $msgId, $userId = null);
conversation_mark_as_unread($convUuid, $msgId, $userId = null);
conversation_mark_as_read($convUuid, $msgId, $userId = null);

conversation_mark_as_read_all($convUuid, $userId = null);
conversation_mark_as_unread_all($convUuid, $userId = null);
```
### Example
```php
    public function conversations() {
        $currentUser = Auth::user();
        //get the conversations
        $conversations = conversations($currentUser->id);
        //array for storing our users data, as that Conversations only provides user id's
        $users = collect();

        //gathering users
        foreach ( $conversations as $conv ) {
            $users->push($conv->users);
        }
        //making sure each user appears once
        $users = $users->unique();

        return View::make('conversations_page')
            ->with('users', $users)
            ->with('user', $currentUser)
            ->with('conversations', $conversations);
    }
```
# Credits
[tzookb/tbmsg](https://github.com/tzookb/tbmsg)
