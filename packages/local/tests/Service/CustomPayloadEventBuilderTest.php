<?php

declare(strict_types=1);

namespace Packages\Local\Tests\Service;

use Iterator;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Packages\Local\Service\CustomPayloadEventBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Serializer;

final class CustomPayloadEventBuilderTest extends TestCase
{
    #[DataProvider('variedConsoleInputDataProvider')]
    public function testSupports(InputInterface $input, bool $expectedSupport): void
    {
        $isSupported = CustomPayloadEventBuilder::supports(
            $input,
            Event::UPLOAD
        );

        $this->assertSame($expectedSupport, $isSupported);
    }


    public function testBuild(): void
    {
        $mockEvent = $this->createMock(EventInterface::class);

        $mockSerializer = $this->createMock(Serializer::class);
        $mockSerializer->expects($this->once())
            ->method('denormalize')
            ->willReturn($mockEvent);

        $customPayloadBuilder = new CustomPayloadEventBuilder($mockSerializer);

        $event = $customPayloadBuilder->build(
            new ArrayInput(
                [
                    '--file' => __DIR__ . '/../Fixture/UPLOAD/mock-upload-event.json'
                ],
                new InputDefinition([
                    new InputOption('file', null, InputOption::VALUE_REQUIRED)
                ])
            ),
            $this->createMock(OutputInterface::class),
            $this->createMock(HelperSet::class),
            Event::UPLOAD
        );

        $this->assertEquals($mockEvent, $event);
    }


    public function testGetPriority(): void
    {
        $this->assertSame(
            0,
            CustomPayloadEventBuilder::getPriority()
        );
    }

    /**
     * @return Iterator<list{ ArrayInput, bool }>
     */
    public static function variedConsoleInputDataProvider(): Iterator
    {
        yield [
            new ArrayInput(
                ['--file' => "some-path/"],
                new InputDefinition([
                    new InputOption('file', null, InputOption::VALUE_REQUIRED)
                ])
            ),
            true
        ];

        yield [
            new ArrayInput(
                [],
                new InputDefinition([
                    new InputOption('file', null, InputOption::VALUE_REQUIRED)
                ])
            ),
            false
        ];
    }
}
