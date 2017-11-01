<?php
    namespace Octo;

    class Registrymail implements FastMailerInterface
    {
        private $message;

        /**
         * @param Courrier $message
         */
        public function __construct(Courrier $message)
        {
            $this->message = $message;
        }

        /**
         * @return array
         */
        public function sent()
        {
            return Registry::get('core.mails', []);
        }

        /**
         * @return bool
         */
        public function send()
        {
            $to         = cut('<', '>', $this->message->getEncodedHeader('To'));
            $from       = cut('<', '>', $this->message->getEncodedHeader('From'));
            $subject    = $this->message->getEncodedHeader('Subject');
            $priority   = $this->message->getEncodedHeader('X-Priority');
            $html       = $this->message->getHtmlBody();
            $text       = $this->message->getBody();

            $fromName   = str_replace(" <$from>", '', $this->message->getEncodedHeader('From'));

            $mails = Registry::get('core.mails', []);

            $mails[] = [
                'to'        => $to,
                'from'      => $from,
                'from_name' => $fromName,
                'subject'   => $subject,
                'priority'  => $priority,
                'html'      => $html,
                'text'      => $text
            ];

            Registry::set('core.mails', $mails);

            return true;
        }

        public function last()
        {
            return o(Arrays::last($this->sent()));
        }
    }
