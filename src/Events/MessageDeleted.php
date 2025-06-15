<?php

namespace Dominservice\Conversations\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The message ID.
     *
     * @var int
     */
    public $messageId;

    /**
     * The conversation UUID.
     *
     * @var string
     */
    public $conversationUuid;

    /**
     * The user ID who deleted the message.
     *
     * @var mixed
     */
    public $userId;

    /**
     * Create a new event instance.
     *
     * @param  string  $conversationUuid
     * @param  int  $messageId
     * @param  mixed  $userId
     * @return void
     */
    public function __construct(string $conversationUuid, int $messageId, $userId)
    {
        $this->conversationUuid = $conversationUuid;
        $this->messageId = $messageId;
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
        return new PrivateChannel("{$channelPrefix}.{$this->conversationUuid}");
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.deleted';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message_id' => $this->messageId,
            'conversation_uuid' => $this->conversationUuid,
            'user_id' => $this->userId,
            'deleted_at' => now()->toIso8601String(),
        ];
    }
}