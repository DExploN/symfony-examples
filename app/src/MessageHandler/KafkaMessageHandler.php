<?php

namespace App\MessageHandler;

use App\Message\KafkaMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class KafkaMessageHandler
{
    public function __invoke(KafkaMessage $message)
    {
        echo $message->getName();
    }
}
