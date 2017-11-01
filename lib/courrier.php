<?php
    namespace Octo;

    class Courrier extends MimePart
    {
        /** Priority */
        const HIGH = 1,
            NORMAL = 3,
            LOW = 5;

        /** @var array */
        public static $defaultHeaders = [
            'MIME-Version' => '1.0',
            'X-Mailer' => 'Octo Framework',
        ];

        /** @var array */
        private $attachments = [];

        /** @var array */
        private $inlines = [];

        /** @var mixed */
        private $html;


        public function __construct()
        {
            foreach (static::$defaultHeaders as $name => $value) {
                $this->setHeader($name, $value);
            }

            $this->setHeader('Date', date('r'));
        }

        public function __toString()
        {
            return $this->getSubject();
        }

        public function __invoke()
        {
            echo $this->getSubject();
        }

        public function setFrom($email, $name = null)
        {
            $this->setHeader('From', $this->formatEmail($email, $name));

            return $this;
        }

        public function getFrom()
        {
            return $this->getHeader('From');
        }

        public function addReplyTo($email, $name = NULL)
        {
            $this->setHeader('Reply-To', $this->formatEmail($email, $name), true);

            return $this;
        }

        public function setSubject($subject)
        {
            $this->setHeader('Subject', $subject);

            return $this;
        }

        /**
         * @return string
         */
        public function getSubject()
        {
            return $this->getHeader('Subject');
        }

        public function addTo($email, $name = NULL) // addRecipient()
        {
            $this->setHeader('To', $this->formatEmail($email, $name), true);

            return $this;
        }

        public function addCc($email, $name = NULL)
        {
            $this->setHeader('Cc', $this->formatEmail($email, $name), true);

            return $this;
        }

        public function addBcc($email, $name = null)
        {
            $this->setHeader('Bcc', $this->formatEmail($email, $name), true);

            return $this;
        }

        private function formatEmail($email, $name)
        {
            if (!$name && preg_match('#^(.+) +<(.*)>\z#', $email, $matches)) {
                return [$matches[2] => $matches[1]];
            } else {
                return [$email => $name];
            }
        }

        public function setReturnPath($email)
        {
            $this->setHeader('Return-Path', $email);

            return $this;
        }

        public function getReturnPath()
        {
            return $this->getHeader('Return-Path');
        }

        public function setPriority($priority)
        {
            $this->setHeader('X-Priority', (int) $priority);

            return $this;
        }

        public function getPriority()
        {
            return $this->getHeader('X-Priority');
        }

        public function setHtmlBody($html, $basePath = null)
        {
            $html = (string) $html;

            if ($basePath) {
                $cids = [];

                $matches = matchAll(
                    $html,
                    '#
                        (<img[^<>]*\s src\s*=\s*
                        |<body[^<>]*\s background\s*=\s*
                        |<[^<>]+\s style\s*=\s* ["\'][^"\'>]+[:\s] url\(
                        |<style[^>]*>[^<]+ [:\s] url\()
                        (["\']?)(?![a-z]+:|[/\\#])([^"\'>)\s]+)
                        |\[\[ ([\w()+./@~-]+) \]\]
                    #ix',
                    PREG_OFFSET_CAPTURE
                );

                foreach (array_reverse($matches) as $m) {
                    $file = rtrim($basePath, '/\\') . '/' . (isset($m[4]) ? $m[4][0] : urldecode($m[3][0]));

                    if (!isset($cids[$file])) {
                        $cids[$file] = substr($this->addEmbeddedFile($file)->getHeader('Content-ID'), 1, -1);
                    }

                    $html = substr_replace($html,
                        "{$m[1][0]}{$m[2][0]}cid:{$cids[$file]}",
                        $m[0][1], strlen($m[0][0])
                    );
                }
            }

            if ($this->getSubject() == null) { // intentionally ==
                $html = $this->replace($html, '#<title>(.+?)</title>#is', function ($m) use (& $title) {
                    $title = $m[1];
                });

                $this->setSubject(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));
            }

            $this->html = ltrim(str_replace("\r", '', $html), "\n");

            if ($this->getBody() == null && $html != null) { // intentionally ==
                $this->setBody($this->buildText($html));
            }

            return $this;
        }

        public function getHtmlBody()
        {
            return $this->html;
        }

        public function addEmbeddedFile($file, $content = null, $contentType = null)
        {
            return $this->inlines[$file] =
                $this->createAttachment(
                    $file,
                    $content,
                    $contentType,
                    'inline'
                )->setHeader(
                    'Content-ID',
                    $this->getRandomId()
                );
        }

        public function addInlinePart(MimePart $part)
        {
            $this->inlines[] = $part;

            return $this;
        }

        public function addAttachment($file, $content = null, $contentType = null)
        {
            return $this->attachments[] = $this->createAttachment(
                $file,
                $content,
                $contentType,
                'attachment'
            );
        }

        public function getAttachments()
        {
            return $this->attachments;
        }

        private function createAttachment($file, $content, $contentType, $disposition)
        {
            $part = new MimePart;

            if ($content === null) {
                $content = @file_get_contents($file); // @ is escalated to exception

                if ($content === false) {
                    throw new Exception("Unable to read file '$file'.");
                }
            } else {
                $content = (string) $content;
            }

            $part->setBody($content);
            $part->setContentType($contentType ? $contentType : finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content));
            $part->setEncoding(preg_match('#(multipart|message)/#A', $contentType) ? self::ENCODING_8BIT : self::ENCODING_BASE64);
            $part->setHeader('Content-Disposition', $disposition . '; filename="' . $this->fixEncoding(basename($file)) . '"');

            return $part;
        }

        public function generateMessage()
        {
            return $this->build()->getEncodedMessage();
        }

        protected function build()
        {
            $mail = clone $this;
            $mail->setHeader('Message-ID', $this->getRandomId());

            $cursor = $mail;

            if ($mail->attachments) {
                $tmp = $cursor->setContentType('multipart/mixed');
                $cursor = $cursor->addPart();

                foreach ($mail->attachments as $value) {
                    $tmp->addPart($value);
                }
            }

            if ($mail->html != NULL) { // intentionally ==
                $tmp = $cursor->setContentType('multipart/alternative');
                $cursor = $cursor->addPart();
                $alt = $tmp->addPart();

                if ($mail->inlines) {
                    $tmp = $alt->setContentType('multipart/related');
                    $alt = $alt->addPart();

                    foreach ($mail->inlines as $value) {
                        $tmp->addPart($value);
                    }
                }

                $alt->setContentType('text/html', 'UTF-8')
                ->setEncoding(preg_match('#[^\n]{990}#', $mail->html)
                    ? self::ENCODING_QUOTED_PRINTABLE
                    : (preg_match('#[\x80-\xFF]#', $mail->html) ? self::ENCODING_8BIT : self::ENCODING_7BIT))
                ->setBody($mail->html);
            }

            $text = $mail->getBody();
            $mail->setBody(null);
            $cursor->setContentType('text/plain', 'UTF-8')
            ->setEncoding(preg_match('#[^\n]{990}#', $text)
                ? self::ENCODING_QUOTED_PRINTABLE
                : (preg_match('#[\x80-\xFF]#', $text) ? self::ENCODING_8BIT : self::ENCODING_7BIT))
            ->setBody($text);

            return $mail;
        }

        protected function buildText($html)
        {
            $text = $this->replace($html, [
                '#<(style|script|head).*</\\1>#Uis' => '',
                '#<t[dh][ >]#i' => ' $0',
                '#<a\s[^>]*href=(?|"([^"]+)"|\'([^\']+)\')[^>]*>(.*?)</a>#is' =>  '$2 &lt;$1&gt;',
                '#[\r\n]+#' => ' ',
                '#<(/?p|/?h\d|li|br|/tr)[ >/]#i' => "\n$0",
            ]);

            $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
            $text = $this->replace($text, '#[ \t]+#', ' ');

            return trim($text);
        }

        private function getRandomId()
        {
            return '<' . token() . '@'
            . preg_replace('#[^\w.-]+#', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n'))
            . '>';
        }

        public function replace($subject, $pattern, $replacement = null, $limit = -1)
        {
            if (is_object($replacement) || is_array($replacement)) {
                if (!is_callable($replacement, false, $textual)) {
                    throw new Exception("Callback '$textual' is not callable.");
                }

                return $this->pcre('preg_replace_callback', [$pattern, $replacement, $subject, $limit]);
            } elseif ($replacement === null && is_array($pattern)) {
                $replacement    = array_values($pattern);
                $pattern        = array_keys($pattern);
            }

            return $this->pcre('preg_replace', [$pattern, $replacement, $subject, $limit]);
        }

        public function pcre($func, $args)
        {
            static $messages = [
                PREG_INTERNAL_ERROR         => 'Internal error',
                PREG_BACKTRACK_LIMIT_ERROR  => 'Backtrack limit was exhausted',
                PREG_RECURSION_LIMIT_ERROR  => 'Recursion limit was exhausted',
                PREG_BAD_UTF8_ERROR         => 'Malformed UTF-8 data',
                PREG_BAD_UTF8_OFFSET_ERROR  => 'Offset didn\'t correspond to the begin of a valid UTF-8 code point',
                6                           => 'Failed due to limited JIT stack space'
            ];

            $res = $this->invokeSafe($func, $args, function ($message) use ($args) {
                throw new Exception($message . ' in pattern: ' . implode(' or ', (array) $args[0]));
            });

            if (($code = preg_last_error()) && ($res === null || !in_array($func, ['preg_filter', 'preg_replace_callback', 'preg_replace']))) {
                throw new Exception((isset($messages[$code]) ? $messages[$code] : 'Unknown error') . ' (pattern: ' . implode(' or ', (array) $args[0]) . ')', $code);
            }

            return $res;
        }

        public function invokeSafe($function, array $args, $onError)
        {
            set_error_handler(function ($severity, $message, $file) use ($onError, & $prev, $function) {
                if ($file === '' && defined('HHVM_VERSION')) {
                    $file = func_get_arg(5)[1]['file'];
                }

                if ($file === __FILE__) {
                    $msg = preg_replace("#^$function\(.*?\): #", '', $message);

                    if ($onError($msg, $severity) !== false) {
                        return;
                    }
                }

                return $prev ? $prev(...func_get_args()) : false;
            });

            try {
                return $function(...$args);
            } finally {
                restore_error_handler();
            }
        }
    }
