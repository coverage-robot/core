<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Upload;

class PublishablePullRequestMessage implements PublishableMessageInterface
{
    public function __construct(
        private readonly Upload $upload,
        private readonly float $coveragePercentage,
        private readonly float $diffCoveragePercentage,
        private readonly int $totalUploads,
        private readonly array $tagCoverage,
        private readonly array $leastCoveredDiffFiles,
        private readonly DateTimeImmutable $validUntil,
    ) {
    }

    public function getUpload(): Upload
    {
        return $this->upload;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::PullRequest;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public function getMessageGroup(): string
    {
        return md5(
            implode('', [
                $this->upload->getOwner(),
                $this->upload->getRepository(),
                $this->upload->getPullRequest()
            ])
        );
    }

    public function getCoveragePercentage(): float
    {
        return $this->coveragePercentage;
    }

    public function getDiffCoveragePercentage(): float
    {
        return $this->diffCoveragePercentage;
    }

    public function getTotalUploads(): int
    {
        return $this->totalUploads;
    }

    public function getTagCoverage(): array
    {
        return $this->tagCoverage;
    }

    public function getLeastCoveredDiffFiles(): array
    {
        return $this->leastCoveredDiffFiles;
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
            !isset($data['diffCoveragePercentage']) ||
            !isset($data['totalUploads']) ||
            !isset($data['tagCoverage']) ||
            !isset($data['leastCoveredDiffFiles']) ||
            !$validUntil
        ) {
            throw new InvalidArgumentException("Pull request message is not valid.");
        }

        return new self(
            Upload::from($data['upload']),
            (float)$data['coveragePercentage'],
            (float)$data['diffCoveragePercentage'],
            (int)$data['totalUploads'],
            (array)$data['tagCoverage'],
            (array)$data['leastCoveredDiffFiles'],
            $validUntil
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType()->value,
            'upload' => $this->upload->jsonSerialize(),
            'coveragePercentage' => $this->coveragePercentage,
            'diffCoveragePercentage' => $this->diffCoveragePercentage,
            'totalUploads' => $this->totalUploads,
            'tagCoverage' => $this->tagCoverage,
            'leastCoveredDiffFiles' => $this->leastCoveredDiffFiles,
            'validUntil' => $this->validUntil->format(DateTimeInterface::ATOM),
        ];
    }

    public function __toString(): string
    {
        return "PublishablePullRequestMessage#{$this->upload->getUploadId()}";
    }
}
