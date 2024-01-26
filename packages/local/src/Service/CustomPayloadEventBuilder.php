<?php

namespace Packages\Local\Service;

use Override;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CustomPayloadEventBuilder implements EventBuilderInterface
{
    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
    ) {
    }

    #[Override]
    public static function supports(InputInterface $input, Event $event): bool
    {
        return (bool)$input->getOption('file') === true;
    }

    #[Override]
    public static function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function build(
        InputInterface $input,
        OutputInterface $output,
        ?HelperSet $helperSet,
        Event $event
    ): EventInterface {
        $filePath = $input->getOption('file');

        return $this->serializer->denormalize(
            array_merge(
                [
                    'type' => $event->value
                ],
                json_decode(
                    file_get_contents($filePath),
                    true
                )
            ),
            EventInterface::class
        );
    }
}
