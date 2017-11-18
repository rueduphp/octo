<?php
    namespace Octo;

    use Illuminate\Database\Connection;
    use Illuminate\Database\ConnectionResolver;
    use Illuminate\Database\Eloquent\Builder as Builderer;
    use Illuminate\Database\Query\Builder;
    use Illuminate\Database\Query\Grammars\MySqlGrammar as MySqlQueryGrammar;
    use Illuminate\Database\Query\Grammars\SQLiteGrammar as SQLiteQueryGrammar;
    use Illuminate\Database\Schema\Grammars\MySqlGrammar;
    use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
    use PDO;
    use PDOException;

    class Orm implements FastOrmInterface
    {
        /**
         * @var PDO
         */
        protected $pdo;
        protected $table;
        protected $wheres       = [];
        protected $columns      = [];
        protected $values       = [];
        protected $prepares     = [];
        protected $orders       = [];
        protected $joins        = [];
        protected $groups       = [];
        protected $havings      = [];
        protected $limit        = null;
        protected $offset       = null;
        protected $query        = null;
        protected $hook         = null;

        public function __construct($pdo = null)
        {
            if (!$pdo instanceof PDO) {
                $this->connect();
            } else {
                $this->setPdo($pdo);
            }

            orm($this);
        }

        protected function connect()
        {
            if ($pdo = context('app')->pdo) {
                if ($pdo instanceof PDO) {
                    return $this->setPdo($pdo);
                }
            } else {
                if (!$this->pdo instanceof PDO) {
                    $config = $this->config();

                    if (!empty($config)) {
                        list($dsn, $user, $pwd, $PDOoptions) = $config;

                        $pdo = maker(PDO::class, [$dsn, $user, $pwd, $PDOoptions]);

                        return $this->setPdo($pdo);
                    } else {
                        exception('orm', 'Please provide a valid configuration to continue.');
                    }
                }
            }
        }

        /**
         * @return array
         */
        protected function config(): array
        {
            $PDOoptions = [
                PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
                PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
                PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES    => false,
                PDO::ATTR_EMULATE_PREPARES     => false
            ];

            $dsns = [
                'mysql' => 'mysql:host=##host##;port=##port##;dbname=##database##',
                'sqlite' => 'sqlite:##path##'
            ];

            $database = path('app') . '/config/database.php';

            $driver = conf('DATABASE_DRIVER');

            if (!File::exists($database) && $driver) {
                $dsn = isAke($dsns, $driver, null);

                if ($dsn) {
                    switch ($driver) {
                        case 'mysql':
                            $host   = conf('DATABASE_HOST');
                            $port   = conf('DATABASE_PORT');
                            $db     = conf('DATABASE_NAME');
                            $user   = conf('DATABASE_USER');
                            $pwd    = conf('DATABASE_PASSWORD');

                            $dsn = str_replace(
                                ['##host##', '##port##', '##database##'],
                                [$host, $port, $db],
                                $dsn
                            );

                            break;

                        case 'sqlite':
                            $path   = conf('DATABASE_PATH');

                            $dsn = str_replace(
                                '##path##',
                                $path,
                                $dsn
                            );

                            $user   = null;
                            $pwd    = null;

                            break;

                    }
                }

                return [$dsn, $user, $pwd, $PDOoptions];
            }

            if (File::exists($database) && $driver) {
                $confDbs = include $database;

                $confDb = isAke($confDbs, $driver, []);
                $dsn    = isAke($dsns, $driver, null);

                if ($dsn) {
                    switch ($driver) {
                        case 'mysql':
                            $host   = isAke($confDb, 'host', 'localhost');
                            $port   = isAke($confDb, 'port', 3306);
                            $db     = isAke($confDb, 'database', 'Octo');
                            $user   = isAke($confDb, 'user', 'root');
                            $pwd    = isAke($confDb, 'password', 'root');

                            $dsn = str_replace(
                                ['##host##', '##port##', '##database##'],
                                [$host, $port, $db],
                                $dsn
                            );

                            break;

                        case 'sqlite':
                            $path   = isAke($confDb, 'database', path('app') . '/database/app.db');

                            $dsn = str_replace(
                                '##path##',
                                $path,
                                $dsn
                            );

                            $user   = null;
                            $pwd    = null;

                            break;
                    }
                }

                return [$dsn, $user, $pwd, $PDOoptions];
            }

            return [];
        }

        /**
         * @param PDO $pdo
         * @return Orm
         */
        public function setPdo(PDO $pdo)
        {
            $this->pdo = $pdo;
            $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, [$this->pdo]]);

            context('app')->pdo = $this->pdo;
            actual('pdo', $this->pdo);

            actual('orm.driver', $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

            return $this;
        }

        /**
         * @return PDO
         */
        public function getPdo()
        {
            return $this->pdo;
        }

        /**
         * @param $class
         * @return Builder
         */
        public function eloquent($class)
        {
            $this->connect();

            /** @var Ormmodel $model */
            $model = is_string($class) ? foundry($class) : $class;

            /** @var  Connection $connection */
            $connection = $this->grammar(foundry(Connection::class, $this->pdo));

            /** @var ConnectionResolver $resolver */
            $resolver   = foundry(
                ConnectionResolver::class,
                ['octoconnection' => $connection]
            );

            $resolver->setDefaultConnection('octoconnection');

            $model->setConnectionResolver($resolver);

            /** @var Builder $builder */
            $builder    = foundry(Builder::class, $connection);

            $builder->macro('all', function () use ($builder) {
                return $builder->get();
            });
            
            /** @var Builderer $eloquent */
            $eloquent   = foundry(Builderer::class, $builder);

            return $eloquent->setModel($model);
        }

        public function schema()
        {
            $this->connect();

            /** @var  Connection $connection */
            $connection = foundry(Connection::class, $this->pdo);

            $driver = actual('orm.driver');

            switch ($driver) {
                case 'mysql':
                    $connection->setSchemaGrammar(new MySqlGrammar());
                    $connection->setQueryGrammar(new MySqlQueryGrammar());
                    break;
                case 'sqlite':
                    $connection->setSchemaGrammar(new SQLiteGrammar());
                    $connection->setQueryGrammar(new SQLiteQueryGrammar());
                    break;
            }

            return $this->grammar($connection)->getSchemaBuilder();
        }

        /**
         * @param Connection $connection
         *
         * @return Connection
         */
        private function grammar(Connection $connection)
        {
            $driver = actual('orm.driver');

            switch ($driver) {
                case 'mysql':
                    $connection->setSchemaGrammar(new MySqlGrammar());
                    $connection->setQueryGrammar(new MySqlQueryGrammar());
                    break;
                case 'sqlite':
                    $connection->setSchemaGrammar(new SQLiteGrammar());
                    $connection->setQueryGrammar(new SQLiteQueryGrammar());
                    break;
            }

            return $connection;
        }

        /**
         * @return Builder
         */
        public function builder(): Builder
        {
            $this->connect();

            /** @var Connection $connection */
            $connection = $this->grammar(foundry(Connection::class, $this->pdo));

            /** @var Builder $builder */
            $builder = foundry(Builder::class, $connection);

            $builder->macro('all', function () use ($builder) {
                return $builder->get();
            });

            if (isset($this->table)) {
                return $builder->from($this->table);
            }

            $entity = $this->getEntity();

            if ($table = $entity->table()) {
                return $builder->from($table);
            }

            return $builder;
        }

        /**
         * @return Orm
         */
        public function newQuery()
        {
            return new self($this->pdo);
        }

        /**
         * @return Orm
         */
        public function fresh()
        {
            return $this->newQuery();
        }

        /**
         * @return $this
         */
        public function reset()
        {
            $this->table        = null;
            $this->hook         = null;
            $this->wheres       = [];
            $this->columns      = [];
            $this->values       = [];
            $this->prepares     = [];
            $this->orders       = [];
            $this->joins        = [];
            $this->groups       = [];
            $this->havings      = [];
            $this->limit        = null;
            $this->offset       = null;
            $this->query        = null;

            return $this;
        }

        /**
         * @param \Closure $callback
         * @param int $times
         *
         * @return mixed
         *
         * @throws \Exception
         * @throws \Throwable
         */
        public function transaction(\Closure $callback, $times = 1)
        {
            for ($t = 1; $t <= $times; $t++) {
                $this->begin();

                try {
                    $result = $callback($this);

                    $this->commit();
                } catch (\Exception $e) {
                    $this->rollBack();

                    throw $e;
                } catch (\Throwable $e) {
                    $this->rollBack();

                    throw $e;
                }

                return $result;
            }
        }

        /**
         * @return bool
         */
        public function begin()
        {
            return $this->pdo->beginTransaction();
        }

        /**
         * @return bool
         */
        public function commit()
        {
            return $this->pdo->commit();
        }

        /**
         * @return bool
         */
        public function rollBack()
        {
            return $this->pdo->rollBack();
        }

        /**
         * @param bool $reset
         * @return \PDOStatement
         */
        public function native($reset = true)
        {
            return $this->run(false, $reset);
        }

        public function queries($q = null)
        {
            if ($q) {
                Registry::set('orm.queries', $q);
            } else {
                return Registry::get('orm.queries', []);
            }
        }

        /**
         * @param bool $make
         * @param bool $reset
         * @return \PDOStatement
         */
        public function run($make = true, $reset = true)
        {
            $stmt = $this->getStatement($make);

            $stmt->execute($this->values());

            $queries = $this->queries();

            $queries[] = $this->query;

            $this->queries($queries);

            if (true === $reset) {
                $this->reset();
            }

            return $stmt;
        }

        /**
         * @param bool $make
         * @param bool $reset
         * @return \PDOStatement
         */
        public function get($make = true, $reset = true)
        {
            return $this->run($make, $reset);
        }

        /**
         * @return string
         */
        public function sql()
        {
            return $this->getStatement()->queryString;
        }

        /**
         * @param bool $make
         * @return \PDOStatement
         */
        protected function getStatement($make = true)
        {
            if ($make) {
                $this->makeQuery();
            }

            return $this->pdo->prepare($this->query);
        }

        /**
         * @return bool
         */
        public function truncate()
        {
            if (empty($this->table)) {
                exception('orm', 'No table set.');
            }

            return 1 === $this->execRaw('TRUNCATE TABLE ' . $this->table);
        }

        /**
         * @return bool
         */
        public function drop()
        {
            if (empty($this->table)) {
                exception('orm', 'No table set.');
            }

            return 1 === $this->execRaw('DROP TABLE ' . $this->table);
        }

        protected function makeQuery()
        {
            if (empty($this->table)) {
                exception('orm', 'No table set.');
            }

            if (startsWith($this->query, 'SELECT ')) {
                $this->query .= implode(' , ', $this->columns());
                $this->query .= ' FROM ' . $this->table;
                $this->query .= $this->joinClause();
                $this->query .= $this->whereClause();
                $this->query .= $this->groupByClause();
                $this->query .= $this->havingClause();
                $this->query .= $this->orderByClause();
                $this->query .= $this->limitClause();
                $this->query .= $this->offsetClause();
            } elseif (startsWith($this->query, 'INSERT INTO ')) {
                $columns = $this->columns();
                $values = $this->values();

                if (empty($columns)) {
                    exception('orm', 'Missing columns');
                }

                if (empty($values)) {
                    exception('orm', 'Missing values');
                }

                $this->prepares($values);

                $this->query .= $this->table;
                $this->query .= ' (' . implode(' , ', $columns) . ')';
                $this->query .= ' VALUES ' . $this->prepares();
            } elseif (startsWith($this->query, 'UPDATE ')) {
                $columns = $this->columns();
                $values = $this->values();

                if (empty($columns)) {
                    exception('orm', 'Missing columns');
                }

                if (empty($values)) {
                    exception('orm', 'Missing values');
                }

                $this->query .= $this->table;
                $this->query .= ' SET ' . implode(' , ', $columns);
                $this->query .= $this->whereClause();
                $this->query .= $this->orderByClause();
                $this->query .= $this->limitClause();
            } elseif (startsWith($this->query, 'DELETE FROM ')) {
                $this->query .= $this->table;
                $this->query .= $this->whereClause();
                $this->query .= $this->orderByClause();
                $this->query .= $this->limitClause();
            }
        }

        /**
         * @param string $key
         * @return int
         */
        public function count()
        {
            return $this->aggregate('count');
        }

        /**
         * @param string $key
         * @return int
         */
        public function sum($key)
        {
            return $this->aggregate('sum', $key);
        }

        /**
         * @param string $key
         * @return float
         */
        public function avg($key)
        {
            return $this->aggregate('avg', $key);
        }

        /**
         * @param string $key
         * @return int
         */
        public function min($key)
        {
            return $this->aggregate('min', $key);
        }

        /**
         * @param string $key
         * @return int
         */
        public function max($key)
        {
            return $this->aggregate('max', $key);
        }

        protected function aggregate($type, $key = '*')
        {
            if (empty($this->table)) {
                exception('orm', 'No table set.');
            }

            $type = Strings::upper($type);

            $this->query = "SELECT $type($key) AS aggregate FROM " . $this->table;
            $this->query .= $this->joinClause();
            $this->query .= $this->whereClause();
            $this->query .= $this->groupByClause();
            $this->query .= $this->havingClause();

            $row = $this->get(false)->fetch();

            return $row['aggregate'];
        }

        public function getQuery($make = true)
        {
            $old = $this->query;
            $oldP = $this->prepares;

            if ($make) {
                $this->makeQuery();
            }

            $new = $this->query;

            $this->query = $old;
            $this->prepares = $oldP;

            return $new;
        }

        public function lastId()
        {
            $this->reset();

            return $this->pdo->lastInsertId();
        }

        public function extractWhere()
        {
            $nargs  = func_num_args();
            $a = $args = func_get_args();

            $key        = array_shift($args);
            $operator   = array_shift($args);
            $value      = array_shift($args);

            if ($nargs == 1) {
                if (is_array($key)) {
                    if (count($key) == 1) {
                        $operator   = '=';
                        $value      = array_values($key);
                        $key        = array_keys($key);
                    } elseif (count($key) == 3) {
                        list($key, $operator, $value) = $key;
                    }
                }
            } elseif ($nargs == 2) {
                list($value, $operator) = [$operator, '='];
            } elseif ($nargs == 3) {
                list($key, $operator, $value) = $a;
            } else {
                exception('orm', "This method requires at least one argument to proceed.");
            }

            if ($value instanceof \Closure) {
                $value = $value($this);
            }

            return [$key, $operator, $value];
        }

        public function where()
        {
            list($key, $operator, $value) = call_user_func_array([$this, 'extractWhere'], func_get_args());

            $this->values[] = $value;

            return $this->_where($key, $operator);
        }

        public function orWhere()
        {
            if (empty($this->wheres)) {
                exception('orm', 'You must have at least one where clause before using the method or.');
            }

            list($key, $operator, $value) = call_user_func_array([$this, 'extractWhere'], func_get_args());

            $this->values[] = $value;

            return $this->_where($key, $operator, 'OR');
        }

        public function between($key, array $values)
        {
            return $this->values($values)->_between($key);
        }

        public function notBetween($key, array $values)
        {
            return $this->values($values)->_between($key, 'AND', true);
        }

        public function orBetween($key, array $values)
        {
            return $this->values($values)->_between($key, 'OR');
        }

        public function orNotBetween($key, array $values)
        {
            return $this->values($values)->_between($key, 'OR', true);
        }

        public function in($key, array $values)
        {
            return $this->values($values)
            ->prepares($values)
            ->_whereIn($key, $this->prepares());
        }

        public function notIn($key, array $values)
        {
            return $this->values($values)
            ->prepares($values)
            ->_whereIn($key, $this->prepares(), 'AND', true);
        }

        public function orIn($key, array $values)
        {
            return $this->values($values)
            ->prepares($values)
            ->_whereIn($key, $this->prepares(), 'OR');
        }

        public function orNotIn($key, array $values)
        {
            return $this->values($values)
            ->prepares($values)
            ->_whereIn($key, $this->prepares(), 'OR', true);
        }

        public function like($key, $value)
        {
            $this->values[] = $value;

            return $this->_whereLike($key);
        }

        public function notLike($key, $value)
        {
            $this->values[] = $value;

            return $this->_whereLike($key, 'AND', true);
        }

        public function orLike($key, $value)
        {
            $this->values[] = $value;

            return $this->_whereLike($key, 'OR');
        }

        public function orNotLike($key, $value)
        {
            $this->values[] = $value;

            return $this->_whereLike($key, 'OR', true);
        }

        public function isNull($key)
        {
            return $this->_whereNull($key);
        }

        public function isNotNull($key)
        {
            return $this->_whereNull($key, 'AND', true);
        }

        public function orIsNull($key)
        {
            return $this->_whereNull($key, 'OR');
        }

        public function orIsNotNull($key)
        {
            return $this->_whereNull($key, 'OR', true);
        }

        public function __invoke($concern = null)
        {
            if (!empty($concern)) {
                if (is_array($concern)) {
                    return $this->query($concern);
                } else {
                    if (reallyInt($concern)) {
                        return $this->getEntity()->find((int) $concern);
                    }
                }
            } else {
                if ($concern == []) {
                    return $this;
                }
            }

            return get_class($this->getEntity());
        }

        public function query($conditions)
        {
            $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

            foreach ($conditions as $key => $value) {
                $this->where($key, $value);
            }

            return $this;
        }

        protected function _whereNull($key, $type = 'AND', $not = false)
        {
            $verb = 'NULL';

            if ($not) {
                $verb = 'NOT NULL';
            }

            $this->wheres[] = ' ' . $type . ' ' . $key . ' IS ' . $verb;

            return $this;
        }

        protected function _whereLike($key, $type = 'AND', $not = false)
        {
            $verb = 'LIKE';

            if ($not) {
                $verb = 'NOT LIKE';
            }

            $this->wheres[] = ' ' . $type . ' ' . $key . ' ' . $verb . ' ?';

            return $this;
        }

        protected function _whereIn($key, $prepares, $type = 'AND', $not = false)
        {
            $verb = 'IN';

            if ($not) {
                $verb = 'NOT IN';
            }

            $this->wheres[] = ' ' . $type . ' ' . $key . ' ' . $verb . ' ' . $prepares;

            return $this;
        }

        protected function _between($key, $type = 'AND', $not = false)
        {
            $verb = 'BETWEEN';

            if ($not) {
                $verb = 'NOT BETWEEN';
            }

            $this->wheres[] = ' ' . $type . ' ' . $key . ' ' . $verb . ' ? AND ?';

            return $this;
        }

        protected function _where($key, $operator = '=', $type = 'AND')
        {
            $this->wheres[] = ' ' . $type . ' ' . $key . ' ' . $operator . ' ?';

            return $this;
        }

        public function whereClause()
        {
            if (empty($this->wheres)) {
                return '';
            }

            $args = [];

            foreach ($this->wheres as $where) {
                $args[] = $where;
            }

            return ' WHERE ' . ltrim(implode('', $args), ' AND');
        }

        public function raw($sql, $bind = "")
        {
            $bind = $this->cleanup($bind);

            try {
                $pdostmt = $this->pdo->prepare($sql);

                if ($pdostmt->execute($bind) !== false) {
                    return $pdostmt;
                }
            } catch (PDOException $e) {}

            return null;
        }

        protected function cleanup($bind)
        {
            if (!is_array($bind)) {
                if (!empty($bind)) $bind = [$bind];
                else $bind = [];
            }

            return $bind;
        }

        public function from($table)
        {
            $this->table = $table;

            return $this;
        }

        public function table($table)
        {
            $this->table = $table;

            return $this;
        }

        public function into($table)
        {
            $this->table = $table;

            return $this;
        }

        protected function columns($columns = null)
        {
            if (empty($columns)) {
                return $this->columns;
            }

            $this->columns = array_merge($this->columns, $columns);

            return $this;
        }

        public function values($values = null)
        {
            if (empty($values)) {
                return $this->values;
            }

            $this->values = array_merge($this->values, $values);

            return $this;
        }

        protected function prepares($values = null)
        {
            if (empty($values)) {
                $prepares = $this->prepares;

                reset($this->prepares);

                return '(' . implode(' , ', $prepares) . ')';
            }

            foreach ($values as $value) {
                $this->prepares[] = $this->prepare(
                    '?',
                    is_null($value) ? 1 : count($value)
                );
            }

            return $this;
        }

        protected function prepare($value, $count = 0, $sep = ' , ')
        {
            $render = [];

            if ($count > 0) {
                for ($p = 0; $p < $count; $p++) {
                    $render[] = $value;
                }
            }

            return implode($sep, $render);
        }

        public function getTable()
        {
            return $this->table;
        }

        public function first()
        {
            if (empty($this->columns())) {
                $this->select();
            }

            return $this->get()->fetch();
        }

        public function firstWith($table)
        {
            if (empty($this->columns())) {
                $this->select();
            }

            return $this->limit(1)->with($table)->first();
        }

        public function findMany(array $ids, $columns = ['*'])
        {
            if (is_string($columns)) {
                $columns = func_get_args();
                array_shift($columns);
            }

            return $this->select($columns)->in($this->getEntity()->pk(), $ids)->all();
        }

        public function findOrNew($id, $columns = ['*'])
        {
            if (is_string($columns)) {
                $columns = func_get_args();
                array_shift($columns);
            }

            if ($row = $this->find($id, $columns)) {
                return $row;
            }

            return $this->getEntity()->model();
        }

        public function findOrFail($id, $columns = ['*'])
        {
            if (is_string($columns)) {
                $columns = func_get_args();
                array_shift($columns);
            }

            $row = $this->find($id, $columns);

            if (!$row) {
                exception('orm', "The row $id does not exist.");
            } else {
                return $row;
            }
        }

        /**
         * @param $id
         * @return bool
         */
        public function exists($id)
        {
            return $this->find($id) !== null;
        }

        /**
         * @param $id
         * @param array $columns
         * @return mixed
         */
        public function find($id, $columns = ['*'])
        {
            if (is_string($columns)) {
                $columns = func_get_args();
                array_shift($columns);
            }

            $row = $this
            ->select($columns)
            ->where($this->getEntity()->pk(), $id)
            ->limit(1)
            ->get()
            ->fetch();

            $this->reset();

            return $row ?: null;
        }

        public function select($columns = ['*'])
        {
            if (is_string($columns)) {
                $columns = func_get_args();
            }

            if (empty($columns)) {
                $columns = ['*'];
            }

            $this->columns  = $columns;
            $this->query    = "SELECT ";

            return $this;
        }

        /**
         * @param null $table
         *
         * @return \PDOStatement
         */
        public function destroy($table = null)
        {
            return $this->delete($table)->run();
        }

        /**
         * @param null $table
         *
         * @return \PDOStatement
         */
        public function remove($table = null)
        {
            return $this->delete($table)->run();
        }

        public function delete($table = null)
        {
            if ($table) {
                $this->table = $table;
            }

            $this->query = "DELETE FROM ";

            return $this;
        }

        public function insert(array $data)
        {
            $this->query = "INSERT INTO ";

            $this->columns(array_keys($data))
            ->values(array_values($data));

            return $this;
        }

        public function edit(array $data)
        {
            return $this->update($data)->run();
        }

        /**
         * @param array $data
         * @return Orm
         */
        public function update(array $data)
        {
            $this->query = "UPDATE ";

            return $this->columnify($data);
        }

        /**
         * @param array $data
         * @return $this
         */
        protected function columnify(array $data)
        {
            foreach ($data as $column => $value) {
                $this->columns[] = $column . ' = ?';
                $this->values[] = $value;
            }

            return $this;
        }

        /**
         * @return string
         */
        protected function havingClause()
        {
            if (empty($this->havings)) {
                return '';
            }

            $args = [];

            foreach ($this->havings as $having) {
                $args[] = $having;
            }

            return ' HAVING ' . ltrim(implode('', $args), ' AND');
        }

        /**
         * @param $column
         * @param null $operator
         * @param string $chainType
         * @return $this
         */
        public function having($column, $operator = null, $chainType = 'AND')
        {
            $this->havings[] = ' ' . $chainType . ' ' . $column . ' ' . $operator . ' ?';

            return $this;
        }

        /**
         * @param $column
         * @param null $operator
         * @return Orm
         */
        public function orHaving($column, $operator = null)
        {
            return $this->having($column, $operator, 'OR');
        }

        /**
         * @param $column
         * @param null $operator
         * @return Orm
         */
        public function havingCount($column, $operator = null)
        {
            $column = 'COUNT(' . $column . ')';

            return $this->having($column, $operator);
        }

        /**
         * @param $column
         * @param null $operator
         * @return Orm
         */
        public function havingMax($column, $operator = null)
        {
            $column = 'MAX(' . $column . ')';

            return $this->having($column, $operator);
        }

        /**
         * @param $column
         * @param null $operator
         * @return Orm
         */
        public function havingMin($column, $operator = null)
        {
            $column = 'MIN(' . $column . ')';

            return $this->having($column, $operator);
        }

        /**
         * @param $column
         * @param null $operator
         * @return Orm
         */
        public function havingAvg($column, $operator = null)
        {
            $column = 'AVG(' . $column . ')';

            return $this->having($column, $operator);
        }

        /**
         * @param $column
         * @param null $operator
         * @return Orm
         */
        public function havingSum($column, $operator = null)
        {
            $column = 'SUM(' . $column . ')';

            return $this->having($column, $operator);
        }

        /**
         * @return string
         */
        protected function joinClause()
        {
            if (empty($this->joins)) {
                return '';
            }

            $args = [];

            foreach ($this->joins as $join) {
                $args[] = $join;
            }

            return implode('', $args);
        }

        /**
         * @param $table
         * @param $first
         * @param null $operator
         * @param null $second
         * @param string $joinType
         * @return $this
         */
        public function join($table, $first, $operator = null, $second = null, $joinType = 'INNER')
        {
            $this->joins[] = ' ' . $joinType . ' JOIN ' . $table . ' ON ' . $first . ' ' . $operator . ' ' . $second;

            return $this;
        }

        /**
         * @param $table
         * @param $first
         * @param $second
         * @param string $operator
         * @return Orm
         */
        public function left($table, $first, $second, $operator = '=')
        {
            return $this->join($table, $first, $operator, $second, 'LEFT');
        }

        /**
         * @param $table
         * @param $first
         * @param $second
         * @param string $operator
         * @return Orm
         */
        public function right($table, $first, $second, $operator = '=')
        {
            return $this->join($table, $first, $operator, $second, 'RIGHT');
        }

        /**
         * @param $table
         * @param $first
         * @param $second
         * @param string $operator
         * @return Orm
         */
        public function full($table, $first, $second, $operator = '=')
        {
            return $this->join($table, $first, $operator, $second, 'FULL');
        }

        /**
         * @return string
         */
        protected function groupByClause()
        {
            if (empty($this->groups)) {
                return '';
            }

            return ' GROUP BY ' . implode(' , ', $this->groups);
        }

        /**
         * @param $columns
         * @return $this
         */
        public function groupBy($columns)
        {
            $this->groups[] = $columns;

            return $this;
        }

        /**
         * @param $max
         * @param null $offset
         * @return Orm
         */
        public function take($max, $offset = null)
        {
            return $this->limit($max, $offset);
        }

        /**
         * @param $max
         * @param null $offset
         * @return $this
         */
        public function limit($max, $offset = null)
        {
            if ($offset >= 0) {
                $this->limit = intval($offset) . ' , ' . intval($max);
            } elseif ($max >= 0) {
                $this->limit = intval($max);
            }

            return $this;
        }

        /**
         * @return string
         */
        protected function limitClause()
        {
            if (is_null($this->limit)) {
                return '';
            }

            return ' LIMIT ' . $this->limit;
        }

        /**
         * @param int $offset
         * @return $this
         */
        public function offset($offset = 0)
        {
            if ($offset >= 0) {
                $offset = max(0, $offset);

                $this->offset = intval($offset);
            }

            return $this;
        }

        /**
         * @param $value
         * @return Orm
         */
        public function skip($value)
        {
            return $this->offset($value);
        }

        /**
         * @return string
         */
        protected function offsetClause()
        {
            if (is_null($this->offset)) {
                return '';
            }

            return ' OFFSET ' . $this->offset;
        }

        /**
         * @param string $column
         * @return Orm
         */
        public function latest($column = 'created_at')
        {
            return $this->orderBy($column, 'DESC');
        }

        /**
         * @param string $column
         * @return Orm
         */
        public function oldest($column = 'created_at')
        {
            return $this->orderBy($column, 'ASC');
        }

        /**
         * @param $column
         * @param string $direction
         * @return $this
         */
        public function orderBy($column, $direction = 'ASC')
        {
            $this->orders[] = $column . ' ' . Strings::upper($direction);

            return $this;
        }

        /**
         * @param $column
         * @return Orm
         */
        public function orderByDesc($column)
        {
            return $this->orderBy($column, 'DESC');
        }

        /**
         * @param $column
         * @return Orm
         */
        public function sortBy($column)
        {
            return $this->orderBy($column);
        }

        /**
         * @param $column
         * @return Orm
         */
        public function sortByDesc($column)
        {
            return $this->orderBy($column, 'DESC');
        }

        /**
         * @return string
         */
        public function now()
        {
            return date('Y-m-d H:i:s');
        }

        /**
         * @return string
         */
        protected function orderByClause()
        {
            if (empty($this->orders)) {
                return '';
            }

            return ' ORDER BY ' . implode(' , ', $this->orders);
        }

        /**
         * @param array $array
         * @return bool
         */
        protected function isPaired(array $array)
        {
            return array_keys($array) !== range(0, count($array) - 1);
        }

        /**
         * @param $field
         * @param null $value
         * @return $this|Orm
         */
        public function findBy($field, $value = null)
        {
            if (is_null($value)) {
                return $this->query($field);
            }

            if (is_array($value)) {
                return $this->in($field, $value);
            }

            return $this->where($field, $value);
        }

        /**
         * @param $field
         * @param $value
         * @return mixed
         */
        public function firstBy($field, $value)
        {
            return $this->findBy($field, $value)->first();
        }

        /**
         * @param $field
         * @param $value
         * @return Orm
         */
        public function startsWith($field, $value)
        {
            return $this->like($field, $value . '%');
        }

        /**
         * @param $field
         * @param $value
         * @return Orm
         */
        public function orStartsWith($field, $value)
        {
            return $this->orLike($field, $value . '%');
        }

        /**
         * @param $field
         * @param $value
         * @return Orm
         */
        public function endsWith($field, $value)
        {
            return $this->like($field, '%' . $value);
        }

        /**
         * @param $field
         * @param $value
         * @return Orm
         */
        public function orEndsWith($field, $value)
        {
            return $this->orLike($field, '%' . $value);
        }

        /**
         * @param $field
         * @param $value
         * @return Orm
         */
        public function lt($field, $value)
        {
            return $this->where($field, '<', $value);
        }

        /**
         * @param $field
         * @param $value
         * @return mixed
         */
        public function orLt($field, $value)
        {
            return $this->or($field, '<', $value);
        }

        /**
         * @param $field
         * @param $value
         * @return Orm
         */
        public function gt($field, $value)
        {
            return $this->where($field, '>', $value);
        }

        /**
         * @param $field
         * @param $value
         * @return mixed
         */
        public function orGt($field, $value)
        {
            return $this->or($field, '>', $value);
        }

        /**
         * @param $field
         * @param $value
         * @return Orm
         */
        public function lte($field, $value)
        {
            return $this->where($field, '<=', $value);
        }

        /**
         * @param $field
         * @param $value
         * @return mixed
         */
        public function orLte($field, $value)
        {
            return $this->or($field, '<=', $value);
        }

        /**
         * @param $field
         * @param $value
         * @return Orm
         */
        public function gte($field, $value)
        {
            return $this->where($field, '>=', $value);
        }

        /**
         * @param $field
         * @param $value
         * @return mixed
         */
        public function orGte($field, $value)
        {
            return $this->or($field, '>=', $value);
        }

        /**
         * @param $date
         *
         * @return false|string
         */
        protected function formatDate($date)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return date('Y-m-d H:i:s', $date);
        }

        /**
         * @param $date
         * @param bool $strict
         * @return Orm
         */
        public function before($date, $strict = true)
        {
            $date = $this->formatDate($date);

            return $strict ? $this->lt('created_at', $date) : $this->lte('created_at', $date);
        }

        /**
         * @param $date
         * @param bool $strict
         * @return Orm
         */
        public function orBefore($date, $strict = true)
        {
            $date = $this->formatDate($date);

            return $strict ? $this->orLt('created_at', $date) : $this->orLte('created_at', $date);
        }

        /**
         * @param $date
         * @param bool $strict
         * @return Orm
         */
        public function after($date, $strict = true)
        {
            $date = $this->formatDate($date);

            return $strict ? $this->gt('created_at', $date) : $this->gte('created_at', $date);
        }

        /**
         * @param $date
         * @param bool $strict
         * @return Orm
         */
        public function orAfter($date, $strict = true)
        {
            $date = $this->formatDate($date);

            return $strict ? $this->orGt('created_at', $date) : $this->orGte('created_at', $date);
        }

        /**
         * @param $field
         * @param $op
         * @param $date
         * @return Orm
         */
        public function when($field, $op, $date)
        {
            $date = $this->formatDate($date);

            return $this->where($field, $op, $date);
        }

        /**
         * @param $field
         * @param $op
         * @param $date
         * @return Orm
         */
        public function orWhen($field, $op, $date)
        {
            $date = $this->formatDate($date);

            return $this->or($field, $op, $date);
        }

        /**
         * @param $column
         * @return mixed
         */
        public function value($column)
        {
            $result = $this->first([$column]);

            if ($result) {
                return $result->{$column};
            }

            return null;
        }

        /**
         * @param int $page
         * @param int $perPage
         * @return Orm
         */
        public function forPage($page, $perPage = 15)
        {
            return $this->skip(($page - 1) * $perPage)->take($perPage);
        }

        /**
         * @return Orm
         */
        public function deleted()
        {
            return $this->lte('deleted_at', microtime(true));
        }

        /**
         * @return Orm
         */
        public function orDeleted()
        {
            return $this->orLte('deleted_at', microtime(true));
        }

        /**
         * @param array $attributes
         * @param array $values
         * @return mixed|Record
         */
        public function updateOrCreate(array $attributes, array $values = [])
        {
            $model = $this->firstOrNew($attributes);

            if (!arrayable($model)) {
                $model = $this->getEntity()->model($model);
            }

            $model->fill($values)->save();

            return $model;
        }

        /**
         * @param $conditions
         * @return mixed
         */
        public function firstOrCreate($conditions)
        {
            $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

            $q = $this;

            foreach ($conditions as $field => $value) {
                $q->where($field, $value);
            }

            $exists = $q->first();

            if (!$exists) {
                $this->insert($conditions)->run();

                return $this->find($this->lastId());
            }

            return $exists;
        }

        /**
         * @param $conditions
         * @return mixed|Record
         */
        public function firstOrNew($conditions)
        {
            $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

            $q = $this;

            foreach ($conditions as $field => $value) {
                $q->where($field, $value);
            }

            $exists = $q->first();

            if (!$exists) {
                return $this->getEntity()->model();
            }

            return $exists;
        }

        public function firstOrFail($conditions = null)
        {
            if ($conditions) {
                foreach ($conditions as $field => $value) {
                    $this->where($field, $value);
                }
            }

            $row = $this->first();

            if (!$row) {
                exception('orm', "The row does not exist.");
            } else {
                return $row;
            }
        }

        public function lastOrFail($conditions = null)
        {
            if ($conditions) {
                foreach ($conditions as $field => $value) {
                    $this->where($field, $value);
                }
            }

            $row = $this->last();

            if (!$row) {
                exception('orm', "The row does not exist.");
            } else {
                return $row;
            }
        }

        public function firstOr($default = false)
        {
            $row = $this->first();

            return $row ?: $default;
        }

        public function firstOrFalse()
        {
            return $this->firstOr(false);
        }

        public function firstOrNull()
        {
            return $this->firstOr(null);
        }

        public function lastOr($default = false)
        {
            $row = $this->last();

            return $row ?: $default;
        }

        public function lastOrFalse()
        {
            return $this->lastOr(false);
        }

        public function lastOrNull()
        {
            return $this->lastOr(null);
        }

        public function all($cursor = false)
        {
            return $cursor ? $this->cursor() : $this->results();
        }

        public function getHook()
        {
            return $this->hook;
        }

        public function getEntity()
        {
            $entity = actual("orm.entity.$this->table");

            if (!$entity) {
                $entity = new Entity;
                $entity->setTable($this->table);
                actual("orm.entity.{$this->table}", $entity);
            }

            return $entity;
        }

        public function model($row)
        {
            $row = arrayable($row) ? $row->toArray() : $row;

            return new Record($row, $this->getEntity());
        }

        public function cursor()
        {
            $instance   = clone $this;

            return new Ormiterator($this->select()->run(), $instance);
        }

        public function rows($model = true)
        {
            $statement  = $this->select()->run();

            while ($record = $statement->fetch()) {
                if ($model) yield $this->model($record);
                else yield $record;
            }
        }

        public function findWith($id, $table)
        {
            return $this
            ->where($this->getEntity()->pk(), $id)
            ->limit(1)
            ->with($table)
            ->first();
        }

        public function with($relations)
        {
            if (!is_array($relations)) {
                $relations = func_get_args();
            }

            $thisEntity = $this->getEntity();
            $collection = $this->collection();
            $first      = $collection->first();
            $rel        = [];
            $related    = [];
            $entities   = [];

            foreach ($relations as $relation) {
                $class               = call_user_func([$thisEntity, $relation]);
                $entity              = maker($class);
                $entities[$relation] = $entity;

                $pk = $entity->table() . '_id';

                $exists = 'octodummy' !== isAke($first, $pk, 'octodummy');

                if ($exists) {
                    $related[$relation] = 'single';
                    $ids                = $collection->pluck($pk);
                    $rel[$relation]     = $entity->in($entity->pk(), $ids)->collection();
                } else {
                    $related[$relation] = 'many';
                    $ids                = $collection->pluck($thisEntity->pk());

                    $pk = $thisEntity->table() . '_id';
                    $rel[$relation] = $entity->in($pk, $ids)->collection();
                }
            }

            $results = [];

            foreach ($collection as $row) {
                $model = $thisEntity->model($row);

                foreach ($entities as $relation => $entity) {
                    $type = $related[$relation];
                    $coll = $rel[$relation];

                    if ('single' == $type) {
                        $getter             = getter($entity->table() . '_id');
                        $record             = $coll->where($entity->pk(), $model->$getter())->first();
                        $model->$relation   = $entity->model($record);
                    } elseif ('many' == $type) {
                        $getter = getter($thisEntity->pk());
                        $rows   = $coll->where($thisEntity->table() . '_id', $model->$getter());
                        $models = [];

                        foreach ($rows as $relRow) {
                            $models[] = $entity->model($relRow);
                        }

                        $model->$relation = $this->collectionEntity($entity, $models);
                    }

                    $results[] = $model;
                }
            }

            return $this->collectionEntity($thisEntity, $results);
        }

        protected function collectionEntity($entity, $data)
        {
            $collection = dyn(coll($data));

            $collection->fn('entity', function () use ($entity) {
                return $entity;
            });

            $collection->fn('update', function (array $data) {
                return $this->edit($data);
            });

            $collection->fn('delete', function () {
                return $this->destroy();
            });

            return $collection;
        }

        public function collection($model = false, $reset = true)
        {
            $rows = $this->select()->run(true, $reset)->fetchAll();

            if ($model) {
                $rows = $this->models($rows);
            }

            return coll($rows);
        }

        public function results($make = true)
        {
            $entity = $this->getEntity();

            if (empty($this->columns())) {
                $this->select();
            }

            $stmt = $this->getStatement($make);

            $stmt->execute($this->values());

            return $this->collectionEntity($entity, $this->models($stmt->fetchAll()));
        }

        public function models(array $rows)
        {
            $collection = [];
            $entity = $this->getEntity();

            foreach ($rows as $row) {
                $collection[] = $entity->model($row);
            }

            return $collection;
        }

        public function paginate($page, $count)
        {
            return $this->limit(($page - 1) * $count, $count);
        }

        public function each(callable $callback, $count = 1000)
        {
            if (empty($this->orders)) {
                $this->orderBy($this->getEntity()->pk());
            }

            return $this->split($count, function ($rows) use ($callback) {
                foreach ($rows as $row) {
                    if ($callback($row) === false) {
                        return false;
                    }
                }
            });
        }

        public function split($count, callable $callback)
        {
            $entity = $this->getEntity();

            if (empty($this->columns())) {
                $this->select();
            }

            $stmt = $this->run();

            $continue = true;

            do {
                $size       = $count;
                $results    = [];

                while ($size > 0) {
                    $size--;
                    $row = $stmt->fetch();

                    if ($row) {
                        $results[] = $entity->model($row);
                    } else {
                        $continue = false;
                    }
                }

                if (call_user_func($callback, $results) === false) {
                    return false;
                }
            } while (true === $continue);

            return true;
        }

        public function chunk($count, callable $callback)
        {
            $entity = $this->getEntity();
            $pk     = $entity->pk();

            if (empty($this->columns())) {
                $this->select([$pk]);
            }

            $ids = coll(
                coll(
                    $this->run()
                    ->fetchAll()
                )->pluck($pk)
            )->values()
            ->toArray();

            if (!empty($ids)) {
                do {
                    $size = $count;
                    $results = [];

                    while ($size > 0) {
                        $size--;
                        $id     = array_shift($ids);
                        $row    = $this->table(
                            $entity->table()
                        )->find($id);

                        if (!$row) {
                            break;
                        }

                        $results[] = $entity->model($row);
                    }

                    if (call_user_func($callback, $results) === false) {
                        return false;
                    }
                } while (!empty($ids));
            } else {
                return false;
            }

            return true;
        }

        public function last()
        {
            $statement = $this->select()->run();

            $last = null;

            while ($record = $statement->fetch()) {
                $last = $record;
            }

            return $last ?: null;
        }

        /**
         * @param $field
         * @param null $key
         * @return array
         */
        public function pluck($field, $key = null)
        {
            return $this->collection()->pluck($field, $key);
        }

        public function __call($m, $a)
        {
            $entity     = $this->getEntity();
            $methods    = get_class_methods($entity);
            $method     = 'scope' . ucfirst(Strings::camelize($m));

            if (in_array($method, $methods)) {
                return call_user_func_array([$entity, $method], array_merge([$this], $a));
            }

            $method = 'query' . ucfirst(Strings::camelize($m));

            if (in_array($method, $methods)) {
                return call_user_func_array([$entity, $method], array_merge([$this], $a));
            }

            if ($m == 'is' && count($a) == 2) {
                return $this->where(
                    current($a),
                    end($a)
                );
            }

            if ($m == 'list') {
                return call_user_func_array([$this, 'pluck'], $a);
            }

            if ($m == 'or') {
                return call_user_func_array([$this, 'orWhere'], $a);
            }

            if ($m == 'and') {
                return call_user_func_array([$this, 'where'], $a);
            }

            if (fnmatch('findBy*', $m) && strlen($m) > 6) {
                $field = callField($m, 'findBy');

                $op = '=';

                if (count($a) == 2) {
                    $op     = array_shift($a);
                    $value  = array_shift($a);
                } else {
                    $value  = array_shift($a);
                }

                return $this->where($field, $op, $value);
            }

            if (fnmatch('getBy*', $m) && strlen($m) > 5) {
                $field = callField($m, 'getBy');

                $op = '=';

                if (count($a) == 2) {
                    $op     = array_shift($a);
                    $value  = array_shift($a);
                } else {
                    $value  = array_shift($a);
                }

                return $this->where($field, $op, $value);
            }

            if (fnmatch('like*', $m) && strlen($m) > 4) {
                $field = callField($m, 'like');

                return $this->like($field, array_shift($a));
            }

            if (fnmatch('notLike*', $m) && strlen($m) > 47) {
                $field = callField($m, 'notLike');

                return $this->notLike($field, array_shift($a));
            }

            if (fnmatch('between*', $m) && strlen($m) > 7) {
                $field = callField($m, 'between');

                return $this->between($field, current($a));
            }

            if (fnmatch('*isNull', $m) && strlen($m) > 6) {
                $field = callField($m, 'isNull');

                return $this->isNull($field);
            }

            if (fnmatch('*isNotNull', $m) && strlen($m) > 9) {
                $field = callField($m, 'isNotNull');

                return $this->isNotNull($field);
            }

            if (fnmatch('*isGreaterThan', $m) && strlen($m) > 13) {
                $field = callField($m, 'isNull');

                return $this->where($field, '>', current($a));
            }

            if (fnmatch('*isLowerThan', $m) && strlen($m) > 11) {
                $field = callField($m, 'isNull');

                return $this->where($field, '<', current($a));
            }

            if (fnmatch('where*', $m) && strlen($m) > 5) {
                $field = callField($m, 'where');

                $op = '=';

                if (count($a) == 2) {
                    $op     = array_shift($a);
                    $value  = array_shift($a);
                } else {
                    $value  = array_shift($a);
                }

                return $this->where($field, $op, $value);
            }

            if (fnmatch('by*', $m) && strlen($m) > 2) {
                $field = callField($m, 'by');
                $value = array_shift($a);

                return $this->where($field, $value);
            }

            if (fnmatch('sortWith*', $m)) {
                $field = callField($m, 'sortWith');

                return $this->sortBy($field);
            }

            if (fnmatch('sortBy*', $m)) {
                $field = callField($m, 'sortBy');

                return $this->sortBy($field);
            }

            if (fnmatch('sortWithDesc*', $m)) {
                $field = callField($m, 'sortWithDesc');

                return $this->sortByDesc($field);
            }

            if (fnmatch('sortByDesc*', $m)) {
                $field = callField($m, 'sortByDesc');

                return $this->sortByDesc($field);
            }

            return call_user_func_array([$this->builder(), $m], $a);
        }

        /**
         * @param $sql
         * @return \PDOStatement
         */
        public function queryRaw($sql)
        {
            return $this->getPdo()->query($sql);
        }

        /**
         * @param $sql
         * @return int
         */
        public function execRaw($sql)
        {
            return $this->getPdo()->exec($sql);
        }
    }
