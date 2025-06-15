<?php

namespace Dominservice\Conversations\Broadcasting\Drivers;

use Pusher\Pusher;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PusherDriver implements DriverInterface
{
    /**
     * The Pusher SDK instance.
     *
     * @var \Pusher\Pusher
     */
    protected $pusher;

    /**
     * Create a new Pusher driver instance.
     *
     * @param  \Pusher\Pusher  $pusher
     * @return void
     */
    public function __construct(Pusher $pusher)
    {
        $this->pusher = $pusher;
    }

    /**
     * Broadcast an event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function broadcast($event)
    {
        if (!$event instanceof ShouldBroadcast) {
            return;
        }

        $channels = $event->broadcastOn();
        
        if (!is_array($channels)) {
            $channels = [$channels];
        }

        $payload = $this->getPayload($event);

        foreach ($channels as $channel) {
            $this->pusher->trigger(
                $this->formatChannelName($channel),
                $event->broadcastAs() ?: get_class($event),
                $payload
            );
        }
    }

    /**
     * Get the payload for the event.
     *
     * @param  mixed  $event
     * @return array
     */
    protected function getPayload($event)
    {
        if (method_exists($event, 'broadcastWith')) {
            return $event->broadcastWith() ?: [];
        }

        $payload = [];
        foreach (get_object_vars($event) as $key => $value) {
            if ($key[0] !== '_' && $key !== 'socket' && $key !== 'connection') {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Format the channel name.
     *
     * @param  \Illuminate\Broadcasting\Channel  $channel
     * @return string
     */
    protected function formatChannelName($channel)
    {
        if ($channel instanceof PrivateChannel) {
            return 'private-' . $channel->name;
        }

        if ($channel instanceof PresenceChannel) {
            return 'presence-' . $channel->name;
        }

        return $channel->name;
    }
}