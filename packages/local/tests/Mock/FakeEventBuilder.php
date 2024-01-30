<?php

namespace Packages\Local\Tests\Mock;

use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\UploadsFinalised;
use Packages\Local\Service\EventBuilderInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FakeEventBuilder implements EventBuilderInterface
{
    #[Override]
    public static function supports(
        InputInterface $input,
        Event $event
    ): bool {
        return true;
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
        return new UploadsFinalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            parent: [],
            eventTime: new DateTimeImmutable('2021-01-01T00:00:00+00:00')
        );
    }
}
