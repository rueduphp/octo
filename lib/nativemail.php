<?php
    namespace Octo;

    class Nativemail
    {
        private $message;

        public function __construct(Courrier $message)
        {
            $this->message = $message;
        }

        public function send()
        {
            $tmp = clone $this->message;
            $tmp->setHeader('Subject', null);
            $tmp->setHeader('To', null);

            $parts = explode(Courrier::EOL . Courrier::EOL, $tmp->generateMessage(), 2);

            $args = [
                str_replace(Courrier::EOL, PHP_EOL, $this->message->getEncodedHeader('To')),
                str_replace(Courrier::EOL, PHP_EOL, $this->message->getEncodedHeader('Subject')),
                str_replace(Courrier::EOL, PHP_EOL, $parts[1]),
                str_replace(Courrier::EOL, PHP_EOL, $parts[0])
            ];

            $res = call_user_func_array('mail', $args);

            if ($res === false) {
                exception("Mail", "Unable to send email.");
            }
        }
    }
