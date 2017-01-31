<?php
    namespace Octo;

    use Octo\Cron\CronExpression;

    class Cron
    {
        public function run($name, $when, $event, $args = [])
        {
            Timer::start();

            Cli::show("Start of execution", 'SUCCESS');

            $db     = engine('cron', 'task');
            $dbCron = $db->firstOrCreate(['name' => $name]);
            $nextDb = $dbCron->next;

            $cron   = CronExpression::factory($when);
            $next   = $cron->getNextRunDate()->format('Y-m-d-H-i-s');

            list($y, $m, $d, $h, $i, $s) = explode('-', $next, 6);

            $timestamp = mktime($h, $i, $s, $m, $d, $y);

            if ($nextDb) {
                if ($nextDb < $timestamp) {
                    Cli::show("Execution $name", 'COMMENT');

                    call_user_func_array($event, $args);

                    $dbCron->setNext($timestamp)->save();
                }
            } else {
                $dbCron->setNext($timestamp)->save();
            }

            Cli::show('Elapsed time: ' . Timer::get() . ' s.', 'INFO');
            Cli::show("End of execution", 'SUCCESS');
        }
    }
