<?php

namespace App\Service\Persist;

use App\Exception\PersistException;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use DateTimeInterface;
use JsonException;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class S3PersistService implements PersistServiceInterface
{
    private const OUTPUT_BUCKET = 'coverage-output-%s';

    private const OUTPUT_KEY = '%s%s.txt';

    public function __construct(
        private readonly S3Client $s3Client,
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
        try {
            /** @var array<string, string> $metadata */
            $metadata = $upload->jsonSerialize();

            $response = $this->s3Client->putObject(
                new PutObjectRequest(
                    [
                        'Bucket' => sprintf(self::OUTPUT_BUCKET, $this->environmentService->getEnvironment()->value),
                        'Key' => sprintf(self::OUTPUT_KEY, '', $upload->getUploadId()),
                        'ContentType' => 'text/plain',
                        'Metadata' => [
                            'sourceFormat' => $coverage->getSourceFormat()->value,
                            ...$metadata,
                            'parent' => json_encode($upload->getParent(), JSON_THROW_ON_ERROR)
                        ],

                        // We can't directly encode and upload the file, as its entirely possible it'll be too big
                        // to fit in memory. Instead, we need stream it in chunks, which requires a content length to be
                        // provided.
                        'ContentLength' => $this->getContentLength($coverage),
                        'Body' => $this->getBody($coverage),
                    ]
                )
            );

            $response->resolve();

            $this->s3PersistServiceLogger->info(
                sprintf(
                    'Persisting %s into BigQuery was %s',
                    (string)$upload,
                    $response->info()['status'] === Response::HTTP_OK ? 'successful' : 'failed'
                )
            );

            return $response->info()['status'] === Response::HTTP_OK;
        } catch (HttpException $exception) {
            $this->s3PersistServiceLogger->info(
                sprintf(
                    'Exception while persisting %s into S3',
                    (string)$upload
                ),
                [
                    'exception' => $exception
                ]
            );

            throw PersistException::from($exception);
        }
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
     * @return iterable<mixed, string>
     * @throws JsonException
     */
    public function getBody(Coverage $coverage): iterable
    {
        yield sprintf(
            ">> SourceFormat: %s, GeneratedAt: %s, ProjectRoot: %s, TotalFiles: %s\n",
            $coverage->getSourceFormat()->value,
            $coverage->getGeneratedAt()?->format(DateTimeInterface::ATOM) ?? 'unknown',
            $coverage->getRoot(),
            count($coverage),
        );

        foreach ($coverage->getFiles() as $file) {
            yield sprintf(
                "\n> FileName: %s, TotalLines: %s\n",
                $file->getFileName(),
                count($file)
            );

            foreach ($file->getAllLines() as $line) {
                $line = $line->jsonSerialize();

                yield implode(
                    ', ',
                    array_map(
                        /**
                         * @param array-key $key
                         * @throws JsonException
                         */
                        static fn (string $key, string|array $value) =>
                            sprintf('%s: %s', ucfirst((string)$key), json_encode($value, JSON_THROW_ON_ERROR)),
                        array_keys($line),
                        array_values($line)
                    )
                ) . "\n";
            }
        }
    }

    /**
     * We need to be able to tell S3 how big the file is going to be once all of the chunks have
     * been streamed.
     *
     * This method acts as an accumulator (ensuring the entire coverage file is never stored directly
     * in memory), and return the total length of the fully streamed file will be.
     */
    public function getContentLength(Coverage $coverage): int
    {
        $contentLength = 0;

        foreach ($this->getBody($coverage) as $line) {
            $contentLength += mb_strlen($line);
        }

        return $contentLength;
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
