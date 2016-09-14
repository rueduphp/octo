<?php
    namespace Octo\Mongo;

    use Countable;
    use IteratorAggregate;
    use BadMethodCallException;
    use MongoCursor;
    use MongoDBRef;

    class Cursor implements Countable, IteratorAggregate
    {
        /**
         * @var  object  $result  MongoCursor
         */
        protected $result;
        protected $db;

        /**
         * Number of times to retry queries.
         *
         * @var integer
         */
        protected $numRetries = 0;

        /**
         * Constructor, sets the result
         *
         * @param object $result     MongoCursor
         */
        public function __construct(MongoCursor $result, $db = null)
        {
            $this->result = $result;
            $this->db = $db;
        }

        /**
         * Retrieve the MongoCursor instance
         *
         * @return object MongoCursor
         */
        public function getCursor()
        {
            return $this->result;
        }

        /**
         * Wrapper method for MongoCursor::current().
         *
         * @see http://php.net/manual/en/iterator.current.php
         * @see http://php.net/manual/en/mongocursor.current.php
         * @return array|null
         */
        public function current()
        {
            $current = $this->result->current();

            if ($current instanceof \MongoGridFSFile) {
                $document           = $current->file;
                $document['file']   = new File($current);
                $current            = $document;
            }

            return $current;
        }

        /**
         * Wrapper method for MongoCursor::getNext().
         *
         * @see http://php.net/manual/en/mongocursor.getnext.php
         * @return array|null
         */
        public function getNext()
        {
            $cursor = $this;

            $next = $this->retry(function() use ($cursor) {
                return $cursor->getCursor()->getNext();
            });

            if ($next instanceof \MongoGridFSFile) {
                $document = $next->file;
                $document['file'] = new File($next);
                $next = $document;
            }

            unset($next['_id']);

            return $next;
        }

        /**
         * Implementing IteratorAggregate
         *
         * @return object MongoCursor
         */
        public function getIterator()
        {
            return $this->result;
        }

        /**
         * Countable implementation
         *
         * @return int number of documents
         */
        public function count()
        {
            return $this->result->count();
        }

        /**
         * Returns the result as an array
         *
         * @return array the iterator as array
         */
        public function toArray()
        {
            return iterator_to_array($this->result);
        }

        /**
         * Return the result content as MongoDBREf objects.
         *
         * @return array array of mongo references
         */
        public function toRefArray()
        {
            // Retrieve the actual objects.
            $documents = $this->toArray();

            // Get the collection idenfitier
            $collection = (string) $this->db->getCollection();

            foreach ($documents as &$document) {
                $document = \MongoDBRef::create($collection, $document);
            }

            return $documents;
        }

        /**
         * Original cursor method routing.
         *
         * @param string $method    method name
         * @param array  $arguments method arguments
         *
         * @return mixed method result
         */
        public function __call($method, $arguments)
        {
            if (!method_exists($this->result, $method)) {
                throw new BadMethodCallException('Call to undefined function ' . get_called_class() . '::' . $method . '.');
            }

            // Trigger the method.
            $function   = [$this->result, $method];
            $result     = call_user_func_array($function, $arguments);

            // When the cursor is returned, return the current instance.
            // It has no use returning the cursor because the cursor
            // contained in this instance will already be affected.
            // Returning it's will cursor in an out-of-sync cursor
            // in this instance.
            if ($result instanceof MongoCursor) {
                return $this;
            }

            return $result;
        }

        /**
         * Conditionally retry a closure if it yields an exception.
         *
         * If the closure does not return successfully within the configured number
         * of retries, its first exception will be thrown.
         *
         * The $recreate parameter may be used to recreate the MongoCursor between
         * retry attempts.
         *
         * @param \Closure $retry
         * @return mixed
         */
        protected function retry(\Closure $retry)
        {
            if ($this->numRetries < 1) {
                return $retry();
            }

            $firstException = null;

            for ($i = 0; $i <= $this->numRetries; $i++) {
                try {
                    return $retry();
                } catch (\MongoCursorException $e) {
                } catch (\MongoConnectionException $e) {
                }

                if ($firstException === null) {
                    $firstException = $e;
                }

                if ($i === $this->numRetries) {
                    throw $firstException;
                }
            }
        }

        public function row($row, $object = false)
        {
            unset($row['_id']);

            return $row;
        }

        public function fetch($object = false)
        {
            $cursor = $this->result;

            $cursor->next();

            $row = $cursor->current();

            unset($row['_id']);

            return $object ? $this->db->model($row) : $row;
        }

        public function model()
        {
            $cursor = $this->result;

            $cursor->next();

            $row = $cursor->current();

            unset($row['_id']);

            return $this->db->model($row);
        }

        public function delete()
        {
            while ($row = $this->model()) {
                $row->delete();
            }

            return $this;
        }

        public function first($object = false)
        {
            $cursor = $this->result;

            $cursor->next();

            $row = $cursor->current();

            $cursor->rewind();

            unset($row['_id']);

            return $object ? $this->db->model($row) : $row;
        }
    }
