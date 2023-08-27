<?php

namespace App\Service\Persist;

use App\Exception\PersistException;
use App\Service\EnvironmentService;
use AsyncAws\SimpleS3\SimpleS3Client;
use DateTimeInterface;
use JsonException;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class S3PersistService implements PersistServiceInterface
{
    private const OUTPUT_BUCKET = 'coverage-output-%s';

    private const OUTPUT_KEY = '%s%s.txt';

    public function __construct(
        private readonly SimpleS3Client $s3Client,
        private readonly EnvironmentService $environmentService,
        private readonly LoggerInterface $s3PersistServiceLogger
    ) {
    }

    /**
     * @param Upload $upload
     * @param Coverage $coverage
     * @return bool
     * @throws JsonException
     */
    public function persist(Upload $upload, Coverage $coverage): bool
    {
        /** @var array<string, string> $metadata */
        $metadata = $upload->jsonSerialize();

        $body = $this->getBody($coverage);

        $this->s3Client->upload(
            sprintf(self::OUTPUT_BUCKET, $this->environmentService->getEnvironment()->value),
            sprintf(self::OUTPUT_KEY, '', $upload->getUploadId()),
            $body,
            [
                // We can't directly encode and upload the file, as its entirely possible it'll be too big
                // to fit in memory. Instead, we need to pass a resource to the request
                'ContentLength' => $this->getContentLength($body),
                'ContentType' => 'text/plain',
                'Metadata' => [
                    'sourceFormat' => $coverage->getSourceFormat()->value,
                    ...$metadata,
                    'parent' => json_encode($upload->getParent(), JSON_THROW_ON_ERROR)
                ]
            ]
        );

        $this->s3PersistServiceLogger->info(
            sprintf(
                'Persisting %s to S3 has finished',
                (string)$upload
            )
        );

        return true;
    }

    /**
     * Build a stream for the body of the S3 object.
     *
     * It's important this method is memory efficient, and doesnt try to directly encode the coverage object, as it's
     * entirely possible it'll be too big to fit in memory as a string.
     *
     * This method will encode the core properties of the coverage file first, and then begin to yield each individual
     * file (and its lines) one by one.
     *
     * Once fully yielded, the output will look something like:
     *
     * ```
     * >> SourceFormat: CLOVER, GeneratedAt: 2023-08-26T11:53:40+00:00, ProjectRoot: /project/root/, TotalFiles: 4115
     *
     * > FileName: src/folder/SomeFile.php, TotalLines: 11
     * Type: METHOD, LineNumber: 14, LineHits: 0, Name: __construct
     * Type: STATEMENT, LineNumber: 17, LineHits: 0
     * Type: METHOD, LineNumber: 20, LineHits: 0, Name: methodOne
     * Type: STATEMENT, LineNumber: 22, LineHits: 0
     * Type: METHOD, LineNumber: 25, LineHits: 0, Name: methodTwo
     * Type: STATEMENT, LineNumber: 27, LineHits: 0
     * Type: STATEMENT, LineNumber: 29, LineHits: 0
     *
     * > FileName: src/folder-two/SomeOtherFile.php, TotalLines: 81
     * Type: METHOD, LineNumber: 37, LineHits: 0, Name: __construct
     * Type: STATEMENT, LineNumber: 41, LineHits: 0
     * Type: STATEMENT, LineNumber: 42, LineHits: 0
     * Type: METHOD, LineNumber: 52, LineHits: 0, Name: resolve
     * Type: STATEMENT, LineNumber: 55, LineHits: 0
     * ```
     * @return resource
     * @throws JsonException
     */
    public function getBody(Coverage $coverage)
    {
        $buffer = fopen('php://temp', 'rw+');

        if (!$buffer) {
            throw new PersistException('Unable to open buffer for writing S3 stream to.');
        }

        fwrite(
            $buffer,
            sprintf(
                ">> SourceFormat: %s, GeneratedAt: %s, ProjectRoot: %s, TotalFiles: %s\n",
                $coverage->getSourceFormat()->value,
                $coverage->getGeneratedAt()?->format(DateTimeInterface::ATOM) ?? 'unknown',
                $coverage->getRoot(),
                count($coverage),
            )
        );

        foreach ($coverage->getFiles() as $file) {
            fwrite(
                $buffer,
                sprintf(
                    "\n> FileName: %s, TotalLines: %s\n",
                    $file->getFileName(),
                    count($file)
                )
            );

            foreach ($file->getAllLines() as $line) {
                $line = $line->jsonSerialize();

                fwrite(
                    $buffer,
                    implode(
                        ', ',
                        array_map(
                        /**
                         * @param array-key $key
                         * @throws JsonException
                         */
                            static fn(string $key, string|array $value) => sprintf(
                                '%s: %s',
                                ucfirst((string)$key),
                                json_encode($value, JSON_THROW_ON_ERROR)
                            ),
                            array_keys($line),
                            array_values($line)
                        )
                    ) . "\n"
                );
            }
        }

        return $buffer;
    }

    /**
     * We need to be able to tell S3 how big the file is going to be once all of the chunks have
     * been streamed.
     *
     * This method returns the total length the fully streamed file.
     *
     * @param resource $buffer
     */
    public function getContentLength($buffer): int
    {
        return fstat($buffer)['size'] ?? 0;
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
