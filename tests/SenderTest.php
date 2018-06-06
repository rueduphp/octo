<?php

use Octo\Mailable;
use Octo\Sender;

class SenderTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        (new Sender())
            ->setHost('localhost')
            ->setPort(1025)
            ->memory();
    }

    /**
     * @throws Exception
     */
    public function testIsMailer()
    {
        $this->assertInstanceOf(Swift_Mailer::class, $this->in('mailer'));
    }

    /**
     * @throws Exception
     */
    public function testSend()
    {
        $mailer = $this->mailer();

        $message = new Mailable();

        $message
            ->from('test@test.com')
            ->to('dummy@test.com')
            ->subject('test')
            ->setBody(
                'Here is <b>the message</b> itself',
                'text/html'
            )->addPart(
                'Here is the message itself',
                'text/plain'
            );

        $status = $mailer->send($message->getSwiftMessage());

        /** @var array $messages */
        $messages = $this->registered('core.mails', []);

        $this->assertSame(1, $status);
        $this->assertSame(end($messages), $message->getSwiftMessage());
    }
}