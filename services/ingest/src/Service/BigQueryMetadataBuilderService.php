<?php

namespace App\Service;

use App\Exception\PersistException;
use JsonException;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\AbstractLine;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class BigQueryMetadataBuilderService
{
    public function __construct(
        private readonly LoggerInterface $metadataBuilderServiceLogger
    ) {
    }

    /**
     * Build a row's worth of line coverage data, suitable for insertion into BigQuery.
     */
    public function buildRow(Upload $upload, int $totalLines, Coverage $coverage, File $file, AbstractLine $line): array
    {
        return [
            'uploadId' => $upload->getUploadId(),
            'ingestTime' => $upload->getIngestTime()->format('Y-m-d H:i:s'),
            'provider' => $upload->getProvider()->value,
            'owner' => $upload->getOwner(),
            'repository' => $upload->getRepository(),
            'commit' => $upload->getCommit(),
            'parent' => $upload->getParent(),
            'ref' => $upload->getRef(),
            'tag' => $upload->getTag()->getName(),
            'sourceFormat' => $coverage->getSourceFormat(),
            'fileName' => $file->getFileName(),
            'generatedAt' => $coverage->getGeneratedAt() ?
                $coverage->getGeneratedAt()?->format('Y-m-d H:i:s') :
                null,
            'type' => $line->getType(),
            'lineNumber' => $line->getLineNumber(),
            'totalLines' => $totalLines,
            'metadata' => $this->buildMetadata($line)
        ];
    }

    /**
     * Convert a line into a BigQuery-compatible set of metadata.
     */
    public function buildMetadata(AbstractLine $line): array
    {
        $metadata = array_map(
            function (mixed $key, mixed $value): ?array {
                return $this->mapMetadataRecord($key, $value);
            },
            array_keys($line->jsonSerialize()),
            array_values($line->jsonSerialize())
        );

        return array_filter($metadata);
    }

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
     * @throws PersistException
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
