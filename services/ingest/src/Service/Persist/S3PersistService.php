<?php

namespace App\Service\Persist;

use App\Exception\PersistException;
use App\Model\Project;
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
     * @param Project $project
     * @param string $uniqueId
     * @throws JsonException
     */
    public function persist(Project $project, string $uniqueId): bool
    {
        try {
            $response = $this->s3Client->putObject(
                new PutObjectRequest(
                    [
                        'Bucket' => sprintf(self::OUTPUT_BUCKET, $this->environmentService->getEnvironment()->value),
                        'Key' => sprintf(self::OUTPUT_KEY, '', $uniqueId),
                        'ContentType' => 'application/json',
                        'Metadata' => [
                            'sourceFormat' => $project->getSourceFormat()->name
                        ],
                        'Body' => json_encode($project, JSON_THROW_ON_ERROR),
                    ]
                )
            );

            $response->resolve();

            return $response->info()['status'] === Response::HTTP_OK;
        } catch (HttpException $exception) {
            throw PersistException::from($exception);
        }
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
