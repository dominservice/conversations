<?php

namespace Dominservice\Conversations\Hooks;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class HookManager
{
    /**
     * Execute hooks for a given hook point.
     *
     * @param  string  $hookPoint
     * @param  array  $parameters
     * @return mixed
     */
    public static function execute(string $hookPoint, array $parameters = [])
    {
        $hooks = Config::get('conversations.hooks.' . $hookPoint, []);
        $result = null;

        foreach ($hooks as $hook) {
            if (is_callable($hook)) {
                // If the hook is a closure, execute it directly
                $result = call_user_func_array($hook, $parameters);
            } elseif (is_string($hook)) {
                // If the hook is a string, it's a class@method reference
                $result = static::executeClassMethod($hook, $parameters);
            }
        }

        return $result;
    }

    /**
     * Execute a class method hook.
     *
     * @param  string  $hook
     * @param  array  $parameters
     * @return mixed
     */
    protected static function executeClassMethod(string $hook, array $parameters = [])
    {
        [$class, $method] = Arr::pad(explode('@', $hook, 2), 2, 'handle');

        if (! class_exists($class)) {
            return null;
        }

        $instance = App::make($class);

        return call_user_func_array([$instance, $method], $parameters);
    }

    /**
     * Register a hook for a given hook point.
     *
     * @param  string  $hookPoint
     * @param  callable|string  $callback
     * @return void
     */
    public static function register(string $hookPoint, $callback): void
    {
        $hooks = Config::get('conversations.hooks.' . $hookPoint, []);
        $hooks[] = $callback;
        
        Config::set('conversations.hooks.' . $hookPoint, $hooks);
    }

    /**
     * Register multiple hooks for a given hook point.
     *
     * @param  string  $hookPoint
     * @param  array  $callbacks
     * @return void
     */
    public static function registerMany(string $hookPoint, array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            static::register($hookPoint, $callback);
        }
    }

    /**
     * Clear all hooks for a given hook point.
     *
     * @param  string  $hookPoint
     * @return void
     */
    public static function clear(string $hookPoint): void
    {
        Config::set('conversations.hooks.' . $hookPoint, []);
    }

    /**
     * Clear all hooks.
     *
     * @return void
     */
    public static function clearAll(): void
    {
        Config::set('conversations.hooks', []);
    }
}