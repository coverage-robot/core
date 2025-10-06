<?php

declare(strict_types=1);

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

final readonly class BigQueryMetadataBuilderService
{
    public function __construct(
        private LoggerInterface $metadataBuilderServiceLogger,
        private SerializerInterface&NormalizerInterface $serializer
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
                $coverage->getGeneratedAt()->format('Y-m-d H:i:s') :
                null,
            'type' => $line->getType(),
            'lineNumber' => $line->getLineNumber(),
            'totalLines' => $totalLines,
            'metadata' => $this->buildMetadata($line)
        ];
    }

    public function buildUploadRow(Upload $upload, Coverage $coverage, int $totalLines): array
    {
        return [
            'uploadId' => $upload->getUploadId(),
            'ingestTime' => $upload->getEventTime()->format('Y-m-d H:i:s'),
            'provider' => $upload->getProvider(),
            'projectId' => $upload->getProjectId(),
            'owner' => $upload->getOwner(),
            'repository' => $upload->getRepository(),
            'commit' => $upload->getCommit(),
            'parent' => $upload->getParent(),
            'ref' => $upload->getRef(),
            'tag' => $upload->getTag()->getName(),
            'totalLines' => $totalLines,
            'sourceFormat' => $coverage->getSourceFormat(),
            'generatedAt' => $coverage->getGeneratedAt() instanceof DateTimeImmutable ?
                $coverage->getGeneratedAt()->format('Y-m-d H:i:s') :
                null,
        ];
    }

    /**
     * Convert a line into a BigQuery-compatible set of metadata.
     */
    public function buildMetadata(AbstractLine $line): array
    {
        /** @var array<array-key, int|string> $line */
        $line = $this->serializer->normalize($line);

        $metadata = array_map(
            fn(mixed $key, null|int|string|float|bool|object|array $value): ?array =>
                $this->mapMetadataRecord($key, $value),
            array_keys($line),
            array_values($line)
        );

        return array_filter($metadata);
    }

    /**
     * @param array-key $key
     * @param (array-key|null|int|float|string|bool|object|array) $value
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
     *
     * @param array-key|null|int|float|string|bool|object|array $value
     */
    private function stringifyDataType(mixed $value): string
    {
        return match (gettype($value)) {
            'NULL' => '',
            'boolean' => $value ? 'true' : 'false',
            'integer', 'double' => (string)$value,
            'string' => $value,
            'array', 'object' => json_encode($value, JSON_THROW_ON_ERROR),
            default => throw new PersistException(
                sprintf(
                    'Unsupported type to stringify for BigQuery: %s',
                    gettype($value)
                )
            ),
        };
    }
}
