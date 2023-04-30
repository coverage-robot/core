<?php

namespace App\Handler;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;

class AnalyseHandler extends SqsHandler
{
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        // TODO: Implement handleSqs() method.
    }
}