<?php
    namespace Octo;

    use Phinx\Config\Config as PhinxConfig;
    use Phinx\Migration\Manager;
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Output\StreamOutput;

    class OrmManager
    {
        protected $manager;

        public function __construct()
        {
            $orm = new Orm;

            $config = new PhinxConfig([
                'paths' => [
                    'migrations' => conf('MIGRATIONS_PATH'),
                    'seeds'      => conf('SEEDS_PATH')
                ],
                'environments'          => [
                    'default_database'  => 'octo_orm',
                    'octo_orm'          => [
                        'name'          => 'octo_orm',
                        'connection'    => $orm->getPdo()
                    ]
                ]
            ]);

            $input  = new ArrayInput([]);
            $output = new StreamOutput(fopen('php://memory', 'a', false));

            $this->manager = new Manager($config, $input, $output);
        }

        public function migrate()
        {
            $this->manager->migrate('octo_orm');

            return $this;
        }

        public function seed()
        {
            $this->manager->seed('octo_orm');

            return $this;
        }
    }
