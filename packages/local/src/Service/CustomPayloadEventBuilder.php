<?php

declare(strict_types=1);

namespace Packages\Local\Service;

use Override;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class CustomPayloadEventBuilder implements EventBuilderInterface
{
    public function __construct(
        private SerializerInterface&DenormalizerInterface $serializer,
    ) {
    }

    #[Override]
    public static function supports(InputInterface $input, Event $event): bool
    {
        return (bool)$input->getOption('file');
    }

    #[Override]
    public static function getPriority(): int
    {
        return 0;
    }

    /**
     * @throws ExceptionInterface
     */
    #[Override]
    public function build(
        InputInterface $input,
        OutputInterface $output,
        HelperSet $helperSet,
        Event $event
    ): EventInterface {
        /** @var string $filePath */
        $filePath = $input->getOption('file');

        /** @var array $file */
        $file = json_decode(
            (string)file_get_contents($filePath),
            true,
            JSON_THROW_ON_ERROR
        );

        /** @var EventInterface $eventModel */
        $eventModel = $this->serializer->denormalize(
            array_merge(
                [
                    'type' => $event->value
                ],
                $file
            ),
            EventInterface::class
        );

        return $eventModel;
    }
}
