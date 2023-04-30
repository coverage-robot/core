<?php

namespace App\Tests\Handler;

use App\Handler\AnalyseHandler;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AnalyseHandlerTest extends TestCase
{
    public function testHandleSqs(): void
    {
        $handler = new AnalyseHandler(new NullLogger());

        $handler->handleSqs(
            new SqsEvent(
                [
                    'Records' => [
                        [
                            'eventSource' => 'aws:sqs',
                            'messageId' => 'mock',
                            'body' => json_encode([
                                'uniqueId' => 'mock-uuid'
                            ]),
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
