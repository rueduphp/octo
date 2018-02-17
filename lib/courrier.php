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

        /**
         * @throws Exception
         */
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

        /**
         * @param string $email
         * @param null|string $name
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function setFrom(string $email, ?string $name = null): self
        {
            $this->setHeader('From', $this->formatEmail($email, $name));

            return $this;
        }

        public function getFrom()
        {
            return $this->getHeader('From');
        }

        /**
         * @param string $email
         * @param null|string $name
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function addReplyTo(string $email, ?string $name = null): self
        {
            $this->setHeader('Reply-To', $this->formatEmail($email, $name), true);

            return $this;
        }

        /**
         * @param string $subject
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function setSubject(string $subject): self
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

        /**
         * @param string $email
         * @param null|string $name
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function addTo(string $email, ?string $name = null): self
        {
            $this->setHeader('To', $this->formatEmail($email, $name), true);

            return $this;
        }

        /**
         * @param string $email
         * @param null|string $name
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function addCc(string $email, ?string $name = null): self
        {
            $this->setHeader('Cc', $this->formatEmail($email, $name), true);

            return $this;
        }

        /**
         * @param string $email
         * @param null|string $name
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function addBcc(string $email, ?string $name = null): self
        {
            $this->setHeader('Bcc', $this->formatEmail($email, $name), true);

            return $this;
        }

        /**
         * @param string $email
         * @param string $name
         *
         * @return array
         */
        protected function formatEmail(string $email, string $name): array
        {
            if (!$name && preg_match('#^(.+) +<(.*)>\z#', $email, $matches)) {
                return [$matches[2] => $matches[1]];
            } else {
                return [$email => $name];
            }
        }

        /**
         * @param string $email
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function setReturnPath(string $email): self
        {
            $this->setHeader('Return-Path', $email);

            return $this;
        }

        /**
         * @return mixed|null
         */
        public function getReturnPath()
        {
            return $this->getHeader('Return-Path');
        }

        /**
         * @param int $priority
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function setPriority(int $priority): self
        {
            $this->setHeader('X-Priority', (int) $priority);

            return $this;
        }

        /**
         * @return mixed|null
         */
        public function getPriority()
        {
            return $this->getHeader('X-Priority');
        }

        /**
         * @param string $html
         * @param null|string $basePath
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function setHtmlBody(string $html, ?string $basePath = null): self
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

        /**
         * @return mixed
         */
        public function getHtmlBody()
        {
            return $this->html;
        }

        /**
         * @param string $file
         * @param null|string $content
         * @param null|string $contentType
         *
         * @return Courrier
         *
         * @throws Exception
         */
        public function addEmbeddedFile(string $file, ?string $content = null, ?string $contentType = null): self
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

        /**
         * @param MimePart $part
         *
         * @return Courrier
         */
        public function addInlinePart(MimePart $part): self
        {
            $this->inlines[] = $part;

            return $this;
        }

        /**
         * @param string $file
         * @param null|string $content
         * @param null|string $contentType
         *
         * @return MimePart
         *
         * @throws Exception
         */
        public function addAttachment(string $file, ?string $content = null, ?string $contentType = null): MimePart
        {
            return $this->attachments[] = $this->createAttachment(
                $file,
                $content,
                $contentType,
                'attachment'
            );
        }

        /**
         * @return array
         */
        public function getAttachments()
        {
            return $this->attachments;
        }

        /**
         * @param string $file
         * @param string $content
         * @param string $contentType
         * @param string $disposition
         *
         * @return MimePart
         *
         * @throws Exception
         */
        protected function createAttachment(
            string $file,
            string $content,
            string $contentType,
            string $disposition
        ): MimePart {
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

        /**
         * @return string
         *
         * @throws Exception
         */
        public function generateMessage(): string
        {
            return $this->build()->getEncodedMessage();
        }

        /**
         * @return Courrier
         *
         * @throws Exception
         */
        protected function build(): self
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

        /**
         * @param string $html
         *
         * @return string
         *
         * @throws Exception
         */
        protected function buildText(string $html): string
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

        /**
         * @return string
         */
        private function getRandomId(): string
        {
            return '<' . token() . '@'
            . preg_replace('#[^\w.-]+#', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n'))
            . '>';
        }

        /**
         * @param string $subject
         * @param string $pattern
         * @param null|string $replacement
         * @param int $limit
         *
         * @return mixed
         *
         * @throws Exception
         */
        public function replace(
            string $subject,
            string $pattern,
            ?string $replacement = null,
            int $limit = -1
        ) {
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

        /**
         * @param string $func
         * @param array $args
         *
         * @return mixed
         *
         * @throws Exception
         */
        public function pcre(string $func, array $args)
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

        /**
         * @param string $function
         * @param array $args
         * @param callable $onError
         *
         * @return mixed
         */
        public function invokeSafe(string $function, array $args, callable $onError)
        {
            set_error_handler(function ($severity, $message, $file) use ($onError, &$prev, $function) {
                if ($file === '' && defined('HHVM_VERSION')) {
                    $file = func_get_arg(5)[1]['file'];
                }

                if ($file === __FILE__) {
                    $msg = preg_replace("#^$function\(.*?\): #", '', $message);

                    if ($status = $onError($msg, $severity) !== false) {
                        return $status;
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
