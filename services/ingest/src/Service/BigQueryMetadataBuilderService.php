<?php

namespace App\Service;

use App\Exception\PersistException;
use App\Model\Coverage;
use App\Model\File;
use App\Model\Line\AbstractLine;
use DateTimeImmutable;
use JsonException;
use Packages\Event\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class BigQueryMetadataBuilderService
{
    public function __construct(
        private readonly LoggerInterface $metadataBuilderServiceLogger,
        private readonly SerializerInterface&NormalizerInterface $serializer
    ) {
    }

    /**
     * Build a row's worth of line coverage data, suitable for insertion into BigQuery.
     */
    public function buildLineCoverageRow(
        Upload $upload,
        int $totalLines,
        Coverage $coverage,
        File $file,
        AbstractLine $line
    ): array {
        return [
            'uploadId' => $upload->getUploadId(),
            'ingestTime' => $upload->getEventTime()->format('Y-m-d H:i:s'),
            'provider' => $upload->getProvider(),
            'owner' => $upload->getOwner(),
            'repository' => $upload->getRepository(),
            'commit' => $upload->getCommit(),
            'parent' => $upload->getParent(),
            'ref' => $upload->getRef(),
            'tag' => $upload->getTag()->getName(),
            'sourceFormat' => $coverage->getSourceFormat(),
            'fileName' => $file->getFileName(),
            'generatedAt' => $coverage->getGeneratedAt() instanceof DateTimeImmutable ?
                $coverage->getGeneratedAt()?->format('Y-m-d H:i:s') :
                null,
            'type' => $line->getType(),
            'lineNumber' => $line->getLineNumber(),
            'totalLines' => $totalLines,
            'metadata' => $this->buildMetadata($line)
        ];
    }

    public function buildUploadRow(Upload $upload): array
    {
        return [
            'uploadId' => $upload->getUploadId(),
            'ingestTime' => $upload->getEventTime()->format('Y-m-d H:i:s'),
            'provider' => $upload->getProvider(),
            'owner' => $upload->getOwner(),
            'repository' => $upload->getRepository(),
            'commit' => $upload->getCommit(),
            'parent' => $upload->getParent(),
            'ref' => $upload->getRef(),
            'tag' => $upload->getTag()->getName()
        ];
    }

    /**
     * Convert a line into a BigQuery-compatible set of metadata.
     */
    public function buildMetadata(AbstractLine $line): array
    {
        /** @var array<array-key, mixed> $line */
        $line = $this->serializer->normalize($line);

        $metadata = array_map(
            fn(mixed $key, mixed $value): ?array => $this->mapMetadataRecord($key, $value),
            array_keys($line),
            array_values($line)
        );

        return array_filter($metadata);
    }

    /**
     * @param (int|string) $key
     *
     * @psalm-param array-key $key
     */
    private function mapMetadataRecord(mixed $key, mixed $value): ?array
    {
        try {
            return [
                'key' => $this->stringifyDataType($key),
                'value' => $this->stringifyDataType($value)
            ];
        } catch (JsonException | PersistException $e) {
            $this->metadataBuilderServiceLogger->critical(
                'Unable to stringify data type for BigQuery',
                [
                    'value' => $value,
                    'exception' => $e,
                ]
            );

            return null;
        }
    }

    /**
     * @throws JsonException
     */
    private function stringifyDataType(mixed $value): string
    {
        return match (gettype($value)) {
            'boolean' => $value ? 'true' : 'false',
            'integer', 'double' => (string)$value,
            'string' => $value,
            'array', 'object' => json_encode($value, JSON_THROW_ON_ERROR),
            'NULL' => '',
            default => throw new PersistException(
                sprintf(
                    'Unsupported type to stringify for BigQuery: %s',
                    gettype($value)
                )
            ),
        };
    }
}
