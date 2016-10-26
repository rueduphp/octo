<?php
    namespace Octo;

    class Notification
    {
        private $data,
        $driver,
        $status = 'success',
        $lines = ['start' => [], 'end' => []],
        $subject,
        $action = ['name' => null, 'url' => null];

        /**
         * Notification constructor.
         * @param null $driver
         * @param array $data
         */
        public function __construct($driver = null, array $data = [])
        {
            $driver         = is_null($driver) ? Config::get('notification.driver', 'mail') : $driver;
            $this->driver   = $driver;
            $this->data     = $data;
        }

        /**
         * @param $line
         * @return $this
         */
        public function line($line)
        {
            $segment = is_null($this->action['name']) ? 'start' : 'end';

            $this->lines[$segment][] = $line;

            return $this;
        }

        /**
         * @param $subject
         * @return $this
         */
        public function subject($subject)
        {
            $this->subject = $subject;

            return $this;
        }

        /**
         * @return $this
         */
        public function success()
        {
            $this->status = 'success';

            return $this;
        }

        /**
         * @return $this
         */
        public function warning()
        {
            $this->status = 'warning';

            return $this;
        }

        /**
         * @param $m
         * @param $a
         * @return $this
         */
        public function __call($m, $a)
        {
            $this->status = $m;

            return $this;
        }

        /**
         * @return $this
         */
        public function status()
        {
            $this->status = 'info';

            return $this;
        }

        /**
         * @return $this
         */
        public function error()
        {
            $this->status = 'error';

            return $this;
        }

        /**
         * @param $name
         * @param null $url
         * @return $this
         */
        public function action($name, $url = null)
        {
            $url = is_null($url) ? def('URLSITE') : $url;

            $this->action['name'] = $name;
            $this->action['url'] = $url;

            return $this;
        }

        /**
         * @return mixed
         */
        public function send()
        {
            return call_user_func_array([$this, $this->driver . 'To'], []);
        }

        /**
         * @return bool
         */
        public function mailTo()
        {
            $status     = $this->status;
            $subject    = $this->subject;
            $action     = $this->action;
            $lines      = $this->lines;

            $from = Config::get('notification.email.from', 'Notification <notify@' . def('SITE_NAME', 'appli') . '.com>');

            $to = isAke($this->data, 'to', []);

            if (empty($to)) {
                $to = Config::get('notification.email.to', ['Admin <admin@' . def('SITE_NAME', 'appli') . '.com>']);
            }

            if (is_string($to)) {
                $to = [$to];
            }

            $html = tpl(__DIR__ . DS . 'notif.email.php', compact('status', 'subject', 'action', 'lines'));

            foreach ($to as $email) {
                $mail = new Courrier();
                $mail->setFrom($from);
                $mail->addTo($email);
                $mail->setSubject($subject);
                $mail->setHTMLBody($html);

                $mailer = new Postman($mail);

                $mailer->queue();
            }

            return true;
        }

        /**
         * @return bool
         */
        public function logTo()
        {
            $status     = $this->status;
            $subject    = $this->subject;
            $action     = $this->action;
            $lines      = $this->lines;

            $file = path('storage') . '/notifications.txt';

            if (!is_file($file) || !file_exists($file) || !is_writable($file)) {
                File::put($file, '');
            }

            $txt = "Date: " .  date('Y-m-d H:i:s') . "\n";
            $txt .= "Status: " .  Strings::upper($status) . "\n";
            $txt .= "Subject: " .  $subject . "\n\n";
            $txt .= "-------------------------- MESSAGE --------------------------\n\n";
            $txt .= implode("\n", $lines['start']) . "\n\n";
            $txt .= "Action: " .  $action['name'] . "\n";
            $txt .= "Url: " .  $action['url'] . "\n\n";
            $txt .= implode("\n", $lines['end']);

            File::append($file, $txt . "\n\n--------------------------\n\n");

            return true;
        }

        /**
         * @return bool
         */
        public function databaseTo()
        {
            $status     = $this->status;
            $subject    = $this->subject;
            $action     = $this->action;
            $lines      = $this->lines;

            $from = Config::get('notification.database.from', 'Admin');

            $to = isAke($this->data, 'to', []);

            if (empty($to)) {
                $to = Config::get('notification.database.to', ['Admin']);
            }

            if (is_string($to)) {
                $to = [$to];
            }

            foreach ($to as $receiver) {
                $notif          =  System::Notification()->store([
                    'status'    => Strings::upper($status),
                    'to'        => $receiver,
                    'from'      => $from,
                    'subject'   => $subject,
                    'action'    => $action['name'],
                    'url'       => $action['url'],
                    'message'   => implode("\n", $lines['start']) . "\n" . implode("\n", $lines['end'])
                ]);

                unset($this->data['to']);
                unset($this->data['from']);

                if (!empty($this->data)) {
                    foreach ($this->data as $k => $v) {
                        $notif[$k] = File::value($v);
                    }

                    $notif->save();
                }
            }

            return true;
        }
    }
