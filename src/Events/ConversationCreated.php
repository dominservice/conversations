<?php

namespace Dominservice\Conversations\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Dominservice\Conversations\Models\Eloquent\Conversation;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The conversation instance.
     *
     * @var \Dominservice\Conversations\Models\Eloquent\Conversation
     */
    public $conversation;

    /**
     * The owner user ID.
     *
     * @var mixed
     */
    public $ownerId;

    /**
     * The participants user IDs.
     *
     * @var array
     */
    public $participantIds;

    /**
     * Create a new event instance.
     *
     * @param  \Dominservice\Conversations\Models\Eloquent\Conversation  $conversation
     * @param  array  $participantIds
     * @return void
     */
    public function __construct(Conversation $conversation, array $participantIds)
    {
        $this->conversation = $conversation;
        $this->ownerId = $conversation->owner_uuid;
        $this->participantIds = $participantIds;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        $channelPrefix = config('conversations.broadcasting.channel_prefix', 'conversation');
        
        // Create an array of private channels for each participant
        $channels = [];
        foreach ($this->participantIds as $participantId) {
            $channels[] = new PrivateChannel("{$channelPrefix}.user.{$participantId}");
        }
        
        return $channels;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'conversation.created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'conversation_uuid' => $this->conversation->uuid,
            'owner_id' => $this->ownerId,
            'participant_ids' => $this->participantIds,
            'created_at' => $this->conversation->created_at->toIso8601String(),
        ];
    }
}