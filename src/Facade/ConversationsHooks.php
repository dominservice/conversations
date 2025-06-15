<?php

namespace Dominservice\Conversations\Facade;

use Illuminate\Support\Facades\Facade;
use Dominservice\Conversations\Hooks\HookManager;

/**
 * @method static mixed execute(string $hookPoint, array $parameters = [])
 * @method static void register(string $hookPoint, callable|string $callback)
 * @method static void registerMany(string $hookPoint, array $callbacks)
 * @method static void clear(string $hookPoint)
 * @method static void clearAll()
 *
 * @see \Dominservice\Conversations\Hooks\HookManager
 */
class ConversationsHooks extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return HookManager::class;
    }
}