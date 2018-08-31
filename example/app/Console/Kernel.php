<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
         $schedule->command('octo:cron')->everyMinute();
    }

    /**
     * @return void
     */
    protected function commands()
    {dd('d');
        $this->load(__DIR__ . '/Commands');

        require __DIR__ . '/routes.php';
    }
}
