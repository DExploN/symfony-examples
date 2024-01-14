<?php

namespace App\Messenger\MessageHandler;

use App\Messenger\Message\Message;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MessageHandler
{
    public function __invoke(Message $message)
    {
        echo $message->getName();
        Throw new \Exception("error");
    }
}
