<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Upload;

class PublishableCheckRunMessage implements PublishableMessageInterface
{
    public function __construct(
        private readonly Upload $upload,
        private readonly float $coveragePercentage,
        private readonly DateTimeImmutable $validUntil,
    ) {
    }

    public function getUpload(): Upload
    {
        return $this->upload;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::CheckRun;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public function getCoveragePercentage(): float
    {
        return $this->coveragePercentage;
    }

    public static function from(array $data): self
    {
        $validUntil = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ATOM,
            $data['validUntil'] ?? ''
        );

        if (
            !isset($data['upload']) ||
            !isset($data['coveragePercentage']) ||
            !$validUntil
        ) {
            throw new InvalidArgumentException("Check run message is not valid.");
        }

        return new self(
            Upload::from($data['upload']),
            (float)$data['coveragePercentage'],
            $validUntil
        );
    }

    public function getMessageGroup(): string
    {
        return md5(
            implode('', [
                $this->upload->getOwner(),
                $this->upload->getRepository(),
                $this->upload->getRef(),
                $this->upload->getCommit()
            ])
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType()->value,
            'upload' => $this->upload->jsonSerialize(),
            'coveragePercentage' => $this->coveragePercentage,
            'validUntil' => $this->validUntil->format(DateTimeInterface::ATOM),
        ];
    }

    public function __toString(): string
    {
        return "PublishableCheckRunMessage#{$this->upload->getUploadId()}";
    }
}
