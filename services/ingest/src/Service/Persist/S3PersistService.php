<?php

namespace App\Service\Persist;

use App\Exception\PersistException;
use App\Model\Upload;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class S3PersistService implements PersistServiceInterface
{
    private const OUTPUT_BUCKET = 'coverage-output-%s';

    private const OUTPUT_KEY = '%s%s.json';

    public function __construct(
        private readonly S3Client $s3Client,
        private readonly EnvironmentService $environmentService,
        private readonly LoggerInterface $persistServiceLogger
    ) {
    }

    /**
     * @param Upload $upload
     * @return bool
     * @throws JsonException
     */
    public function persist(Upload $upload): bool
    {
        try {
            $project = $upload->getProject();

            $response = $this->s3Client->putObject(
                new PutObjectRequest(
                    [
                        'Bucket' => sprintf(self::OUTPUT_BUCKET, $this->environmentService->getEnvironment()->value),
                        'Key' => sprintf(self::OUTPUT_KEY, '', $upload->getUploadId()),
                        'ContentType' => 'application/json',
                        'Metadata' => [
                            'sourceFormat' => $project->getSourceFormat()->value,
                            ...$upload->jsonSerialize()
                        ],
                        'Body' => json_encode($project, JSON_THROW_ON_ERROR),
                    ]
                )
            );

            $response->resolve();

            $this->persistServiceLogger->info(
                sprintf(
                    'Persisting %s into BigQuery was %s',
                    (string)$upload,
                    $response->info()['status'] === Response::HTTP_OK ? 'successful' : 'failed'
                )
            );

            return $response->info()['status'] === Response::HTTP_OK;
        } catch (HttpException $exception) {
            $this->persistServiceLogger->info(
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
