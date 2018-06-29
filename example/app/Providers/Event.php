<?php
namespace App\Providers;

use function Octo\getEventManager;

class Event
{
    /**
     * @throws \ReflectionException
     */
    public function handler()
    {
        dic('eventer', getEventManager());
    }
}
