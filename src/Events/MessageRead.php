<?php

namespace Dominservice\Conversations\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Dominservice\Conversations\Models\Eloquent\ConversationMessageStatus;

class MessageRead implements ShouldBroadcast
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
     * The user ID who read the message.
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
        return 'message.read';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        // Get the user who read the message
        $userModel = config('conversations.user_model');
        $user = $userModel::find($this->userId);

        // Get all users who have read this message
        $readBy = app('conversations')->getMessageReadBy($this->messageId);

        return [
            'message_id' => $this->messageId,
            'conversation_uuid' => $this->conversationUuid,
            'user_id' => $this->userId,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'read_at' => now()->toIso8601String(),
            'read_by' => $readBy,
            'read_count' => $readBy->count(),
        ];
    }
}
