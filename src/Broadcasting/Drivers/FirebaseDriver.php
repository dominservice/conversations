<?php

namespace Dominservice\Conversations\Broadcasting\Drivers;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;

class FirebaseDriver implements DriverInterface
{
    /**
     * The Firebase configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * The Firebase database instance.
     *
     * @var \Kreait\Firebase\Database|null
     */
    protected $database;

    /**
     * Create a new Firebase driver instance.
     *
     * @param  array  $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
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
        $eventName = $event->broadcastAs() ?: get_class($event);

        foreach ($channels as $channel) {
            $channelName = $this->formatChannelName($channel);
            $reference = $this->getDatabase()->getReference("channels/{$channelName}/events");
            
            // Add event to the channel's events list
            $newEvent = $reference->push([
                'name' => $eventName,
                'data' => $payload,
                'time' => time() * 1000 // milliseconds
            ]);
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
        $name = $channel->name;
        
        // Replace characters that are not allowed in Firebase paths
        $name = str_replace(['.', '#', '$', '[', ']', '/'], '_', $name);
        
        if ($channel instanceof PrivateChannel) {
            return 'private_' . $name;
        }

        if ($channel instanceof PresenceChannel) {
            return 'presence_' . $name;
        }

        return $name;
    }

    /**
     * Get the Firebase database instance.
     *
     * @return \Kreait\Firebase\Database
     */
    protected function getDatabase()
    {
        if ($this->database === null) {
            $factory = (new Factory)
                ->withServiceAccount($this->config['credentials_file'])
                ->withDatabaseUri($this->config['database_url']);
            
            $this->database = $factory->createDatabase();
        }

        return $this->database;
    }
}