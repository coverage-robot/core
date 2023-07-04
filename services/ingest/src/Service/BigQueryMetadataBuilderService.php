<?php

namespace App\Service;

use App\Exception\PersistException;
use JsonException;
use Packages\Models\Model\Line\AbstractLine;
use Psr\Log\LoggerInterface;

class BigQueryMetadataBuilderService
{
    public function __construct(
        private readonly LoggerInterface $metadataBuilderServiceLogger
    ) {
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

        return array_filter($metadata, static fn(?array $record) => $record !== null);
    }

    private function mapMetadataRecord(mixed $key, mixed $value): ?array
    {
        try {
            return  [
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
