<?php
namespace App\Services;

class Message
{
    /**
     * @var array|string[]
     */
    protected $messages = [];

    /**
     * Add message to the queue.
     *
     * @param string $message
     * @param string $scope
     * @return $this
     */
    public function add(string $message, string $scope = 'default')
    {
        $key = md5($scope.$message);
        $item = ['message' => $message, 'scope' => $scope];

        // don't add duplicates
        if (!array_key_exists($key, $this->messages)) {
            $this->messages[$key] = $item;
        }

        return $this;
    }

    /**
     * Clear message queue.
     *
     * @param string $scope
     * @return $this
     */
    public function clear(?string $scope = null)
    {
        if ($scope === null) {
            $this->messages = [];
        } else {
            foreach ($this->messages as $key => $message) {
                if ($message['scope'] === $scope) {
                    unset($this->messages[$key]);
                }
            }
        }
        return $this;
    }

    /**
     * Fetch all messages.
     *
     * @param string $scope
     * @return array
     */
    public function all(?string $scope = null)
    {
        if ($scope === null) {
            return array_values($this->messages);
        }

        $messages = [];

        foreach ($this->messages as $message) {
            if ($message['scope'] === $scope) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Fetch and clear message queue.
     *
     * @param string $scope
     * @return array
     */
    public function fetch(?string $scope = null)
    {
        $messages = $this->all($scope);
        $this->clear($scope);

        return $messages;
    }
}
