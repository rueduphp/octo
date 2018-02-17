<?php

namespace Octo;

use Aws\Ses\SesClient;
use Psr\Log\LoggerInterface;
use Swift_Mailer;
use Swift_SendmailTransport as MailTransport;
use Swift_SendmailTransport as SendmailTransport;
use Swift_SmtpTransport as SmtpTransport;
use Swift_Transport;

class Sender
{
    /**
     * @var string
     */
    private $host = 'localhost';

    /**
     * @var int
     */
    private $port = 25;

    /**
     * @var null|string
     */
    private $protocol = null;

    /**
     * @var null|string
     */
    private $username = null;

    /**
     * @var null|string
     */
    private $password = null;

    /**
     * @param string $host
     *
     * @return Sender
     */
    public function setHost(string $host = 'localhost'): self
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param int $port
     *
     * @return Sender
     */
    public function setPort(int $port = 25): self
    {
        $this->port = $port;

        return $this;
    }

    /**
     * @param null|string $protocol
     *
     * @return Sender
     */
    public function setProtocol(?string $protocol = null): self
    {
        $this->protocol = $protocol;

        return $this;
    }

    /**
     * @param null|string $username
     *
     * @return Sender
     */
    public function setUsername(?string $username = null): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @param null|string $password
     *
     * @return Sender
     */
    public function setPassword(?string $password = null): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Swift_Mailer
     */
    public function mail()
    {
        return $this->mailer(new MailTransport());
    }

    /**
     * @return Swift_Mailer
     */
    public function memory()
    {
        return $this->mailer(new Memorytransporter());
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return Swift_Mailer
     */
    public function log(LoggerInterface $logger)
    {
        return $this->mailer(new Logtransporter($logger));
    }

    /**
     * @param string $cmd
     *
     * @return Swift_Mailer
     */
    function sendmail(string $cmd = '/usr/sbin/sendmail -bs')
    {
        return $this->mailer(new SendmailTransport($cmd));
    }

    /**
     * @return Swift_Mailer
     */
    public function smtp()
    {
        $transport = SmtpTransport::newInstance($this->host, $this->port);

        if (!is_null($this->protocol)) {
            $transport->setEncryption($this->protocol);
        }

        if (!is_null($this->username)) {
            $transport->setUsername($this->username);
            $transport->setPassword($this->password);
        }

        return $this->mailer($transport);
    }

    /**
     * @param SesClient $client
     *
     * @return Swift_Mailer
     */
    public function ses(SesClient $client)
    {
        return $this->mailer(new Ses($client));
    }

    /**
     * @return Swift_Mailer
     */
    public function amazonsmtp()
    {
            $transport = Awsmail::newInstance($this->host, (int) $this->port, $this->protocol)
                ->setUsername($this->username)
                ->setPassword($this->password)
            ;

            return $this->mailer($transport);
    }

    /**
     * @param Swift_Transport $transport
     *
     * @return Swift_Mailer
     */
    protected function mailer(Swift_Transport $transport)
    {
        $mailer = Swift_Mailer::newInstance($transport);
        getContainer()['mailer'] = $mailer;

        return $mailer;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return null|string
     */
    public function getProtocol(): ?string
    {
        return $this->protocol;
    }

    /**
     * @return null|string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @return null|string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }
}
