<?php
    namespace Octo;

    class MimePart
    {
        const ENCODING_BASE64 = 'base64',
            ENCODING_7BIT = '7bit',
            ENCODING_8BIT = '8bit',
            ENCODING_QUOTED_PRINTABLE = 'quoted-printable';

        const EOL = "\r\n";
        const LINE_LENGTH = 76;

        private $headers = [];

        private $parts = [];

        private $body;

        public function setHeader($name, $value, $append = FALSE)
        {
            if (!$name || preg_match('#[^a-z0-9-]#i', $name)) {
                throw new Exception("Header name must be non-empty alphanumeric string, '$name' given.");
            }

            if ($value == NULL) {
                if (!$append) {
                    unset($this->headers[$name]);
                }

            } elseif (is_array($value)) {
                $tmp = & $this->headers[$name];

                if (!$append || !is_array($tmp)) {
                    $tmp = [];
                }

                foreach ($value as $email => $recipient) {
                    if ($recipient !== null && !$this->checkEncoding($recipient)) {
                        if (!$this->isUnicode($recipient)) {
                            throw new Exception($recipient . ' is not unicode.');
                        }
                    }

                    if (preg_match('#[\r\n]#', $recipient)) {
                        throw new Exception('Name must not contain line separator.');
                    }

                    if (!$this->isEmail($email)) {
                        throw new Exception($email . ' is not a correct email.');
                    }

                    $tmp[$email] = $recipient;
                }
            } else {
                $value = (string) $value;

                if (!$this->checkEncoding($value)) {
                    throw new Exception('Header is not valid UTF-8 string.');
                }

                $this->headers[$name] = preg_replace('#[\r\n]+#', ' ', $value);
            }

            return $this;
        }

        public function isUnicode($value)
        {
            return is_string($value) && preg_match('##u', $value);
        }

        public function isEmail($value)
        {
            $atom = "[-a-z0-9!#$%&'*+/=?^_`{|}~]";
            $alpha = "a-z\x80-\xFF";

            return (bool) preg_match("(^
                (\"([ !#-[\\]-~]*|\\\\[ -~])+\"|$atom+(\\.$atom+)*)  # quoted or unquoted
                @
                ([0-9$alpha]([-0-9$alpha]{0,61}[0-9$alpha])?\\.)+    # domain - RFC 1034
                [$alpha]([-0-9$alpha]{0,17}[$alpha])?                # top domain
            \\z)ix", $value);
        }

        public function getHeader($name)
        {
            return isset($this->headers[$name]) ? $this->headers[$name] : null;
        }

        public function clearHeader($name)
        {
            unset($this->headers[$name]);

            return $this;
        }

        public function getEncodedHeader($name)
        {
            $offset = strlen($name) + 2; // colon + space

            if (!isset($this->headers[$name])) {
                return null;

            } elseif (is_array($this->headers[$name])) {
                $s = '';
                foreach ($this->headers[$name] as $email => $name) {
                    if ($name != null) {
                        $s .= self::encodeHeader($name, $offset, TRUE);
                        $email = " <$email>";
                    }
                    $s .= self::append($email . ',', $offset);
                }
                return ltrim(substr($s, 0, -1));

            } elseif (preg_match('#^(\S+; (?:file)?name=)"(.*)"\z#', $this->headers[$name], $m)) { // Content-Disposition
                $offset += strlen($m[1]);
                return $m[1] . '"' . self::encodeHeader($m[2], $offset) . '"';

            } else {
                return ltrim(self::encodeHeader($this->headers[$name], $offset));
            }
        }

        public function getHeaders()
        {
            return $this->headers;
        }

        public function setContentType($contentType, $charset = null)
        {
            $this->setHeader('Content-Type', $contentType . ($charset ? "; charset=$charset" : ''));

            return $this;
        }

        public function setEncoding($encoding)
        {
            $this->setHeader('Content-Transfer-Encoding', $encoding);
            return $this;
        }

        public function getEncoding()
        {
            return $this->getHeader('Content-Transfer-Encoding');
        }

        public function addPart(MimePart $part = null)
        {
            return $this->parts[] = $part === null ? new self : $part;
        }

        public function setBody($body)
        {
            $this->body = (string) $body;

            return $this;
        }

        public function getBody()
        {
            return $this->body;
        }

        public function getEncodedMessage()
        {
            $output = '';
            $boundary = '--------' . token();

            foreach ($this->headers as $name => $value) {
                $output .= $name . ': ' . $this->getEncodedHeader($name);
                if ($this->parts && $name === 'Content-Type') {
                    $output .= ';' . self::EOL . "\tboundary=\"$boundary\"";
                }
                $output .= self::EOL;
            }
            $output .= self::EOL;

            $body = (string) $this->body;
            if ($body !== '') {
                switch ($this->getEncoding()) {
                    case self::ENCODING_QUOTED_PRINTABLE:
                        $output .= quoted_printable_encode($body);
                        break;

                    case self::ENCODING_BASE64:
                        $output .= rtrim(chunk_split(base64_encode($body), self::LINE_LENGTH, self::EOL));
                        break;

                    case self::ENCODING_7BIT:
                        $body = preg_replace('#[\x80-\xFF]+#', '', $body);
                        // break intentionally omitted

                    case self::ENCODING_8BIT:
                        $body = str_replace(["\x00", "\r"], '', $body);
                        $body = str_replace("\n", self::EOL, $body);
                        $output .= $body;
                        break;

                    default:
                        throw new Exception('Unknown encoding.');
                }
            }

            if ($this->parts) {
                if (substr($output, -strlen(self::EOL)) !== self::EOL) {
                    $output .= self::EOL;
                }
                foreach ($this->parts as $part) {
                    $output .= '--' . $boundary . self::EOL . $part->getEncodedMessage() . self::EOL;
                }
                $output .= '--' . $boundary.'--';
            }

            return $output;
        }

        private static function encodeHeader($s, & $offset = 0, $quotes = FALSE)
        {
            if (strspn($s, "!\"#$%&\'()*+,-./0123456789:;<>@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^`abcdefghijklmnopqrstuvwxyz{|}~=? _\r\n\t") === strlen($s)) {
                if ($quotes && preg_match('#[^ a-zA-Z0-9!\#$%&\'*+/?^_`{|}~-]#', $s)) { // RFC 2822 atext except =
                    return self::append('"' . addcslashes($s, '"\\') . '"', $offset);
                }

                return self::append($s, $offset);
            }

            $o = '';

            if ($offset >= 55) {
                $o = self::EOL . "\t";
                $offset = 1;
            }

            $s = iconv_mime_encode(str_repeat(' ', $old = $offset), $s, [
                'scheme' => 'B', // Q is broken
                'input-charset' => 'UTF-8',
                'output-charset' => 'UTF-8',
            ]);

            $offset = strlen($s) - strrpos($s, "\n");
            $s = str_replace("\n ", "\n\t", substr($s, $old + 2)); // adds ': '

            return $o . $s;
        }


        private static function append($s, & $offset = 0)
        {
            if ($offset + strlen($s) > self::LINE_LENGTH) {
                $offset = 1;
                $s = self::EOL . "\t" . $s;
            }

            $offset += strlen($s);

            return $s;
        }

        public function fixEncoding($s)
        {
            return htmlspecialchars_decode(
                htmlspecialchars(
                    $s,
                    ENT_NOQUOTES | ENT_IGNORE,
                    'UTF-8'
                ),
                ENT_NOQUOTES
            );
        }

        public function checkEncoding($s)
        {
            return $s === $this->fixEncoding($s);
        }
    }
