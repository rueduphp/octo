<?php

namespace App\Services;

use App\Facades\Files;
use Exception;
use function Octo\public_path;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CachePage
{
    /**
     * @var string|null
     */
    protected $cachePath = null;

    /**
     * @param $path
     */
    public function setCachePath($path)
    {
        $this->cachePath = rtrim($path, '\/');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getCachePath()
    {
        $base = $this->cachePath ?? $this->getDefaultCachePath();

        if (is_null($base)) {
            throw new Exception('Cache path not set.');
        }

        return $this->join(array_merge([$base], func_get_args()));
    }

    /**
     * @param array $paths
     * @return string
     */
    protected function join(array $paths)
    {
        $trimmed = array_map(function ($path) {
            return trim($path, '/');
        }, $paths);

        return $this->matchRelativity(
            $paths[0], implode('/', array_filter($trimmed))
        );
    }

    /**
     * @param $source
     * @param $target
     * @return string
     */
    protected function matchRelativity($source, $target)
    {
        return $source[0] === '/' ? '/' . $target : $target;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return CachePage
     * @throws Exception
     */
    public function cacheIfNeeded(ServerRequestInterface $request, ResponseInterface $response): self
    {
        if ($this->shouldCache($request, $response)) {
            $this->cache($request, $response);
        }

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     */
    public function shouldCache(ServerRequestInterface $request, ResponseInterface $response)
    {
        return 'GET' === $request->getMethod() && $response->getStatusCode() === 200;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws Exception
     */
    public function cache(ServerRequestInterface $request, ResponseInterface $response)
    {
        list($path, $file) = $this->getDirectoryAndFileNames($request);

        Files::makeDirectory($path, 0775, true, true);

        Files::put(
            $this->join([$path, $file]),
            (string) $response->getBody(),
            true
        );
    }

    /**
     * @param $slug
     * @return mixed
     * @throws Exception
     */
    public function forget($slug)
    {
        return Files::delete($this->getCachePath($slug . '.html'));
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function clear()
    {
        return Files::deleteDirectory($this->getCachePath(), true);
    }

    /**
     * @param $request
     * @return array
     * @throws Exception
     */
    protected function getDirectoryAndFileNames($request)
    {
        $segments = explode('/', ltrim($request->getPathInfo(), '/'));

        $file = $this->aliasFilename(array_pop($segments)) . '.html';

        return [$this->getCachePath(implode('/', $segments)), $file];
    }

    /**
     * @param $filename
     * @return string
     */
    protected function aliasFilename($filename)
    {
        return $filename ?: 'pc__index__pc';
    }

    /**
     * @return string
     */
    protected function getDefaultCachePath()
    {
        return public_path() . '/cached-pages';
    }
}
