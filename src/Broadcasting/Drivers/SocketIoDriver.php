<?php

namespace Dominservice\Conversations\Broadcasting\Drivers;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use GuzzleHttp\Client as HttpClient;

class SocketIoDriver implements DriverInterface
{
    /**
     * The Socket.IO configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * The HTTP client instance.
     *
     * @var \GuzzleHttp\Client|null
     */
    protected $httpClient;

    /**
     * Create a new Socket.IO driver instance.
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
            
            // Emit the event to the Socket.IO server
            $this->emit($channelName, $eventName, $payload);
        }
    }

    /**
     * Emit an event to the Socket.IO server.
     *
     * @param  string  $channel
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    protected function emit($channel, $event, $payload)
    {
        $server = rtrim($this->config['server'], '/');
        $endpoint = '/socket.io/emit';
        
        try {
            $this->getHttpClient()->post($server . $endpoint, [
                'json' => [
                    'channel' => $channel,
                    'event' => $event,
                    'data' => $payload,
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error but don't throw it to prevent disrupting the application
            if (function_exists('logger')) {
                logger()->error('Socket.IO broadcast error: ' . $e->getMessage());
            }
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
        
        if ($channel instanceof PrivateChannel) {
            return 'private-' . $name;
        }

        if ($channel instanceof PresenceChannel) {
            return 'presence-' . $name;
        }

        return $name;
    }

    /**
     * Get the HTTP client instance.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        if ($this->httpClient === null) {
            $this->httpClient = new HttpClient([
                'timeout' => 5,
                'connect_timeout' => 5,
            ]);
        }

        return $this->httpClient;
    }
}