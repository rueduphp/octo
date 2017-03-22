<?php
    namespace Octo;

    class Smtp
    {
        private $connection;
        private $host;
        private $port;
        private $username;
        private $password;
        private $secure;
        private $timeout;
        private $context;
        private $persistent;
        private $message;

        public function __construct(Courrier $message)
        {
            $this->message = $message;

            $this->host     = Config::get('mailer.host', '127.0.0.1');
            $this->port     = Config::get('mailer.port', null);
            $this->username = Config::get('mailer.username', 'root');
            $this->password = Config::get('mailer.password', 'root');
            $this->secure   = Config::get('mailer.secure', '');
            $this->timeout  = Config::get('mailer.timeout', 20);
            $this->context  = stream_context_get_default();

            $this->persistent = !empty(Config::get('mailer.persistent', null));

            if (!$this->port) {
                $this->port = $this->secure === 'ssl' ? 465 : 25;
            }
        }

        public function send()
        {
            $mail = clone $this->message;

            try {
                if (!$this->connection) {
                    $this->connect();
                }

                if (($from = $mail->getHeader('Return-Path')) || ($from = key($mail->getHeader('From')))) {
                    $this->write("MAIL FROM:<$from>", 250);
                }

                foreach (array_merge((array) $mail->getHeader('To'), (array) $mail->getHeader('Cc'), (array) $mail->getHeader('Bcc')) as $email => $name) {
                    $this->write("RCPT TO:<$email>", [250, 251]);
                }

                $mail->setHeader('Bcc', null);
                $data = $mail->generateMessage();
                $this->write('DATA', 354);
                $data = preg_replace('#^\.#m', '..', $data);
                $this->write($data);
                $this->write('.', 250);

                if (!$this->persistent) {
                    $this->write('QUIT', 221);
                    $this->disconnect();
                }

                return true;
            } catch (Exception $e) {
                if ($this->connection) {
                    $this->disconnect();
                }

                throw $e;
            }
        }

        protected function connect()
        {
            $this->connection = @stream_socket_client( // @ is escalated to exception
                ($this->secure === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port,
                $errno, $error, $this->timeout, STREAM_CLIENT_CONNECT, $this->context
            );

            if (!$this->connection) {
                throw new Exception($error, $errno);
            }

            stream_set_timeout($this->connection, $this->timeout, 0);
            $this->read();

            $self = isset($_SERVER['HTTP_HOST']) && preg_match('#^[\w.-]+\z#', $_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $this->write("EHLO $self");
            $ehloResponse = $this->read();

            if ((int) $ehloResponse !== 250) {
                $this->write("HELO $self", 250);
            }

            if ($this->secure === 'tls') {
                $this->write('STARTTLS', 220);

                if (!stream_socket_enable_crypto($this->connection, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Unable to connect via TLS.');
                }

                $this->write("EHLO $self", 250);
            }

            if ($this->username != null && $this->password != null) {
                $authMechanisms = [];

                if (preg_match('~^250[ -]AUTH (.*)$~im', $ehloResponse, $matches)) {
                    $authMechanisms = explode(' ', trim($matches[1]));
                }

                if (in_array('PLAIN', $authMechanisms, true)) {
                    $credentials = $this->username . "\0" . $this->username . "\0" . $this->password;
                    $this->write('AUTH PLAIN ' . base64_encode($credentials), 235, 'PLAIN credentials');
                } else {
                    $this->write('AUTH LOGIN', 334);
                    $this->write(base64_encode($this->username), 334, 'username');
                    $this->write(base64_encode($this->password), 235, 'password');
                }
            }
        }

        protected function disconnect()
        {
            fclose($this->connection);

            $this->connection = null;
        }

        protected function write($line, $expectedCode = null, $message = null)
        {
            fwrite($this->connection, $line . Courrier::EOL);

            if ($expectedCode) {
                $response = $this->read();

                if (!in_array((int) $response, (array) $expectedCode, true)) {
                    throw new Exception('SMTP server did not accept ' . ($message ? $message : $line) . ' with error: ' . trim($response));
                }
            }
        }

        protected function read()
        {
            $s = '';

            while (($line = fgets($this->connection, 1e3)) != null) {
                $s .= $line;

                if (substr($line, 3, 1) === ' ') {
                    break;
                }
            }

            return $s;
        }
    }
