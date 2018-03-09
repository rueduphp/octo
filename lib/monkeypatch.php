<?php
namespace Octo;

class Monkeypatch
{
    /** @var bool  */
    private $enabled = false;

    /** @var string  */
    private $namespace;

    /** @var string  */
    private $name;

    /** @var callable  */
    private $callback;

    /** @var array  */
    private static $callbacks = [];

    /** @var array  */
    private static $instances = [];

    /**
     * @param string $namespace
     * @param string $name
     * @param callable $callback
     *
     * @throws \Exception
     */
    public function __construct(string $namespace, string $name, callable $callback)
    {
        $this->namespace    = $namespace;
        $this->name         = $name;
        $this->callback     = $callback;

        $func = $namespace . '\\' . $name;

        if (!function_exists($func)) {
            $code = 'namespace ' . $this->namespace . ' {
                function ' . $this->name . '(...$args) 
                {
                    $params = array_merge([__function__], $args);
                    
                    return \\Octo\\Monkeypatch::run(...$params);
                }
            }';

            eval($code);

            $key = sha1($func);

            static::$callbacks[$key] = $callback;
            static::$instances[$key] = $this;
        } else {
            throw new \Exception($func . ' ever exists.');
        }
    }

    /**
     * @param array ...$args
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public static function run(...$args)
    {
        $key = sha1(array_shift($args));

        $instance = isAke(static::$instances, $key, null);

        if ($instance instanceof static) {
            $enabled = $instance->isEnabled();

            if (true === $enabled) {
                $callback = $instance->getCallback();

                if (is_array($callback)) {
                    $params = array_merge($callback, $args);

                    return instanciator()->call(...$params);
                } else {
                    $params = array_merge([$callback], $args);

                    return instanciator()->makeClosure(...$params);
                }
            } else {
                $func = $instance->getName();

                return $func(...$args);
            }
        }

        return null;
    }

    /**
     * @return Monkeypatch
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * @return Monkeypatch
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * @param mixed $namespace
     *
     * @return Monkeypatch
     */
    public function setNamespace($namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * @param mixed $name
     *
     * @return Monkeypatch
     */
    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param mixed $callback
     *
     * @return Monkeypatch
     */
    public function setCallback($callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
