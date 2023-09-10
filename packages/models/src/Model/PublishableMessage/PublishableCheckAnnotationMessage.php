<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Event\Upload;

class PublishableCheckAnnotationMessage implements PublishableMessageInterface
{
    public function __construct(
        private readonly Upload $upload,
        private readonly string $fileName,
        private readonly int $lineNumber,
        private readonly LineState $lineState,
        private readonly DateTimeImmutable $validUntil,
    ) {
    }

    public function getUpload(): Upload
    {
        return $this->upload;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getLineState(): LineState
    {
        return $this->lineState;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::CheckAnnotation;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public static function from(array $data): self
    {
        $validUntil = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ATOM,
            $data['validUntil'] ?? ''
        );

        if (
            !isset($data['upload']) ||
            !isset($data['fileName']) ||
            !isset($data['lineNumber']) ||
            !isset($data['lineState']) ||
            !$validUntil
        ) {
            throw new InvalidArgumentException("Check annotation message is not valid.");
        }

        return new self(
            Upload::from($data['upload']),
            (string)$data['fileName'],
            (int)$data['lineNumber'],
            LineState::from($data['lineState']),
            $validUntil
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType()->value,
            'upload' => $this->upload->jsonSerialize(),
            'fileName' => $this->fileName,
            'lineNumber' => $this->lineNumber,
            'lineState' => $this->lineState->value,
            'validUntil' => $this->validUntil->format(DateTimeInterface::ATOM),
        ];
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
        return "PublishableCheckAnnotationMessage#{$this->upload->getUploadId()}";
    }
}
