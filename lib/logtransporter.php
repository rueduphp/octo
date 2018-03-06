<?php

namespace Octo;

use Psr\Log\LoggerInterface;
use Swift_Mime_SimpleMimeEntity;

class Logtransporter extends Transporter
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param  \Psr\Log\LoggerInterface  $logger
     *
     * @return void
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function send(\Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $this->logger->debug($this->getMimeEntityString($message));

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * @param  \Swift_Mime_SimpleMimeEntity $entity
     *
     * @return string
     */
    protected function getMimeEntityString(Swift_Mime_SimpleMimeEntity $entity): string
    {
        $string = (string) $entity->getHeaders() . PHP_EOL . $entity->getBody();

        foreach ($entity->getChildren() as $children) {
            $string .= PHP_EOL . PHP_EOL . $this->getMimeEntityString($children);
        }

        return $string;
    }
}
