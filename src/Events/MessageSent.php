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
     * Participant IDs in the conversation.
     *
     * @var array<int, string>
     */
    public $participantIds = [];

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
        $this->participantIds = $this->resolveParticipantIds();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channelPrefix = config('conversations.broadcasting.channel_prefix', 'conversation');
        $channels = [
            new PrivateChannel("{$channelPrefix}.{$this->conversationUuid}"),
        ];

        if ((bool) config('conversations.broadcasting.user_channel_events.message_sent', true)) {
            foreach ($this->participantIds as $participantId) {
                $channels[] = new PrivateChannel("{$channelPrefix}.user.{$participantId}");
            }
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
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $sender = $this->message->sender;

        return [
            'id' => $this->message->id,
            'conversation_uuid' => $this->conversationUuid,
            'sender_id' => $this->senderId,
            'sender_name' => (string) ($sender?->full_name ?? $sender?->name ?? $sender?->username ?? ''),
            'content' => $this->message->content,
            'message_type' => (string) ($this->message->message_type ?? 'text'),
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function resolveParticipantIds(): array
    {
        $conversation = Conversation::query()
            ->with('users')
            ->where('uuid', $this->conversationUuid)
            ->first();

        if (!$conversation) {
            return [];
        }

        return $conversation->users
            ->map(function ($user) {
                $id = $user?->{$user?->getKeyName() ?? 'id'} ?? $user?->uuid ?? $user?->id ?? null;
                return (string) ($id ?? '');
            })
            ->filter(static fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->all();
    }
}
