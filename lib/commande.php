<?php
    namespace Octo;

    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Formatter\OutputFormatterStyle;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class Commande extends Command
    {
        protected function execute(InputInterface $input, OutputInterface $output)
        {
            Timer::start();

            $this->setupConsole($input, $output);
            $this->go();
            $this->exit();
        }

        private function exit()
        {
            $task = str_replace(['Octo\\', 'Task'], '', get_called_class());

            Timer::stop();

            Cli::show("Execution time of task '$task' ==> " . Timer::get() . " s.");
        }

        public function setupConsole(InputInterface $input, OutputInterface $output)
        {
            $this->argv = $_SERVER['argv'][0];

            array_shift($_SERVER['argv']);
            array_shift($_SERVER['argv']);

            $_REQUEST = $_SERVER['argv'];

            $this->input  = $input;
            $this->output = $output;

            $this->output->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
            $this->output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow', null, array('bold')));
            $this->output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, array('bold')));
            $this->output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, array('bold')));
            $this->output->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, array('bold')));
            $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, array('bold')));
            $this->output->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, array('bold')));
        }

        protected function go() { }
    }
