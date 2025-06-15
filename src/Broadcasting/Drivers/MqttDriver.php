<?php

namespace Dominservice\Conversations\Broadcasting\Drivers;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttDriver implements DriverInterface
{
    /**
     * The MQTT configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * The MQTT client instance.
     *
     * @var \PhpMqtt\Client\MqttClient|null
     */
    protected $client;

    /**
     * Create a new MQTT driver instance.
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
        $client = $this->getClient();

        try {
            foreach ($channels as $channel) {
                $channelName = $this->formatChannelName($channel);
                $topic = "conversations/{$channelName}/{$eventName}";
                
                // Publish the event to the MQTT topic
                $client->publish(
                    $topic,
                    json_encode($payload),
                    0, // QoS level
                    false // Retain flag
                );
            }
        } finally {
            $client->disconnect();
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
            return 'private/' . $name;
        }

        if ($channel instanceof PresenceChannel) {
            return 'presence/' . $name;
        }

        return $name;
    }

    /**
     * Get the MQTT client instance.
     *
     * @return \PhpMqtt\Client\MqttClient
     */
    protected function getClient()
    {
        if ($this->client === null) {
            $host = $this->config['host'] ?? 'localhost';
            $port = $this->config['port'] ?? 1883;
            $clientId = $this->config['client_id'] ?? 'laravel_conversations_' . uniqid();
            
            $connectionSettings = (new ConnectionSettings)
                ->setConnectTimeout(60)
                ->setSocketTimeout(30);
            
            if (isset($this->config['username']) && isset($this->config['password'])) {
                $connectionSettings
                    ->setUsername($this->config['username'])
                    ->setPassword($this->config['password']);
            }
            
            $this->client = new MqttClient($host, $port, $clientId);
            $this->client->connect($connectionSettings);
        }

        return $this->client;
    }
}