<?php

namespace Packages\Contracts\PublishableMessage;

use DateTimeInterface;
use Packages\Contracts\Event\EventInterface;
use Stringable;

interface PublishableMessageInterface extends Stringable
{
    /**
     * Get the event the message is was generated for (this may be an upload, or some
     * other event, like a pipeline finishing).
     *
     * Its not always the case that a message is associated to a specific upload - for
     * example, a collection of messages ({@see PublishableMessageCollection}) might be
     * for different ones.
     *
     * @return EventInterface|null
     */
    public function getEvent(): EventInterface|null;

    /**
     * The message _must_ provide a hash which can uniquely group messages of the same context together.
     *
     * For example, this is most commonly for grouping messages which are for different uploads, but
     * the same destination (e.g. Github Pull Request). This allows us to keep the messages atomic and ensure
     * any messages are fanned in when landing on a destination which needs atomicity.
     */
    public function getMessageGroup(): string;

    /**
     * The message _must_ provide a way of identifying the validity period, where in which, messages after that are
     * more up to date.
     *
     * For example, a pull request message is only valid until another pull request message (with a later date
     * is created).
     */
    public function getValidUntil(): DateTimeInterface;

    /**
     * The type of message that's being published.
     *
     * This represents the medium in which the information is going to be shown in. For example,
     * that could be a Pull Request comment, or a Check Run, or a singular Check Annotation.
     */
    public function getType(): PublishableMessage;
}
