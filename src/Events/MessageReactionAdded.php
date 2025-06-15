<?php

namespace Dominservice\Conversations\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The conversation UUID.
     *
     * @var string
     */
    public $conversationUuid;

    /**
     * The message ID.
     *
     * @var int
     */
    public $messageId;

    /**
     * The user ID who added the reaction.
     *
     * @var mixed
     */
    public $userId;

    /**
     * The reaction emoji.
     *
     * @var string
     */
    public $reaction;

    /**
     * Create a new event instance.
     *
     * @param  string  $conversationUuid
     * @param  int  $messageId
     * @param  mixed  $userId
     * @param  string  $reaction
     * @return void
     */
    public function __construct(string $conversationUuid, int $messageId, $userId, string $reaction)
    {
        $this->conversationUuid = $conversationUuid;
        $this->messageId = $messageId;
        $this->userId = $userId;
        $this->reaction = $reaction;
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
        return 'message.reaction.added';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        // Get the user who added the reaction
        $userModel = config('conversations.user_model');
        $user = $userModel::find($this->userId);

        // Get the reaction summary for the message
        $reactionsSummary = app('conversations')->getMessageReactionsSummary($this->messageId);

        return [
            'message_id' => $this->messageId,
            'conversation_uuid' => $this->conversationUuid,
            'user_id' => $this->userId,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'reaction' => $this->reaction,
            'created_at' => now()->toIso8601String(),
            'reactions_summary' => $reactionsSummary,
        ];
    }
}