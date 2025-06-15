<?php

namespace Dominservice\Conversations\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void broadcast($event)
 * @method static bool enabled()
 * @method static void broadcastUserTyping(string $conversationUuid, $userId = null, ?string $userName = null)
 *
 * @see \Dominservice\Conversations\Broadcasting\BroadcastManager
 * @see \Dominservice\Conversations\Conversations
 */
class ConversationsBroadcasting extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'conversations.broadcasting';
    }
}