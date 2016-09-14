<?php
    namespace Octo;

    class Garbage
    {
        public static function clean($directory, $days = 30, $verbose = false)
        {
            $allDropped = true;

            $dir = opendir($directory);

            if (!$dir) {
                if ($verbose) {
                    echo "! Unable to open $directory\n";
                }

                return false;
            }

            $age = strtotime("-{$days} DAY");

            while ($file = readdir($dir)) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $fullName = $directory . DS . $file;

                if (is_dir($fullName)) {
                    if (static::clean($fullName, $days, $verbose)) {
                        self::drop($fullName, $verbose);
                    } else {
                        $allDropped = false;
                    }
                } else {
                    if (filemtime($fullName) < $age) {
                        self::drop($fullName, $verbose);
                    } else {
                        $allDropped = false;
                    }
                }
            }

            closedir($dir);

            return $allDropped;
        }

        public static function drop($file, $verbose = false)
        {
            if (is_dir($file)) {
                @rmdir($file);
            } else {
                File::delete($file);
            }

            if ($verbose) {
                echo "> Dropping $file...\n";
            }
        }
    }
