<?php

namespace Dominservice\Conversations\Broadcasting\Drivers;

interface DriverInterface
{
    /**
     * Broadcast an event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function broadcast($event);
}