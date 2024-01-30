<?php

namespace Packages\Local\Tests\Service;

use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Packages\Local\Service\CustomPayloadEventBuilder;
use Packages\Local\Service\ExistingFixtureEventBuilder;
use Packages\Local\Service\ManualInputEventBuilder;
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
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Serializer;

final class ManualInputEventBuilderTest extends TestCase
{
    #[DataProvider('variedConsoleInputDataProvider')]
    public function testSupports(InputInterface $input, bool $expectedSupport): void
    {
        $isSupported = ManualInputEventBuilder::supports(
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

        $mockPropertyInfoExtractor = $this->createMock(PropertyInfoExtractor::class);
        $mockPropertyInfoExtractor->expects($this->once())
            ->method('getProperties')
            ->with(EventInterface::class)
            ->willReturn([
                'some-field'
            ]);
        $mockPropertyInfoExtractor->expects($this->once())
            ->method('getTypes')
            ->with(EventInterface::class, 'some-field')
            ->willReturn([
                new Type(
                    Type::BUILTIN_TYPE_STRING,
                    false,
                    null,
                    true
                )
            ]);

        $mockClassDiscriminator = $this->createMock(ClassDiscriminatorFromClassMetadata::class);
        $mockClassDiscriminator->expects($this->once())
            ->method('getMappingForMappedObject')
            ->willReturn(
                new ClassDiscriminatorMapping(
                    'type',
                    [
                        Event::UPLOAD->value => EventInterface::class
                    ]
                )
            );

        $manualInputEventBuilder = new ManualInputEventBuilder(
            $mockPropertyInfoExtractor,
            $mockClassDiscriminator,
            $mockSerializer
        );

        $mockQuestionHelper = $this->createMock(QuestionHelper::class);
        $mockQuestionHelper->expects($this->once())
            ->method('ask')
            ->willReturnCallback(
                function (
                    InputInterface $input,
                    OutputInterface $output,
                    Question $question
                ): string {
                    $this->assertEquals(
                        '<question>(1/1) Enter value for "some-field" (type: string):</question>',
                        $question->getQuestion()
                    );
                    return 'mock-input';
                }
            );

        $event = $manualInputEventBuilder->build(
            new ArrayInput(
                [],
                new InputDefinition()
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
            -1000,
            ManualInputEventBuilder::getPriority()
        );
    }

    public static function variedConsoleInputDataProvider(): array
    {
        return [
            [
                new ArrayInput(
                    ['--file' => 'some-path/'],
                    new InputDefinition([
                        new InputOption('file', null, InputOption::VALUE_REQUIRED)
                    ])
                ),
                true
            ],
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
                        new InputOption('file', null, InputOption::VALUE_REQUIRED),
                        new InputOption('fixture', null, InputOption::VALUE_NONE)
                    ])
                ),
                true
            ]
        ];
    }
}
