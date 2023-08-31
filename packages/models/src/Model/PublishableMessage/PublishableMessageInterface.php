<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeInterface;
use JsonSerializable;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Upload;
use Stringable;

interface PublishableMessageInterface extends JsonSerializable, Stringable
{
    /**
     * Get the upload the message is was generated for.
     *
     * Its not always the case that a message is associated to a specific upload - for example, a collection of messages ({@see PublishableMessageCollection})
     * might be for different ones.
     *
     * @return Upload|null
     */
    public function getUpload(): Upload|null;

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
     * Determine the type of message when its serialised.
     */
    public function getType(): PublishableMessage;

    /**
     * Create a message from an array of data - generally a serialised array of data from a queue.
     */
    public static function from(array $data): self;
}
