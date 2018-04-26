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
     * @param  string|null $message
     * @return $this
     */
    public function info($message = null)
    {
        return $this->message($message, 'info');
    }

    /**
     * @param  string|null $message
     * @return $this
     */
    public function success($message = null)
    {
        return $this->message($message, 'success');
    }

    /**
     * @param  string|null $message
     * @return $this
     */
    public function error($message = null)
    {
        return $this->message($message, 'danger');
    }

    /**
     * @param  string|null $message
     * @return $this
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
        $this->session->flash($this->sessionKey, $this->messages);

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
     */
    public function has(?string $type = null): bool
    {
        return $this->count($type) > 0;
    }

    /**
     * @param null|string $type
     * @return int
     */
    public function count(?string $type = null): int
    {
        if (null === $type) {
            return $this->get()->count();
        }

        if (in_array($type, get_class_methods($this))) {
            return count($this->{$type}(false));
        }

        return 0;
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
}
