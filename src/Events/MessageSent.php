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

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The conversation message instance.
     *
     * @var \Dominservice\Conversations\Models\Eloquent\ConversationMessage
     */
    public $message;

    /**
     * The conversation UUID.
     *
     * @var string
     */
    public $conversationUuid;

    /**
     * The sender user ID.
     *
     * @var mixed
     */
    public $senderId;

    /**
     * Create a new event instance.
     *
     * @param  \Dominservice\Conversations\Models\Eloquent\ConversationMessage  $message
     * @return void
     */
    public function __construct(ConversationMessage $message)
    {
        $this->message = $message;
        $this->conversationUuid = $message->conversation_uuid;
        $this->senderId = $message->{get_sender_key()};
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channelPrefix = config('conversations.broadcasting.channel_prefix', 'conversation');
        return new PrivateChannel("{$channelPrefix}.{$this->conversationUuid}");
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'conversation_uuid' => $this->conversationUuid,
            'sender_id' => $this->senderId,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}