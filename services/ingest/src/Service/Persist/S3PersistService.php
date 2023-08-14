<?php

namespace App\Service\Persist;

use App\Exception\PersistException;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use JsonException;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class S3PersistService implements PersistServiceInterface
{
    private const OUTPUT_BUCKET = 'coverage-output-%s';

    private const OUTPUT_KEY = '%s%s.json';

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
                        'ContentType' => 'application/json',
                        'Metadata' => [
                            'sourceFormat' => $coverage->getSourceFormat()->value,
                            ...$metadata,
                            'parent' => json_encode($upload->getParent(), JSON_THROW_ON_ERROR)
                        ],
                        'Body' => json_encode($coverage, JSON_THROW_ON_ERROR),
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

    public static function getPriority(): int
    {
        return 0;
    }
}
