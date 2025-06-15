<?php

namespace Dominservice\Conversations\Broadcasting;

use Illuminate\Support\Manager;
use Illuminate\Support\Arr;
use Illuminate\Broadcasting\BroadcastManager as LaravelBroadcastManager;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Foundation\Application;

class BroadcastManager extends Manager
{
    /**
     * The Laravel broadcast manager instance.
     *
     * @var \Illuminate\Broadcasting\BroadcastManager
     */
    protected $laravelBroadcastManager;

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Broadcasting\BroadcastManager  $laravelBroadcastManager
     * @return void
     */
    public function __construct(Application $app, LaravelBroadcastManager $laravelBroadcastManager)
    {
        parent::__construct($app);
        $this->laravelBroadcastManager = $laravelBroadcastManager;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['conversations.broadcasting.driver'] ?? 'null';
    }

    /**
     * Create the Pusher driver.
     *
     * @return \Dominservice\Conversations\Broadcasting\Drivers\PusherDriver
     */
    public function createPusherDriver()
    {
        $config = $this->app['config']['conversations.broadcasting.drivers.pusher'];
        
        if ($config['use_laravel_config'] ?? true) {
            $pusher = $this->laravelBroadcastManager->driver('pusher')->getPusher();
        } else {
            // Create a new Pusher instance with custom config
            $options = Arr::get($config, 'options', []);
            $pusher = new \Pusher\Pusher(
                $config['key'], $config['secret'], $config['app_id'], $options
            );
        }
        
        return new Drivers\PusherDriver($pusher);
    }

    /**
     * Create the Laravel WebSockets driver.
     *
     * @return \Dominservice\Conversations\Broadcasting\Drivers\LaravelWebSocketsDriver
     */
    public function createLaravelWebsocketsDriver()
    {
        // Laravel WebSockets uses the same Pusher client
        return $this->createPusherDriver();
    }

    /**
     * Create the Firebase driver.
     *
     * @return \Dominservice\Conversations\Broadcasting\Drivers\FirebaseDriver
     */
    public function createFirebaseDriver()
    {
        $config = $this->app['config']['conversations.broadcasting.drivers.firebase'];
        
        return new Drivers\FirebaseDriver($config);
    }

    /**
     * Create the MQTT driver.
     *
     * @return \Dominservice\Conversations\Broadcasting\Drivers\MqttDriver
     */
    public function createMqttDriver()
    {
        $config = $this->app['config']['conversations.broadcasting.drivers.mqtt'];
        
        return new Drivers\MqttDriver($config);
    }

    /**
     * Create the Socket.IO driver.
     *
     * @return \Dominservice\Conversations\Broadcasting\Drivers\SocketIoDriver
     */
    public function createSocketioDriver()
    {
        $config = $this->app['config']['conversations.broadcasting.drivers.socketio'];
        
        return new Drivers\SocketIoDriver($config);
    }

    /**
     * Create the null driver.
     *
     * @return \Dominservice\Conversations\Broadcasting\Drivers\NullDriver
     */
    public function createNullDriver()
    {
        return new Drivers\NullDriver();
    }

    /**
     * Determine if broadcasting is enabled.
     *
     * @return bool
     */
    public function enabled()
    {
        return $this->app['config']['conversations.broadcasting.enabled'] ?? false;
    }

    /**
     * Broadcast an event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function broadcast($event)
    {
        if (!$this->enabled()) {
            return;
        }

        $this->driver()->broadcast($event);
    }
}