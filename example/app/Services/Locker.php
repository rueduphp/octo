<?php

namespace App\Services;

class Locker
{
    /** @var string */
    private $file;

    public function __construct(string $file)
    {
        if (!file_exists($file)) {
            touch($file);
        }

        $this->file = $file;
    }

    /**
     * @return void
     */
    public function getExclusiveLock($callback)
    {
        $reader = fopen($this->file, 'w');

        while (true) {
            if (flock($reader, LOCK_EX | LOCK_NB)) {
                cf($callback, $reader);

                fflush($reader);
                flock($reader, LOCK_UN);
                fclose($reader);

                break;
            }
        }
    }

    /**
     * Get shared lock
     *
     * @return void
     */
    public function getSharedLock($callback)
    {
        $reader = fopen($this->file, "r");

        while (true) {
            if (flock($reader, LOCK_SH | LOCK_NB)) {
                cf($callback, $this);

                flock($reader, LOCK_UN);
                fclose($reader);

                break;
            }
        }
    }
}
