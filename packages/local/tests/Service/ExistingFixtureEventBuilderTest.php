<?php

namespace Packages\Local\Tests\Service;

use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Packages\Local\Service\CustomPayloadEventBuilder;
use Packages\Local\Service\ExistingFixtureEventBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sentry\Serializer\SerializerInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Serializer;

class ExistingFixtureEventBuilderTest extends TestCase
{
    #[DataProvider('variedConsoleInputDataProvider')]
    public function testSupports(InputInterface $input, bool $expectedSupport): void
    {
        $isSupported = ExistingFixtureEventBuilder::supports(
            $input,
            Event::UPLOAD
        );

        $this->assertEquals($expectedSupport, $isSupported);
    }

    public function testBuild(): void
    {
        $mockEvent = $this->createMock(EventInterface::class);

        $mockSerializer = $this->createMock(Serializer::class);
        $mockSerializer->expects($this->once())
            ->method('denormalize')
            ->willReturn($mockEvent);

        $existingFixtureEventBuilder = new ExistingFixtureEventBuilder(
            $mockSerializer,
            __DIR__ . '/../Fixture/'
        );

        $mockQuestionHelper = $this->createMock(QuestionHelper::class);
        $mockQuestionHelper->expects($this->once())
            ->method('ask')
            ->willReturnCallback(
                function (
                    InputInterface $input,
                    OutputInterface $output,
                    ChoiceQuestion $question
                ) {
                    $this->assertCount(1, $question->getChoices());
                    return $question->getChoices()[0];
                }
            );

        $event = $existingFixtureEventBuilder->build(
            new ArrayInput(
                [
                    '--fixture' => true
                ],
                new InputDefinition([
                    new InputOption('fixture', null, InputOption::VALUE_NONE)
                ])
            ),
            $this->createMock(OutputInterface::class),
            new HelperSet([
                'question' => $mockQuestionHelper
            ]),
            Event::UPLOAD
        );

        $this->assertEquals($mockEvent, $event);
    }


    public function testGetPriority(): void
    {
        $this->assertEquals(
            0,
            ExistingFixtureEventBuilder::getPriority()
        );
    }

    public static function variedConsoleInputDataProvider(): array
    {
        return [
            [
                new ArrayInput(
                    ['--fixture' => true],
                    new InputDefinition([
                        new InputOption('fixture', null, InputOption::VALUE_NONE)
                    ])
                ),
                true
            ],
            [
                new ArrayInput(
                    [],
                    new InputDefinition([
                        new InputOption('fixture', null, InputOption::VALUE_NONE)
                    ])
                ),
                false
            ]
        ];
    }
}
