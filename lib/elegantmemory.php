<?php
namespace Octo;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use PDO;
/**
 * @method static \Illuminate\Database\Eloquent\Builder schema()
 */
class Elegantmemory extends EloquentModel implements FastModelInterface
{
    protected $guarded  = [];
    protected $__capsule;

    /**
     * @param string $m
     * @param array $a
     * @return mixed
     * @throws \ReflectionException
     */
    public static function __callStatic($m, $a)
    {
        if ('schema' === $m) {
            $m = '__schema';
        }

        $callable = [instanciator()->singleton(get_called_class()), $m];

        $params = array_merge($callable, $a);

        return instanciator()->call(...$params);
    }

    /**
     * @param string $m
     * @param array $a
     *
     * @return \Illuminate\Database\Schema\Builder|mixed|null
     *
     * @throws \ReflectionException
     */
    public function __call($m, $a)
    {
        $class = get_called_class();

        if (!has('capsule.lite.schema')) {
            $PDOoptions = [
                PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
                PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
                PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES    => false,
                PDO::ATTR_EMULATE_PREPARES     => false
            ];

            $pdo = new PDO('sqlite::memory:', null, null, $PDOoptions);
            $this->__capsule = (new Capsule($pdo))->make($class);
            set('capsule.lite.schema', $this->__capsule);
        } else {
            $this->__capsule = get('capsule.lite.schema');
        }

        if ('__schema' === $m) {
            return $this->__capsule;
        }

        if (in_array($m, ['increment', 'decrement'])) {
            return $this->$m(...$a);
        }

        $callable = [$this->newQuery(), $m];

        $params = array_merge($callable, $a);

        return instanciator()->call(...$params);
    }

    /**
     * @return FastFactory
     * @throws \ReflectionException
     */
    public static function factory()
    {
        $class = get_called_class();

        return new FastFactory($class, instanciator()->singleton($class));
    }
}
