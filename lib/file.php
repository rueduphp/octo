<?php
    namespace Octo;

    use Closure;

    class File
    {
        /**
         * @param $file
         * @param null $content
         *
         * @return bool
         *
         * @throws \Exception
         */
        public static function create($file, $content = null)
        {
            static::delete($file);

            $create = @touch($file);

            if (null !== $content) {
                $fp = fopen($file, 'a');
                fwrite($fp, $content);
                fclose($fp);
            }

            umask(0000);

            chmod($file, 0777);

            return $create;
        }

        /**
         * @param $file
         * @param $data
         * @return bool|int
         */
        public static function append($file, $data)
        {
            $append = file_put_contents($file, $data, LOCK_EX | FILE_APPEND);

            umask(0000);

            chmod($file, 0777);

            return $append;
        }

        /**
         * @param $file
         * @return bool
         */
        public static function exists($file)
        {
            return file_exists($file);
        }

        /**
         * @param $file
         * @param null $default
         *
         * @return bool|mixed|null|string
         *
         * @throws \ReflectionException
         */
        public static function get($file, $default = null)
        {
            return static::exists($file) ? static::read($file) : static::value($default);
        }

        /**
         * @param $value
         * @return mixed
         * @throws \ReflectionException
         */
        public static function value($value)
        {
            return $value instanceof Closure ? gi()->makeClosure($value) : $value;
        }

        /**
         * @param string $file
         * @param string $data
         * @param int $chmod
         *
         * @return bool|int
         */
        public static function put(string $file, string $data, $chmod = 0777)
        {
            umask(0000);

            $result = file_put_contents($file, $data, LOCK_EX);

            @chmod($file, $chmod);

            return $result;
        }

        /**
         * @param string $file
         * @param string $data
         * @param int $chmod
         *
         * @return bool
         */
        public static function putWithLock(string $file, string $data, $chmod = 0777)
        {
            $fp = fopen($file, 'w');

            if (!flock($fp, LOCK_EX)) {
                exception('file', "The file '$file' can not be locked.");
            }

            $result = fwrite($fp, $data);

            flock($fp, LOCK_UN);

            fclose($fp);

            umask(0000);

            chmod($file, $chmod);

            return $result !== false;
        }

        /**
         * @param string $file
         * @param bool $deleteDir
         *
         * @return bool
         *
         * @throws \Exception
         */
        public static function delete(string $file, bool $deleteDir = false)
        {
            if (is_array($file)) {
                foreach ($file as $f) {
                    return static::delete($f);
                }
            }

            if (is_dir($file) && true === $deleteDir) {
                return static::rmdir($file);
            }

            if (true === static::exists($file)) {
                $fp = fopen($file, "w");

                if (!flock($fp, LOCK_EX)) {
                    throw new \Exception("The file '$file' can not be removed because it is locked.");
                }

                $status = @unlink($file);
                fclose($fp);

                return $status;
            }

            return false;
        }

        /**
         * @param string $file
         * @param string $target
         *
         * @return bool
         *
         * @throws \Exception
         */
        public static function move(string $file, string $target)
        {
            umask(0000);

            static::put($target, static::read($file));

            return static::delete($file);
        }

        /**
         * @param string $file
         * @param string $target
         * @param int $chmod
         *
         * @return bool
         */
        public static function copy(string $file, string $target, $chmod = 0777)
        {
            $copy = copy($file, $target);

            umask(0000);

            chmod($target, $chmod);

            return $copy;
        }

        /**
         * @param string $file
         * @return mixed
         */
        public static function extension(string $file)
        {
            return pathinfo($file, PATHINFO_EXTENSION);
        }

        /**
         * @param string $path
         * @return mixed
         */
        public static function basename(string $path)
        {
            return pathinfo($path, PATHINFO_BASENAME);
        }

        /**
         * @param string $file
         * @return string
         */
        public static function type(string $file)
        {
            return filetype($file);
        }

        /**
         * @param string $path
         *
         * @return int
         */
        public static function size(string $path)
        {
            return filesize($path);
        }

        /**
         * @param string $file
         * @param string $format
         *
         * @return false|string
         */
        public static function date(string $file, string $format = "YmDHis")
        {
            return date($format, filemtime($file));
        }

        /**
         * @param string $file
         * @return bool|int
         */
        public static function modified(string $file)
        {
            return filemtime($file);
        }

        /**
         * @param string $file
         * @return bool|int
         */
        public static function age(string $file)
        {
            return filemtime($file);
        }

        /**
         * @param string $file
         * @return bool
         */
        public static function is(string $file)
        {
            return is_file($file);
        }

        /**
         * @param string $path
         * @param int $chmod
         *
         * @return bool
         *
         * @throws Exception
         */
        public static function mkdir(string $path, $chmod = 0777)
        {
            umask(0000);

            if (is_dir($path)) {
                return true;
            }

            $tab = explode(DS, $path);

            array_pop($tab);

            $parent = implode(DS, $tab);

            if (!is_writable($parent)) {
                try {
                    chmod($parent, $chmod);
                } catch (\Exception $e) {
                    throw new Exception("You have not sufficient rights to write in $parent Please chmod 0777 $parent");
                }
            }

            try {
                mkdir($path, $chmod, true);
            } catch (\Exception $e) {
                throw new Exception("You have not sufficient rights to create $path");
            }
        }

        /**
         * @param string $source
         * @param string $destination
         * @param int $options
         *
         * @return bool
         */
        public static function mvdir(string $source, string $destination, $options = \FilesystemIterator::SKIP_DOTS)
        {
            return static::cpdir($source, $destination, true, $options);
        }

        /**
         * @param string $source
         * @param string $destination
         * @param bool $delete
         * @param int $options
         *
         * @return bool
         *
         * @throws Exception
         */
        public static function cpdir(
            string $source,
            string $destination,
            bool $delete = false,
            $options = \FilesystemIterator::SKIP_DOTS
        ) {
            umask(0000);

            if (!is_dir($source)) {
                return false;
            }

            if (!is_dir($destination)) {
                static::mkdir($destination, 0777);
            }

            $items = new \FilesystemIterator($source, $options);

            foreach ($items as $item) {
                $location = $destination . DS . $item->getBasename();

                if ($item->isDir()) {
                    $path = $item->getRealPath();

                    if (!static::cpdir($path, $location, $delete, $options)) {
                        return false;
                    }

                    if (true === $delete) {
                        @rmdir($item->getRealPath());
                    }
                } else {
                    if (!copy($item->getRealPath(), $location)) {
                        return false;
                    }

                    if (true === $delete) {
                        @unlink($item->getRealPath());
                    }
                }
            }

            unset($items);

            if ($delete) {
                @rmdir($source);
            }

            return true;
        }

        /**
         * @param string $directory
         * @param int $chmod
         *
         * @return bool
         */
        public static function chmodDir(string $directory, $chmod = 0777)
        {
            umask(0000);

            if (!is_dir($directory)) {
                return false;
            }

            $items = new \FilesystemIterator($directory);

            foreach ($items as $item) {
                if (true === $item->isDir()) {
                    static::chmodDir($item->getRealPath(), $chmod);
                } else {
                    umask(0000);
                    @chmod($item->getRealPath(), $chmod);
                }
            }
        }

        /**
         * @param string $directory
         * @param bool $preserve
         *
         * @return bool
         */
        public static function rmdir(string $directory, bool $preserve = false)
        {
            umask(0000);

            if (!is_dir($directory)) {
                return false;
            }

            $items = new \FilesystemIterator($directory);

            foreach ($items as $item) {
                if (true === $item->isDir()) {
                    static::rmdir($item->getRealPath());
                } else {
                    @unlink($item->getRealPath());
                }
            }

            unset($items);

            if (false === $preserve) {
                @rmdir($directory);
            }

            return true;
        }

        /**
         * @param string $directory
         *
         * @return bool
         */
        public static function cleandir(string $directory)
        {
            return static::rmdir($directory, true);
        }

        /**
         * @param string $directory
         * @param int $options
         *
         * @return mixed|null
         */
        public static function latest(string $directory, int $options = \FilesystemIterator::SKIP_DOTS)
        {
            $latest = null;
            $time   = 0;
            $items  = new \FilesystemIterator($directory, $options);

            foreach ($items as $item) {
                if ($item->getMTime() > $time) {
                    $latest = $item;
                    $time   = $item->getMTime();
                }
            }

            return $latest;
        }

        /**
         * @param string $path
         * @param int $waitTime
         *
         * @return bool
         */
        public static function isFileComplete(string $path, int $waitTime = 2)
        {
            // récupération de la taille du fichier
            $sizeBefore = static::size($path);

            // pause
            sleep($waitTime);

            // purge du cache mémoire PHP (car sinon filesize retourne la même valeur qu'à l'appel précédent)
            clearstatcache();

            // récupération de la taille du fichier après
            $size = static::size($path);

            return $sizeBefore === $size;
        }

        /**
         * @param string $path
         *
         * @return array
         *
         * @throws \Exception
         */
        public static function readdir(string $path): array
        {
            // initialisation variable de retour
            $ret = [];

            // on gère par sécurité la fin du path pour ajouter ou pas le /
            if ('/' != substr($path, -1)) {
                $path .= '/';
            }

            // on vérifie que $path est bien un répertoire
            if (is_dir($path)) {
                // ouverture du répertoire
                if ($dir = opendir($path)) {
                    // on parcours le répertoire
                    while (false !== ($dirElt = readdir($dir))) {
                        if ($dirElt != '.' && $dirElt != '..') {
                            if (!is_dir($path . $dirElt)) {
                                $ret[] = $path . $dirElt;
                            } else {
                                $ret[] = static::readdir($path . $dirElt);
                            }
                        }
                    }

                    // fermeture du répertoire
                    closedir($dir);
                } else {
                    throw new \Exception('error while opening ' . $path);
                }
            } else {
                throw new \Exception($path . ' is not a directory');
            }

            return Arrays::flatten($ret);
        }

        /**
         * @param string $fileLocation
         * @param int $maxSpeed
         *
         * @return bool
         */
        public static function download(string $fileLocation, $maxSpeed = 5120)
        {
            if (connection_status() != 0) {
                return false;
            }

            $tab       = explode(DS, $fileLocation);
            $fileName  = end($tab);
            $extension = static::lower(
                substr(
                    $fileName,
                    strrpos(
                        $fileName,
                        '.'
                    ) + 1
                )
            );

            /* List of File Types */
            $fileTypes['swf']  = 'application/x-shockwave-flash';
            $fileTypes['pdf']  = 'application/pdf';
            $fileTypes['exe']  = 'application/octet-stream';
            $fileTypes['zip']  = 'application/zip';
            $fileTypes['doc']  = 'application/msword';
            $fileTypes['docx'] = 'application/msword';
            $fileTypes['xls']  = 'application/vnd.ms-excel';
            $fileTypes['xlsx'] = 'application/vnd.ms-excel';
            $fileTypes['ppt']  = 'application/vnd.ms-powerpoint';
            $fileTypes['pptx'] = 'application/vnd.ms-powerpoint';
            $fileTypes['gif']  = 'image/gif';
            $fileTypes['png']  = 'image/png';
            $fileTypes['jpeg'] = 'image/jpg';
            $fileTypes['bmp']  = 'image/bmp';
            $fileTypes['jpg']  = 'image/jpg';
            $fileTypes['rar']  = 'application/rar';
            $fileTypes['ace']  = 'application/ace';

            $fileTypes['ra']  = 'audio/x-pn-realaudio';
            $fileTypes['ram'] = 'audio/x-pn-realaudio';
            $fileTypes['ogg'] = 'audio/x-pn-realaudio';

            $fileTypes['wav']  = 'video/x-msvideo';
            $fileTypes['wmv']  = 'video/x-msvideo';
            $fileTypes['avi']  = 'video/x-msvideo';
            $fileTypes['asf']  = 'video/x-msvideo';
            $fileTypes['divx'] = 'video/x-msvideo';

            $fileTypes['mp3']  = 'audio/mpeg';
            $fileTypes['mp4']  = 'video/mpeg';
            $fileTypes['mpeg'] = 'video/mpeg';
            $fileTypes['mpg']  = 'video/mpeg';
            $fileTypes['mpe']  = 'video/mpeg';
            $fileTypes['mov']  = 'video/quicktime';
            $fileTypes['swf']  = 'video/quicktime';
            $fileTypes['3gp']  = 'video/quicktime';
            $fileTypes['m4a']  = 'video/quicktime';
            $fileTypes['aac']  = 'video/quicktime';
            $fileTypes['m3u']  = 'video/quicktime';

            $contentType = isAke($fileTypes, $extension, 'application/octet-stream');

            header("Cache-Control: public");
            header("Content-Transfer-Encoding: binary\n");
            header("Content-Type: $contentType");

            $contentDisposition = 'attachment';

            if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
                $fileName = preg_replace('/\./', '%2e', $fileName, substr_count($fileName, '.') - 1);

                header("Content-Disposition: $contentDisposition;filename=\"$fileName\"");
            } else {
                header("Content-Disposition: $contentDisposition;filename=\"$fileName\"");
            }

            header("Accept-Ranges: bytes");

            $size  = filesize($fileLocation);
            $range = isAke($_SERVER, 'HTTP_RANGE', null);

            if (!is_null($range)) {
                list($a, $range) = explode("=", $range);

                $range      = str_replace($range, "-", $range);
                $size2      = $size-1;
                $new_length = $size-$range;

                header("HTTP/1.1 206 Partial Content");
                header("Content-Length: $new_length");
                header('Content-Range: bytes ' . $range . $size2 . '/' . $size);
            } else {
                $size2 = $size-1;

                header("Content-Range: bytes 0-$size2/$size");
                header("Content-Length: " . $size);
            }

            if ($size < 1) {
                die('Zero byte file! Aborting download');
            }

            $fp = fopen($fileLocation, "rb");

            fseek($fp, $range);

            while (!feof($fp) && (connection_status() == 0)) {
                set_time_limit(0);
                print(fread($fp, 1024 * $maxSpeed));
                flush();
                ob_flush();
                sleep(1);
            }

            fclose($fp);

            return connection_status() == 0 && !connection_aborted();
        }

        /**
         * @param string $file
         * @param bool $default
         * @param string $mode
         *
         * @return bool|string
         */
        public static function read(string $file, bool $default = false, string $mode = 'rb')
        {
            if (static::exists($file)) {
                $fp   = fopen($file, $mode);
                $data = fread($fp, static::size($file));

                fclose($fp);

                return $data;
            }

            return $default;
        }

        /**
         * @param string $file
         * @param int $start
         * @param int $end
         *
         * @return null|string
         */
        public static function readLines(string $file, int $start, int $end)
        {
            if (static::exists($file)) {
                $content = file($file);

                $back = [];

                for ($i = $start - 1; $i < $end; $i++) {
                    if (isset($content[$i])) array_push($back, $content[$i]);
                }

                return implode('', $back);
            }

            return null;
        }

        /**
         * @param string $defaultPath
         * @param string $pattern
         * @param int $flags
         *
         * @return array
         */
        public static function recursiveGlob(string $defaultPath = '', string $pattern = '*', int $flags = 0)
        {
            $paths = glob($defaultPath . '*', GLOB_MARK | GLOB_ONLYDIR | GLOB_NOSORT);
            $files = glob($defaultPath . $pattern, $flags);

            if ($paths === false) {
                if ($files === false) {
                    return [];
                }

                return $files;
            }

            foreach ($paths as $path) {
                $files = array_merge($files, static::recursiveGlob($path, $pattern, $flags));
            }

            return $files;
        }

        /**
         * @param string $file
         * @return bool
         */
        public static function is777(string $file)
        {
            return file_exists($file) && (int) static::getPerms($file) === 777;
        }

        /**
         * @param string $file
         * @return bool|string
         */
        public static function getPerms(string $file)
        {
            return substr(
                sprintf(
                    '%o',
                    fileperms($file)
                ),
                -3
            );
        }

        /**
         * @param string $directory
         *
         * @return \RecursiveIteratorIterator
         */
        public static function iterator(string $directory): \RecursiveIteratorIterator
        {
            return new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $directory,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
        }

        /**
         * @param string $pattern
         */
        public static function load(string $pattern)
        {
            $files = glob($pattern);

            foreach ($files as $file) {
                require_once $file;
            }
        }

        /**
         * @param string $field
         *
         * @return mixed|null|string
         */
        public static function upload(string $field)
        {
            return upload($field);
        }

        /**
         * @param string $path
         * @return string
         */
        public static function hash(string $path): string
        {
            return md5_file($path);
        }

        /**
         * @param string $path
         * @param string $data
         *
         * @return bool|int
         */
        public static function prepend(string $path, string $data)
        {
            if (static::exists($path)) {
                return static::put($path, $data . static::read($path));
            }

            return static::put($path, $data);
        }

        /**
         * @param string $path
         * @param int|null $mode
         *
         * @return bool|string
         */
        public static function chmod(string $path, ?int $mode = null)
        {
            if ($mode) {
                return chmod($path, $mode);
            }

            return substr(sprintf('%o', fileperms($path)), -4);
        }
    }
