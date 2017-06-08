<?php
    namespace Octo;

    use PHPUnit\Framework\TestCase as PTC;

    abstract class TestCase extends PTC
    {
        protected $app;

        abstract public function makeApplication();

        public function em($entity, $new = true)
        {
            return dbMemory($entity, $new);
        }

        public function factory($class, $count = 1, $lng = 'fr_FR')
        {
            $model = maker($class, [], false);
            $faker = faker($lng);

            $entity = $this->em(
                lcfirst(
                    Strings::camelize(
                        $model->orm()->db . '_' . $model->orm()->table
                    )
                )
            );

            $rows = [];

            for ($i = 0; $i < $count; $i++) {
                $rows[] = $model->factory($faker);
            }

            $factories = o([
                'rows' => $rows,
                'entity' => $entity
            ]);

            $factories->macro('raw', function () use ($factories) {
                return $factories->getRows();
            });

            $factories->macro('store', function () use ($factories) {
                $em = $factories->getEntity();
                $rows = [];

                foreach ($factories->getRows() as $row) {
                    $rows[] = $em->persist($row);
                }

                return $rows;
            });

            return $factories;
        }

        protected function setUp()
        {
            if (!$this->app) {
                $this->refreshApplication();
            }
        }

        protected function refreshApplication()
        {
            putenv('APPLICATION_ENV=testing');

            $this->app = $this->makeApplication();
        }

        protected function tearDown()
        {
            if ($this->app) {
                $this->app = null;
            }

            if (property_exists($this, 'serverVariables')) {
                $this->serverVariables = [];
            }
        }
    }
