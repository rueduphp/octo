<?php
    namespace Octo;

    class Octaliamail
    {
        private $message;

        public function __construct(Courrier $message)
        {
            $this->message = $message;
        }

        public function send()
        {
            $to         = cut('<', '>', $this->message->getEncodedHeader('To'));
            $from       = cut('<', '>', $this->message->getEncodedHeader('From'));
            $subject    = $this->message->getEncodedHeader('Subject');
            $html       = $this->message->getHtmlBody();
            $text       = $this->message->getBody();

            $fromName   = str_replace(" <$from>", '', $this->message->getEncodedHeader('From'));

            $mail = em('systemMail')->store([
                'to'        => $to,
                'from'      => $from,
                'from_name' => $fromName,
                'subject'   => $subject,
                'html'      => $html,
                'text'      => $text
            ]);

            if ($mail->id > 0) {
                return true;
            } else {
                throw new Exception("Unable to send email.");
            }
        }
    }
