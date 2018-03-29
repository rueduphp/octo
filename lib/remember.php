<?php
namespace Octo;

use Illuminate\Filesystem\Filesystem;

class Remember
{
    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function __callStatic($name, $arguments)
    {
        $fileSystem = instanciator()->singleton(Filesystem::class);
        $path       = path('cache', null, path('app', null, session_save_path()) . '/storage/cache');

        if (!is_dir($path)) {
            File::mkdir($path);
        }

        $params     = array_merge([Store::class], [$fileSystem, $path]);
        $manager    = instanciator()->singleton(...$params);

        return $manager->{$name}(...$arguments);
    }
}
