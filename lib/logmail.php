<?php
    namespace Octo;

    class Logmail
    {
        private $message;

        public function __construct(Courrier $message)
        {
            $this->message = $message;
        }

        public function send()
        {
            try {
                $mail = $this->message->generateMessage();

                $file = path('storage') . DS . 'mails.txt';

                if (!File::exists($file)) {
                    File::put($file, '');
                }

                File::append($file, $mail . "\n\n--------------------------\n\n");

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }
