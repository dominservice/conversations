<?php

namespace Dominservice\Conversations\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The conversation UUID.
     *
     * @var string
     */
    public $conversationUuid;

    /**
     * The user ID who is typing.
     *
     * @var mixed
     */
    public $userId;

    /**
     * The user name who is typing.
     *
     * @var string|null
     */
    public $userName;

    /**
     * Create a new event instance.
     *
     * @param  string  $conversationUuid
     * @param  mixed  $userId
     * @param  string|null  $userName
     * @return void
     */
    public function __construct(string $conversationUuid, $userId, ?string $userName = null)
    {
        $this->conversationUuid = $conversationUuid;
        $this->userId = $userId;
        $this->userName = $userName;
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
        return 'user.typing';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'conversation_uuid' => $this->conversationUuid,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}