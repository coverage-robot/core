<?php

namespace App\Tests\Handler;

use App\Handler\AnalyseHandler;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use PHPUnit\Framework\TestCase;

class AnalyseHandlerTest extends TestCase
{
    public function testHandleSqs(): void
    {
        $handler = new AnalyseHandler();

        $handler->handleSqs(
            new SqsEvent(
                [
                    'Records' => [
                        [
                            'eventSource' => 'aws:sqs',
                            'messageId' => 'mock',
                            'body' => [

                            ],
                            'messageAttributes' => [

                            ]
                        ]
                    ]
                ]
            ),
            Context::fake()
        );
    }
}
