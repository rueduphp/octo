<?php
    namespace Octo;

    class Postman
    {
        private $message;
        private $driver;

        public function __construct(Courrier $message, $driver = null)
        {
            $this->message  = $message;
            $this->driver   = $driver;
        }

        public function send()
        {
            $driver = !$this->driver ? Config::get('mailer.driver', 'logmail') : $this->driver;

            try {
                return lib($driver, [$this->message])->send();
            } catch (\Exception $e) {
                throw new Exception("Unable to send email.");
            }
        }

        public function queue()
        {
            lib('later')->set('Postman.' . token(), function ($m) {
                $driver = Config::get('mailer.driver', 'logmail');
                $message = unserialize($m);

                return lib($driver, [$message])->send();
            }, [serialize($this->message)]);

            lib('later')->background();

            return true;
        }

        public function later($seconds = 60)
        {
            $when = time() + $seconds;

            lib('later')->set('Postman.' . token(), function ($m) {
                $driver = Config::get('mailer.driver', 'logmail');
                $message = unserialize($m);

                return lib($driver, [$message])->send();
            }, [serialize($this->message)], $when);

            lib('later')->background();

            return true;
        }
    }
