<?php

namespace Packages\Models\Model\PublishableMessage;

use Countable;
use DateTimeInterface;
use InvalidArgumentException;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Upload;

class PublishableCheckAnnotationMessageCollection implements PublishableMessageInterface, Countable
{
    /**
     * @var PublishableCheckAnnotationMessage[] $annotations
     */
    private readonly array $annotations;

    /**
     * @param PublishableCheckAnnotationMessage[] $annotations
     */
    public function __construct(
        private readonly Upload $upload,
        array $annotations,
    ) {
        $this->annotations = array_filter(
            array_map(
                static function (array $message) {
                    try {
                        return PublishableCheckAnnotationMessage::from($message);
                    } catch (InvalidArgumentException) {
                        return null;
                    }
                },
                $annotations
            )
        );
    }

    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function getUpload(): Upload
    {
        return $this->upload;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::Collection;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return max(
            ...array_map(
                static fn(PublishableCheckAnnotationMessage $message) => $message->getValidUntil(),
                $this->annotations
            )
        );
    }

    public static function from(array $data): self
    {
        if (!isset($data['upload'])) {
            throw new InvalidArgumentException(
                'An upload must be provided for a collection of check annotation messages.'
            );
        }

        $collection = new self(
            Upload::from($data['upload']),
            $data
        );

        if (count($collection !== count($data))) {
            throw new InvalidArgumentException('At least one invalid message has been provided.');
        }

        return $collection;
    }

    public function jsonSerialize(): array
    {
        return array_map(
            static fn(PublishableCheckAnnotationMessage $message) => (array)$message->jsonSerialize(),
            $this->annotations
        );
    }

    public function getMessageGroup(): string
    {
        return md5(
            implode('', [
                $this->upload->getOwner(),
                $this->upload->getRepository(),
                $this->upload->getRef(),
                $this->upload->getPullRequest(),
                $this->upload->getCommit()
            ])
        );
    }

    public function __toString(): string
    {
        return "PublishableCheckAnnotationMessageCollection#{$this->getValidUntil()->format('Y-m-d H:i:s')}";
    }

    public function count(): int
    {
        return count($this->annotations);
    }
}
