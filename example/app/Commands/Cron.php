<?php
namespace App\Commands;

use Octo\Cli;

class Cron
{
    public function cron_run($table)
    {
        Cli::show("Start cron work");
        Cli::show("End cron work");
    }
}
