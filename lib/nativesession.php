<?php

namespace Octo;

use Carbon\Carbon;
use Symfony\Component\Finder\Finder;
use Illuminate\Filesystem\Filesystem;

class Nativesession implements \SessionHandlerInterface
{
    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var int
     */
    protected $minutes;

    /**
     * @param Filesystem $files
     * @param $path
     * @param $minutes
     */
    public function __construct(Filesystem $files, $path, $minutes)
    {
        $this->path = $path;
        $this->files = $files;
        $this->minutes = $minutes;
    }

    /**
     * @param string $save_path
     * @param string $name
     * @return bool
     */
    public function open($save_path, $name)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * @param $sessionId
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function read($sessionId)
    {
        if ($this->files->exists($path = $this->path.'/'.$sessionId)) {
            if (filemtime($path) >= Carbon::now()->subMinutes($this->minutes)->getTimestamp()) {
                return $this->files->get($path, true);
            }
        }

        return '';
    }

    /**
     * @param $sessionId
     * @param $data
     * @return bool
     */
    public function write($sessionId, $data)
    {
        $this->files->put($this->path.'/'.$sessionId, $data, true);

        return true;
    }

    /**
     * @param $sessionId
     * @return bool
     */
    public function destroy($sessionId)
    {
        $this->files->delete($this->path.'/'.$sessionId);

        return true;
    }

    /**
     * @param $lifetime
     */
    public function gc($lifetime)
    {
        $files = Finder::create()
            ->in($this->path)
            ->files()
            ->ignoreDotFiles(true)
            ->date('<= now - '.$lifetime.' seconds');

        foreach ($files as $file) {
            $this->files->delete($file->getRealPath());
        }
    }
}
