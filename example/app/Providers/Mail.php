<?php
namespace App\Providers;

use Octo\Sender;

class Mail
{
    public function handler()
    {
        (new Sender)
            ->setHost(config('mail.host'))
            ->setPort(config('mail.port'))
            ->smtp()
        ;
    }
}
