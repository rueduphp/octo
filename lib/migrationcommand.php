<?php
    namespace Octo;

    use Illuminate\Console\Command;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Input\InputArgument;

    class MigrationCommand extends Command
    {
        protected $name = 'octo:make:model';
        protected $description = 'Make an Octalia model';

        public function __construct()
        {
            parent::__construct();
        }

        function fire()
        {
            $table  = $this->argument('table');
            $fields = $this->argument('fields');

            $fields = explode(',', str_replace(' ', '', $fields));

            $model  = Model::$table();

            createModel($model, $fields);

            $this->output->writeln('<info>Model ' . get_class($model->create()) . ' created.</info>');
        }

        protected function getArguments()
        {
            return [
                ['table', InputArgument::REQUIRED, 'Table name'],
                ['fields', InputArgument::REQUIRED, 'Comma separated fields to add in the table'],
            ];
        }

        protected function getOptions()
        {
            return [];
        }
    }

    class SeedCommand extends Command
    {
        protected $name = 'octo:seed:model';
        protected $description = 'Seed an Octalia model';

        public function __construct()
        {
            parent::__construct();
        }

        function fire()
        {
            $table  = $this->argument('table');
            $amount  = $this->argument('amount');

            $model  = Model::$table();

            $model->fake((int) $amount);

            if (1 < $amount) {
                $rows = "$amount rows";
            } else {
                $rows = "$amount row";
            }

            $this->output->writeln('<info>Model ' . get_class($model->create()) . ' seeded ' . $rows . '.</info>');
        }

        protected function getArguments()
        {
            return [
                ['table', InputArgument::REQUIRED, 'Table name'],
                ['amount', InputArgument::OPTIONAL, 'Amount of seeds.', 1],
            ];
        }

        protected function getOptions()
        {
            return [];
        }
    }

    class CleanFakeCommand extends Command
    {
        protected $name = 'octo:clean_fake:model';
        protected $description = 'Clean all fakes in an Octalia model';

        public function __construct()
        {
            parent::__construct();
        }

        function fire()
        {
            $table  = $this->argument('table');

            $model  = Model::$table();

            $query = $model->where('is_fake', true);

            $count = $query->count();

            $query->delete();

            $this->output->writeln('<info>Model ' . get_class($model->create()) . ' has been cleaned (' . $count . ' fakes deleted).</info>');
        }

        protected function getArguments()
        {
            return [
                ['table', InputArgument::REQUIRED, 'Table name']
            ];
        }

        protected function getOptions()
        {
            return [];
        }
    }
