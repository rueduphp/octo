<?php

namespace Octo;

class Flasher
{
    /**
     * @var Live
     */
    protected $session;

    /**
     * @var Collection
     */
    public $messages;

    /**
     * @var string
     */
    protected $sessionKey = 'flash_notification';

    protected $old;

    /**
     * @param FastSessionInterface $session
     */
    function __construct(FastSessionInterface $session)
    {
        $this->session = $session;
        $this->messages = coll();
    }

    /**
     * @param null $message
     * @return Flasher
     * @throws Exception
     */
    public function info($message = null)
    {
        return $this->message($message, 'info');
    }

    /**
     * @param null $message
     * @return Flasher
     * @throws Exception
     */
    public function success($message = null)
    {
        return $this->message($message, 'success');
    }

    /**
     * @param null $message
     * @return Flasher
     * @throws Exception
     */
    public function error($message = null)
    {
        return $this->message($message, 'danger');
    }

    /**
     * @param null $message
     * @return Flasher
     * @throws Exception
     */
    public function warning($message = null)
    {
        return $this->message($message, 'warning');
    }

    /**
     * @param null $message
     * @param null $level
     * @return Flasher
     * @throws Exception
     */
    public function message($message = null, $level = null)
    {
        if (!$message) {
            return $this->updateLastMessage(compact('level'));
        }

        if (!$message instanceof Ghost) {
            $message = make(compact('message', 'level'));
        }

        $this->messages->push($message);

        return $this->flash();
    }

    /**
     * @param  array $overrides
     * @return $this
     */
    protected function updateLastMessage($overrides = [])
    {
        $last = $this->get()->last();

        foreach ($overrides as $key => $value) {
            $setter = setter($key);
            $last->{$setter}($value);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function important()
    {
        return $this->updateLastMessage(['important' => true]);
    }

    /**
     * @param array $data
     * @return Flasher
     */
    public function qualify(array $data): self
    {
        return $this->updateLastMessage($data);
    }

    /**
     * @return $this
     */
    public function clear()
    {
        $this->messages = coll();

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function flash()
    {
        $this->session->set($this->sessionKey, $this->messages);

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function keep()
    {
        if (null !== $this->old) {
            $this->session->set($this->sessionKey, $this->old);
        }

        return $this;
    }

    /**
     * @return FastSessionInterface
     */
    public function getSession(): FastSessionInterface
    {
        return $this->session;
    }

    /**
     * @return Collection
     */
    public function get(): Collection
    {
        return $this->session->get($this->sessionKey, coll());
    }

    /**
     * @param null|string $type
     * @return bool
     * @throws Exception
     */
    public function has(?string $type = null): bool
    {
        return $this->count($type) > 0;
    }

    /**
     * @param null|string $type
     * @return bool
     * @throws Exception
     */
    public function empty(?string $type = null): bool
    {
        return $this->count($type) === 0;
    }

    /**
     * @param null|string $type
     * @return int
     * @throws Exception
     */
    public function count(?string $type = null): int
    {
        if (null === $type) {
            return $this->get()->count();
        }

        if (in_array($type, get_class_methods($this))) {
            return count($this->{$type}());
        }

        return count($this->getByLevel($type));
    }

    /**
     * @return Collection
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    /**
     * @param string $level
     * @return array
     * @throws Exception
     */
    public function getByLevel(string $level)
    {
        $rows = [];

        if (null === $this->old) {
            $this->old = $this->get();
            $this->clear();
            $this->flash();
        }

        if ($this->old->count() > 0) {
            /** @var Ghost $message */
            foreach ($this->old as $key => $message) {
                $row = $message->toArray();

                if ($row['level'] === $level) {
                    unset($row['level']);

                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function errors()
    {
        return $this->getByLevel('danger');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function successes()
    {
        return $this->getByLevel('success');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function warnings()
    {
        return $this->getByLevel('warning');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function infos()
    {
        return $this->getByLevel('info');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function all()
    {
        $all = [];

        if (null === $this->old) {
            $this->old = $this->get();
            $this->clear();
            $this->flash();
        }

        if ($this->old->count() > 0) {
            /** @var Ghost $row */
            foreach ($this->old as $key => $row) {
                $row = $row->toArray();
                $all[] = $row;
            }
        }

        return $all;
    }

    /**
     * @return string
     */
    public function getSessionKey(): string
    {
        return $this->sessionKey;
    }

    /**
     * @param string $sessionKey
     * @return Flasher
     */
    public function setSessionKey(string $sessionKey): Flasher
    {
        $this->sessionKey = $sessionKey;

        return $this;
    }

    /**
     * @param string $name
     * @param array $parameters
     * @return array|Flasher
     * @throws Exception
     */
    public function __call(string $name, array $parameters)
    {
        if (fnmatch('*s', $name)) {
            return $this->getByLevel(substr($name, 0, -1));
        }

        return $this->message(current($parameters), $name);
    }
}
