<?php

namespace App\Service;

use App\Exception\SigningException;
use App\Model\SignedUrl;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @psalm-type SigningParameters = array{
 *     owner: mixed,
 *     repository: mixed,
 *     fileName: mixed,
 *     pullRequest?: mixed,
 *     commit: mixed,
 *     parent: mixed,
 *     provider: mixed
 * }
 */
class UploadService
{
    private const TARGET_BUCKET = 'coverage-ingest-%s';

    private const EXPIRY_MINUTES = 5;

    private const REQUIRED_FIELDS = [
        'owner',
        'repository',
        'provider',
        'fileName',
        'commit',
        'parent'
    ];

    public function __construct(
        private readonly S3Client $s3Client,
        private readonly EnvironmentService $environmentService,
        private readonly LoggerInterface $uploadLogger
    ) {
    }

    /**
     * @param Request $request
     * @return SigningParameters
     * @throws SigningException
     */
    public function getSigningParametersFromRequest(Request $request): array
    {
        $body = $request->toArray();

        if (!isset($body['data']) || !is_array($body['data'])) {
            $this->uploadLogger->info(
                'No data key found in request body.',
                [
                    'parameters' => $body
                ]
            );

            throw SigningException::invalidPayload(['data']);
        }

        /** @var array{ data: array<array-key, mixed> } $body */
        $parameters = $body['data'];

        $this->uploadLogger->info(
            'Beginning to generate signed url for upload request.',
            [
                'parameters' => $parameters
            ]
        );

        try {
            return $this->validatePayload($parameters);
        } catch (SigningException $exception) {
            $this->uploadLogger->error(
                $exception->getMessage(),
                [
                    'parameters' => $parameters
                ]
            );

            throw $exception;
        }
    }

    public function buildSignedUploadUrl(
        string $owner,
        string $repository,
        string $fileName,
        string|null $pullRequest,
        string $commit,
        string $parent,
        string $provider
    ): SignedUrl {
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        $uploadKey = sprintf(
            '%s/%s/%s/%s.%s',
            $owner,
            $repository,
            $commit,
            Uuid::uuid4()->toString(),
            $fileExtension
        );

        $input = new PutObjectRequest([
            'Bucket' => sprintf(
                self::TARGET_BUCKET,
                $this->environmentService->getEnvironment()->value
            ),
            'Key' => $uploadKey,
            'Metadata' => $this->getMetadata(
                $owner,
                $repository,
                $pullRequest,
                $commit,
                $parent,
                $provider
            )
        ]);

        $expiry = new DateTimeImmutable(sprintf('+%s min', self::EXPIRY_MINUTES));

        return new SignedUrl(
            $this->s3Client->presign(
                $input,
                $expiry,
            ),
            $expiry
        );
    }

    private function getMetadata(
        string $owner,
        string $repository,
        string|null $pullRequest,
        string $commit,
        string $parent,
        string $provider
    ): array {
        $metaData = [
            'owner' => $owner,
            'repository' => $repository,
            'commit' => $commit,
            'parent' => $parent,
            'provider' => $provider
        ];

        if ($pullRequest) {
            $metaData['pullRequest'] = $pullRequest;
        }

        return $metaData;
    }

    /**
     * @param array $parameters
     * @return SigningParameters
     * @throws SigningException
     */
    public function validatePayload(array $parameters): array
    {
        if (array_diff(self::REQUIRED_FIELDS, array_keys($parameters)) === []) {
            /** @var SigningParameters $parameters */
            return $parameters;
        }

        throw SigningException::invalidPayload(array_diff(self::REQUIRED_FIELDS, array_keys($parameters)));
    }
}
