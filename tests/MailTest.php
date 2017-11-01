<?php

use Octo\Courrier;
use Octo\Registrymail;

class MailTest extends TestCase
{
    public function testMail()
    {
        $courrier = new Courrier();

        $courrier
            ->addTo('to@test.com', 'To')
            ->setFrom('from@test.com', 'From')
            ->setSubject('Test mail')
            ->setBody('text body')
            ->setHtmlBody('<h1>html body</h1>')
            ->setPriority(1)
        ;

        $mailer = new Registrymail($courrier);

        $mailer->send();

        $sent = $mailer->sent();

        $this->assertCount(1, $sent);

        $message = $mailer->last();

        $this->assertEquals('Test mail', $message->getSubject());
        $this->assertEquals('text body', $message->getText());
        $this->assertEquals(1, $message->getPriority());
    }
}
