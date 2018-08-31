<?php
namespace App\Traits;

use App\Models\Log;
use App\Services\Logger;
use Illuminate\Database\Eloquent\Builder;

trait Loggable
{
    /**
     * @param string $message
     * @return $this
     */
    public function logSuccess(string $message)
    {
        Log::success($this->getKeyLog(), $message);

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function logError(string $message)
    {
        Log::error($this->getKeyLog(), $message);

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function logWarning(string $message)
    {
        Log::warning($this->getKeyLog(), $message);

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function logIt(string $type, string $message)
    {
        Log::write($type, $this->getKeyLog(), $message);

        return $this;
    }

    /**
     * @param null|string $type
     * @return Builder
     */
    public function getLogs(?string $type = null): Builder
    {
        /** @var Builder $qb */
        $qb = Log::where('model', $this->getKeyLog());

        if (null !== $type) {
            $qb->where('type', $type);
        }

        return $qb;
    }

    /**
     * @return string
     */
    protected function getKeyLog(): string
    {
        $key = get_called_class();

        if ($this instanceof Logger) {
            $key = $this->getNamespace();
        }

        return str_replace('\\', '.', strtolower($key));
    }
}
