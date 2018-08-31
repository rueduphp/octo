<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Artisan;

class Cron extends Command
{
    /**
     * @var string
     */
    protected $signature = 'octo:cron';

    /**
     * @var string
     */
    protected $description = 'Execute all cron tasks';

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return mixed
     */
    public function handle()
    {
        $this->info('xoll');

        return 4;
    }
}
