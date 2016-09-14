<?php
    namespace Octo;

    class Elasticmail
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

            $res = "";

            $data = "username=" .       urlencode(Config::get('mailer.username', 'root'));
            $data .= "&api_key=" .      urlencode(Config::get('mailer.password', 'root'));
            $data .= "&from=" .         urlencode($from);
            $data .= "&from_name=" .    urlencode($fromName);
            $data .= "&to=" .           urlencode($to);
            $data .= "&subject=" .      urlencode($subject);

            if($html) $data .= "&body_html=" . urlencode($html);
            if($text) $data .= "&body_text=" . urlencode($text);

            $header = "POST /mailer/send HTTP/1.0\r\n";
            $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $header .= "Content-Length: " . strlen($data) . "\r\n\r\n";

            $fp = fsockopen('ssl://api.elasticemail.com', 443, $errno, $errstr, 30);

            if(!$fp)
                throw new Exception("ERROR. Could not open connection");
            else {
                fputs ($fp, $header.$data);

                while (!feof($fp)) {
                    $res .= fread ($fp, 1024);
                }

                fclose($fp);
            }

            $last = Arrays::last(explode("\n", $res));

            if(fnmatch('*-*-*-*-*', $last)) {
                return true;
            } else {
                throw new Exception("Unable to send email.");
            }
        }
    }
