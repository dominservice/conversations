<?php

namespace Dominservice\Conversations\Broadcasting\Drivers;

class NullDriver implements DriverInterface
{
    /**
     * Broadcast an event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function broadcast($event)
    {
        // Do nothing
    }
}