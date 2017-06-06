<?php
    namespace Octo;

    class Pivot
    {
        public function attach($model1, $model2, $args = [])
        {
            if (!is_object($model1)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!is_object($model2)) {
                throw new Exception('the second argument must be a model.');
            }

            if (!strlen($model1->id)) {
                throw new Exception("attach method requires a valid model 1 [$model1->id].");
            }

            if (!strlen($model2->id)) {
                throw new Exception("attach method requires a valid model 2 [$model2->id].");
            }

            $m1Table = $model1->table();
            $m2Table = $model2->table();

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $m1Table) {
                $from_id    = $model1->id;
                $from_db    = $model1->db();

                $to_id      = $model2->id;
                $to_db      = $model2->db();
            } else {
                $from_id    = $model2->id;
                $from_db    = $model2->db();
                $to_id      = $model1->id;
                $to_db      = $model1->db();
            }

            $pivot = System::Pivot()->firstOrCreate([
                'from_table'    => (string) $from,
                'from_db'       => (string) $from_db,
                'to_db'         => (string) $to_db,
                'to_table'      => (string) $to,
                'from_id'       => (int) $from_id,
                'to_id'         => (int) $to_id,
            ]);

            if (!empty($args)) {
                foreach ($args as $k => $v) {
                    $pivot->$k = $this->cleanInt($v);
                }

                $pivot->save();
            }

            return $pivot;
        }

        private function cleanInt($v)
        {
            if (is_numeric($v)) {
                if (!fnmatch('*.*', $v) && !fnmatch('*,*', $v)) {
                    $v = (int) $v;
                } else {
                    $v = (double) $v;
                }
            }

            return $v;
        }

        public function detach($model1, $model2)
        {
            if (!is_object($model1)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!is_object($model2)) {
                throw new Exception('the second argument must be a model.');
            }

            if (!strlen($model1->id)) {
                throw new Exception("attach method requires a valid model 1.");
            }

            if (!strlen($model2->id)) {
                throw new Exception("attach method requires a valid model 2.");
            }

            $m1Table = $model1->table();
            $m2Table = $model2->table();

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $m1Table) {
                $from_id    = $model1->id;
                $from_db    = $model1->db();

                $to_id      = $model2->id;
                $to_db      = $model2->db();
            } else {
                $from_id    = $model2->id;
                $from_db    = $model2->db();
                $to_id      = $model1->id;
                $to_db      = $model1->db();
            }

            $pivot = System::Pivot()
            ->where(['from_id', '=', (int) $from_id])
            ->where(['to_id', '=', (int) $to_id])
            ->where(['from_db', '=', (string) $from_db])
            ->where(['to_db', '=', (string) $to_db])
            ->where(['from_table', '=', (string) $from])
            ->where(['to_table', '=', (string) $to])
            ->first(true);

            if ($pivot) {
                $pivot->delete();

                return true;
            }

            return false;
        }

        public function exists($model1, $model2)
        {
            if (!is_object($model1)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!is_object($model2)) {
                throw new Exception('the second argument must be a model.');
            }

            if (!strlen($model1->id)) {
                throw new Exception("detach method requires a valid model 1.");
            }

            if (!strlen($model2->id)) {
                throw new Exception("detach method requires a valid model 2.");
            }

            $m1Table = $model1->table();
            $m2Table = $model2->table();

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $m1Table) {
                $from_id    = $model1->id;
                $from_db    = $model1->db();

                $to_id      = $model2->id;
                $to_db      = $model2->db();
            } else {
                $from_id    = $model2->id;
                $from_db    = $model2->db();
                $to_id      = $model1->id;
                $to_db      = $model1->db();
            }

            $count = System::Pivot()
            ->where(['from_id', '=', (int) $from_id])
            ->where(['to_id', '=', (int) $to_id])
            ->where(['from_db', '=', (string) $from_db])
            ->where(['to_db', '=', (string) $to_db])
            ->where(['from_table', '=', (string) $from])
            ->where(['to_table', '=', (string) $to])
            ->count();

            return $count > 0 ? true : false;
        }

        public function retrieve($model, $pivot)
        {
            if (!is_object($model)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!strlen($model->id)) {
                throw new Exception("attach method requires a valid model 1.");
            }

            $names = [(string) $model->table(), (string) $pivot];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $model->table()) {
                $rows = System::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['from_id', '=', (int) $model->id])
                ->where(['from_db', '=', (string) $model->db()])
                ->where(['to_table', '=', (string) $pivot])
                ->get();
            } else {
                $rows = System::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['to_id', '=', (int) $model->id])
                ->where(['to_db', '=', (string) $model->db()])
                ->where(['to_table', '=', (string) $model->table()])
                ->get();
            }

            $collection = [];

            foreach ($rows as $tab) {
                unset($tab['to_db']);
                unset($tab['to_table']);
                unset($tab['to_id']);

                unset($tab['from_db']);
                unset($tab['from_table']);
                unset($tab['from_id']);

                unset($tab['id']);
                unset($tab['created_at']);
                unset($tab['updated_at']);

                if ($from == $model->table()) {
                    $object = odb((string) $row->to_db, (string) $row->to_table)->row((int) $row->to_id);
                } else {
                    $object = odb((string) $row->from_db, (string) $row->from_table)->row((int) $row->from_id);
                }

                if (!empty($tab)) {
                    $object['pivot'] = $tab;
                }

                $collection[] = $object;
            }

            return coll($collection);
        }


        public function delete($model, $pivot)
        {
            if (!is_object($model)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!strlen($model->id)) {
                throw new Exception("attach method requires a valid model.");
            }

            $names = [$model->table(), $pivot];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $model->table()) {
                $rows = System::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['from_id', '=', (int) $model->id])
                ->where(['from_db', '=', (string) $model->db()])
                ->where(['to_table', '=', (string) $pivot])
                ->get();
            } else {
                $rows = System::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['to_id', '=', (int) $model->id])
                ->where(['to_db', '=', (string) $model->db()])
                ->where(['to_table', '=', (string) $model->table()])
                ->get();
            }

            return $rows->delete();
        }

        public function has($model, $pivot)
        {
            if (is_object($pivot)) {
                if ($pivot instanceof Octalia) {
                    $pivot = $pivot->table;
                } else {
                    $pivot = $pivot->table();
                }
            }

            if (!is_object($model)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!strlen($model->id)) {
                throw new Exception("attach method requires a valid model 1.");
            }

            $names = [(string) $model->table(), (string) $pivot];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $model->table()) {
                $count = System::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['from_id', '=', (int) $model->id])
                ->where(['from_db', '=', (string) $model->db()])
                ->where(['to_table', '=', (string) $pivot])
                ->count();
            } else {
                $count = System::Pivot()
                ->where(['from_table', '=', (string) $pivot])
                ->where(['to_id', '=', (int) $model->id])
                ->where(['to_db', '=', (string) $model->db()])
                ->where(['to_table', '=', (string) $to])
                ->count();
            }

            return $count > 0 ? true : false;
        }

        public function __call($m, $a)
        {
            if (fnmatch('retrieve*', $m) && strlen($m) > strlen('retrieve')) {
                $pivot = Inflector::lower(str_replace('retrieve', '', $m));

                return call_user_func_array([$this, 'retrieve'], [current($a), (string) $pivot]);
            }

            if (fnmatch('get*', $m) && strlen($m) > strlen('get')) {
                $pivot = Inflector::lower(str_replace('get', '', $m));

                $res = call_user_func_array([$this, 'retrieve'], [current($a), (string) $pivot]);

                $last = $m[strlen($m) - 1];

                if ('s' == $last) {
                    return $res;
                }

                $row = $res->first();

                if (!$row) {
                    $obj    = current($a);
                    $field  = $pivot . '_id';
                    $table  = ucfirst($pivot);
                    $row    = odb($obj->db(), $pivot)->row((int) $obj->$field);
                }

                return $row;
            }
        }

        public static function sync($model1, $model2)
        {
            if (!is_object($model1)) {
                exception('pivot', 'the first argument must be a model.');
            }

            if (!is_object($model2)) {
                exception('pivot', 'the second argument must be a model.');
            }

            if (!$model1->exists()) {
                exception('pivot', "sync method requires a valid model 1.");
            }

            if (!$model2->exists()) {
                exception('pivot', "sync method requires a valid model 2.");
            }

            if ($model1->db() != $model2->db()) {
                exception('pivot', "sync method requires the 2 models have the same database.");
            }

            $m1Table = $model1->table();
            $m2Table = $model2->table();

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $entity = Inflector::camelize($model1->db() . '_' . current($names) . end($names));

            $db = em($entity);

            $exists = $db
            ->where($m1Table . '_id', $model1->id)
            ->where($m2Table . '_id', $model2->id)
            ->exists();

            if (!$exists) {
                $item = [
                    $m1Table . '_id' => $model1->id,
                    $m2Table . '_id' => $model2->id
                ];

                $db->store($item);

                return true;
            }

            return false;
        }

        public static function pivoted($model1, $em)
        {
            if (is_string($em)) {
                $em = maker($em, [], false);
            }

            $model2 = $em->first();

            $m1Table = $model1->table();
            $m2Table = $model2->table();

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $entity = Inflector::camelize($model1->db() . '_' . current($names) . end($names));

            $db = em($entity);

            $pivots = $db
            ->where($m1Table . '_id', $model1->id)
            ->get();

            $fk = $m2Table . '_id';

            $ids = [];

            foreach ($pivots as $pivot) {
                $id = $pivot[$fk];

                if (!in_array($id, $ids)) {
                    $ids[] = $id;
                }
            }

            return $em->newQuery()->where('id', 'IN', $ids);
        }

        public static function get($model1, $model2)
        {
            if (!$model2 instanceof Object) {
                return self::pivoted($model1, $model2);
            }

            if (!is_object($model1)) {
                exception('pivot', 'the first argument must be a model.');
            }

            if (!is_object($model2)) {
                exception('pivot', 'the second argument must be a model.');
            }

            if (!$model1->exists()) {
                exception('pivot', "get method requires a valid model 1.");
            }

            if (!$model2->exists()) {
                exception('pivot', "get method requires a valid model 2.");
            }

            if ($model1->db() != $model2->db()) {
                exception('pivot', "get method requires the 2 models have the same database.");
            }

            $m1Table = $model1->table();
            $m2Table = $model2->table();

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $entity = Inflector::camelize($model1->db() . '_' . current($names) . end($names));

            $db = em($entity);

            return $db
            ->where($m1Table . '_id', $model1->id)
            ->where($m2Table . '_id', $model2->id)
            ->first();
        }

        public static function bound($model1, $model2)
        {
            if (!is_object($model1)) {
                exception('pivot', 'the first argument must be a model.');
            }

            if (!is_object($model2)) {
                exception('pivot', 'the second argument must be a model.');
            }

            if (!$model1->exists()) {
                exception('pivot', "bound method requires a valid model 1.");
            }

            if (!$model2->exists()) {
                exception('pivot', "bound method requires a valid model 2.");
            }

            if ($model1->db() != $model2->db()) {
                exception('pivot', "bound method requires the 2 models have the same database.");
            }

            $m1Table = $model1->table();
            $m2Table = $model2->table();

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $entity = Inflector::camelize($model1->db() . '_' . current($names) . end($names));

            $db = em($entity);

            return $db
            ->where($m1Table . '_id', $model1->id)
            ->where($m2Table . '_id', $model2->id)
            ->exists();
        }

        public static function remove($model1, $model2)
        {
            if (!is_object($model1)) {
                exception('pivot', 'the first argument must be a model.');
            }

            if (!is_object($model2)) {
                exception('pivot', 'the second argument must be a model.');
            }

            if (!$model1->exists()) {
                exception('pivot', "remove method requires a valid model 1.");
            }

            if (!$model2->exists()) {
                exception('pivot', "remove method requires a valid model 2.");
            }

            if ($model1->db() != $model2->db()) {
                exception('pivot', "remove method requires the 2 models have the same database.");
            }

            $m1Table = $model1->table();
            $m2Table = $model2->table();

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $entity = Inflector::camelize($model1->db() . '_' . current($names) . end($names));

            $db = em($entity);

            return $db
            ->where($m1Table . '_id', $model1->id)
            ->where($m2Table . '_id', $model2->id)
            ->delete();
        }
    }
