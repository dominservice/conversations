<?php

namespace Dominservice\Conversations\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Dominservice\Conversations\Models\Eloquent\ConversationMessage;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The message that was edited.
     *
     * @var \Dominservice\Conversations\Models\Eloquent\ConversationMessage
     */
    public $message;

    /**
     * The user ID who edited the message.
     *
     * @var mixed
     */
    public $userId;

    /**
     * Create a new event instance.
     *
     * @param  \Dominservice\Conversations\Models\Eloquent\ConversationMessage  $message
     * @param  mixed  $userId
     * @return void
     */
    public function __construct(ConversationMessage $message, $userId)
    {
        $this->message = $message;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channelPrefix = config('conversations.broadcasting.channel_prefix', 'conversation');
        return new PrivateChannel("{$channelPrefix}.{$this->message->conversation_uuid}");
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.edited';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        // Get the user who edited the message
        $userModel = config('conversations.user_model');
        $user = $userModel::find($this->userId);

        return [
            'message_id' => $this->message->id,
            'conversation_uuid' => $this->message->conversation_uuid,
            'content' => $this->message->content,
            'user_id' => $this->userId,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'edited_at' => $this->message->edited_at->toIso8601String(),
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}