<?php

namespace Packages\Local\Service;

use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('package.local.event_builder')]
interface EventBuilderInterface
{
    /**
     * Determine whether the event builder supports the given event using the inputs
     * provided from the console.
     */
    public static function supports(InputInterface $input, Event $event): bool;

    /**
     * Get the priority of the event builder. The higher the priority, the earlier
     * it will be evaluated.
     */
    public static function getPriority(): int;

    /**
     * Build the event using the inputs provided from the console.
     */
    public function build(
        InputInterface $input,
        OutputInterface $output,
        ?HelperSet $helperSet,
        Event $event
    ): EventInterface;
}
