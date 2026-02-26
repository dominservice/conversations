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
     * Participant IDs in the conversation.
     *
     * @var array<int, string>
     */
    public $participantIds = [];

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

        if ((bool) config('conversations.broadcasting.user_channel_events.message_read', true)) {
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
        return 'message.read';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $userModel = config('conversations.user_model');
        $user = null;
        if (is_string($userModel) && class_exists($userModel)) {
            $model = new $userModel();
            $user = $userModel::query()
                ->where($model->getKeyName(), $this->userId)
                ->first();
        }

        // Get all users who have read this message
        $readBy = app('conversations')->getMessageReadBy($this->messageId);

        return [
            'message_id' => $this->messageId,
            'conversation_uuid' => $this->conversationUuid,
            'user_id' => $this->userId,
            'user' => $user ? [
                'id' => (string) ($user->{$user->getKeyName()} ?? $user->uuid ?? $user->id ?? ''),
                'uuid' => (string) ($user->uuid ?? ''),
                'name' => (string) ($user->name ?? $user->full_name ?? $user->username ?? ''),
                'full_name' => (string) ($user->full_name ?? $user->name ?? ''),
                'username' => (string) ($user->username ?? ''),
                'email' => (string) ($user->email ?? ''),
                'avatar_path' => (string) ($user->avatar_path ?? ''),
            ] : null,
            'read_at' => now()->toIso8601String(),
            'read_by' => $readBy->values()->all(),
            'read_count' => $readBy->count(),
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
