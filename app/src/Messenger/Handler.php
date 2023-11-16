<?php

declare(strict_types=1);

namespace App\Messenger;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class Handler implements MessageHandlerInterface
{
    public function __invoke(Message $message)
    {
        dd($message);
    }
}
