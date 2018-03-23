<?php
    namespace Octo;

    use Swift_Mailer;
    use Swift_Message;
    use Swift_Image;
    use Swift_Attachment;
    use Swift_Mime_Attachment;

    class Mailable
    {
        /**
         * The Swift Message instance.
         *
         * @var \Swift_Message
         */
        protected $swift;

        /**
         * Create a new message instance.
         *
         * @param  \Swift_Message|null  $swift
         * @return void
         */
        public function __construct(?Swift_Message $swift = null)
        {
            $swift = is_null($swift) ? Swift_Message::newInstance() : $swift;
            $this->swift = $swift;
        }

        /**
         * Add a "from" address to the message.
         *
         * @param  string|array  $address
         * @param  string|null  $name
         *
         * @return $this
         */
        public function from($address, $name = null)
        {
            $this->swift->setFrom($address, $name);

            return $this;
        }

        /**
         * Set the "sender" of the message.
         *
         * @param  string|array  $address
         * @param  string|null  $name
         *
         * @return $this
         */
        public function sender($address, $name = null)
        {
            $this->swift->setSender($address, $name);

            return $this;
        }

        /**
         * Set the "return path" of the message.
         *
         * @param  string  $address
         *
         * @return $this
         */
        public function returnPath($address)
        {
            $this->swift->setReturnPath($address);

            return $this;
        }

        /**
         * Add a recipient to the message.
         *
         * @param  string|array  $address
         * @param  string|null  $name
         * @param  bool  $override
         *
         * @return $this
         */
        public function to($address, $name = null, $override = false)
        {
            if ($override) {
                $this->swift->setTo($address, $name);

                return $this;
            }

            return $this->addAddresses($address, $name, 'To');
        }

        /**
         * Add a carbon copy to the message.
         *
         * @param  string|array  $address
         * @param  string|null  $name
         * @return $this
         */
        public function cc($address, $name = null)
        {
            return $this->addAddresses($address, $name, 'Cc');
        }

        /**
         * Add a blind carbon copy to the message.
         *
         * @param  string|array  $address
         * @param  string|null  $name
         * @return $this
         */
        public function bcc($address, $name = null)
        {
            return $this->addAddresses($address, $name, 'Bcc');
        }

        /**
         * Add a reply to address to the message.
         *
         * @param  string|array  $address
         * @param  string|null  $name
         * @return $this
         */
        public function replyTo($address, $name = null)
        {
            return $this->addAddresses($address, $name, 'ReplyTo');
        }

        /**
         * Add a recipient to the message.
         *
         * @param  string|array  $address
         * @param  string  $name
         * @param  string  $type
         * @return $this
         */
        protected function addAddresses($address, $name, $type)
        {
            if (is_array($address)) {
                $this->swift->{"set{$type}"}($address, $name);
            } else {
                $this->swift->{"add{$type}"}($address, $name);
            }

            return $this;
        }

        /**
         * Set the subject of the message.
         *
         * @param  string  $subject
         * @return $this
         */
        public function subject($subject)
        {
            $this->swift->setSubject($subject);

            return $this;
        }

        /**
         * Set the message priority level.
         *
         * @param  int  $level
         * @return $this
         */
        public function priority($level)
        {
            $this->swift->setPriority($level);

            return $this;
        }

        /**
         * Attach a file to the message.
         *
         * @param  string  $file
         * @param  array  $options
         * @return $this
         */
        public function attach($file, array $options = [])
        {
            $attachment = $this->createAttachmentFromPath($file);

            return $this->prepAttachment($attachment, $options);
        }

        /**
         * @param string $file
         * @return Swift_Mime_Attachment
         */
        protected function createAttachmentFromPath(string $file): Swift_Mime_Attachment
        {
            return Swift_Attachment::fromPath($file);
        }

        /**
         * Attach in-memory data as an attachment.
         *
         * @param  string  $data
         * @param  string  $name
         * @param  array  $options
         * @return $this
         */
        public function attachData($data, $name, array $options = [])
        {
            $attachment = $this->createAttachmentFromData($data, $name);

            return $this->prepAttachment($attachment, $options);
        }

        /**
         * @param $data
         * @param $name
         * @return Swift_Mime_Attachment
         */
        protected function createAttachmentFromData($data, $name): Swift_Mime_Attachment
        {
            return Swift_Attachment::newInstance($data, $name);
        }

        /**
         * @param string $file
         * @return string
         */
        public function embed(string $file): string
        {
            return $this->swift->embed(Swift_Image::fromPath($file));
        }

        /**
         * @param string $data
         * @param string $name
         * @param null|string $contentType
         *
         * @return string
         */
        public function embedData(string $data, string $name, ?string $contentType = null): string
        {
            $image = Swift_Image::newInstance($data, $name, $contentType);

            return $this->swift->embed($image);
        }

        /**
         * @param Swift_Attachment $attachment
         * @param array $options
         *
         * @return Mailable
         */
        protected function prepAttachment(Swift_Attachment $attachment, array $options = []): self
        {
            if (isset($options['mime'])) {
                $attachment->setContentType($options['mime']);
            }

            if (isset($options['as'])) {
                $attachment->setFilename($options['as']);
            }

            $this->swift->attach($attachment);

            return $this;
        }

        /**
         * Get the underlying Swift Message instance.
         *
         * @return \Swift_Message
         */
        public function getSwiftMessage(): Swift_Message
        {
            return $this->swift;
        }

        /**
         * Get the underlying Swift Message instance.
         *
         * @return \Swift_Message
         */
        public function __invoke(): Swift_Message
        {
            return $this->swift;
        }

        /**
         * @param null|Swift_Mailer $mailer
         *
         * @return int
         */
        public function send(?Swift_Mailer $mailer = null): int
        {
            /** @var Swift_Mailer $mailer */
            $mailer = $mailer ?: getContainer()['mailer'];

            return $mailer->send($this->swift);
        }

        /**
         * @param string $path
         * @param array $args
         *
         * @return Mailable
         *
         * @throws \Twig_Error_Loader
         * @throws \Twig_Error_Runtime
         * @throws \Twig_Error_Syntax
         */
        public function twig(string $path, array $args = []): self
        {
            $body = twig($path, $args);

            $this->swift
                ->setBody(
                    $body,
                    'text/html'
                )->addPart(
                    strip_tags($body),
                    'text/plain'
                );

            return $this;
        }

        /**
         * @param string $path
         * @param array $args
         *
         * @return Mailable
         *
         * @throws \Exception
         * @throws \ReflectionException
         */
        public function blade(string $path, array $args = []): self
        {
            $body = blade($path, $args);

            $this->swift
                ->setBody(
                    $body,
                    'text/html'
                )->addPart(
                    strip_tags($body),
                    'text/plain'
                );

            return $this;
        }

        /**
         * @param string $path
         * @param array $args
         *
         * @return Mailable
         */
        public function view(string $path, array $args = []): self
        {
            $view = vue($path, $args);

            $body = $view->inline();

            $this->swift
            ->setBody(
                $body,
                'text/html'
            )->addPart(
                strip_tags($body),
                'text/plain'
            );

            return $this;
        }

        /**
         * Dynamically pass missing methods to the Swift instance.
         *
         * @param  string  $method
         * @param  array  $parameters
         *
         * @return mixed
         */
        public function __call(string $method, array $parameters)
        {
            $callable = [$this->swift, $method];

            return call_user_func_array($callable, $parameters);
        }
    }
