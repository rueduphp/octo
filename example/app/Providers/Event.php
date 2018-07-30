<?php
namespace App\Providers;

use function Octo\getEventManager;

class Event
{
    /**
     * @return array
     */
    public static function subscribers()
    {
        return [];
    }

    /**
     * @throws \ReflectionException
     */
    public function handler()
    {
        dic('eventer', getEventManager());
    }
}
