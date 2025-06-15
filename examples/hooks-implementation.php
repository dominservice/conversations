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
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

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
            
            // Send the message to an external service if needed
            app(ExternalChatService::class)->sendMessage(
                $conversation->uuid,
                $senderId,
                $message->content,
                $message->created_at
            );
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

        // Hook: Before deleting a conversation - Archive important conversations
        ConversationsHooks::register('before_delete_conversation', function ($data) {
            $conversationUuid = $data['conversation_uuid'];
            $userId = $data['user_id'];
            
            // Check if this is an important conversation that should be archived instead of deleted
            $conversation = app('conversations')->get($conversationUuid);
            
            if ($conversation && $conversation->type_id === 'support') {
                // Archive support conversations instead of deleting them
                Log::info('Support conversation archived instead of deleted', [
                    'conversation_uuid' => $conversationUuid,
                    'user_id' => $userId,
                ]);
                
                // Implement your archiving logic here
                // ...
                
                return false; // Prevent deletion
            }
        });

        // Hook: After marking a message as read - Update analytics
        ConversationsHooks::register('after_mark_as_read', function ($data) {
            $conversationUuid = $data['conversation_uuid'];
            $messageId = $data['message_id'];
            $userId = $data['user_id'];
            
            // Update read analytics
            Log::info('Message marked as read', [
                'conversation_uuid' => $conversationUuid,
                'message_id' => $messageId,
                'user_id' => $userId,
                'read_at' => now(),
            ]);
        });
    }
}

// Example ContentFilterService class
/*
namespace App\Services;

class ContentFilterService
{
    protected $prohibitedWords = [
        'spam', 'offensive', 'inappropriate'
    ];
    
    public function containsProhibitedContent($content)
    {
        $content = strtolower($content);
        
        foreach ($this->prohibitedWords as $word) {
            if (str_contains($content, $word)) {
                return true;
            }
        }
        
        return false;
    }
}
*/

// Example ExternalChatService class
/*
namespace App\Services;

use Illuminate\Support\Facades\Http;

class ExternalChatService
{
    public function sendMessage($conversationId, $senderId, $content, $timestamp)
    {
        // Send the message to an external service
        Http::post('https://external-chat-service.com/api/messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'content' => $content,
            'timestamp' => $timestamp->toIso8601String(),
        ]);
    }
}
*/

// Example NewMessageNotification class
/*
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Dominservice\Conversations\Models\Eloquent\ConversationMessage;
use Dominservice\Conversations\Models\Eloquent\Conversation;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $message;
    protected $conversation;

    public function __construct(ConversationMessage $message, Conversation $conversation)
    {
        $this->message = $message;
        $this->conversation = $conversation;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $sender = $this->message->sender;
        $senderName = $sender ? $sender->name : 'Someone';

        return (new MailMessage)
            ->subject('New Message from ' . $senderName)
            ->line($senderName . ' sent you a message:')
            ->line('"' . $this->message->content . '"')
            ->action('View Conversation', url('/chat/' . $this->conversation->uuid))
            ->line('Thank you for using our application!');
    }

    public function toDatabase($notifiable)
    {
        $sender = $this->message->sender;
        $senderName = $sender ? $sender->name : 'Someone';

        return [
            'message_id' => $this->message->id,
            'conversation_uuid' => $this->conversation->uuid,
            'sender_id' => $this->message->user_id,
            'sender_name' => $senderName,
            'content' => $this->message->content,
        ];
    }
}
*/