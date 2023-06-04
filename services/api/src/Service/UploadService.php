<?php

namespace App\Service;

use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class UploadService
{
    private const TARGET_BUCKET = 'coverage-ingest-%s';

    private const EXPIRY_MINUTES = 5;

    public function __construct(
        private readonly S3Client $s3Client,
        private readonly EnvironmentService $environmentService,
        private readonly UniqueIdGeneratorService $uniqueIdGeneratorService,
        private readonly LoggerInterface $uploadLogger
    ) {
    }

    /**
     * @throws SigningException
     */
    public function getSigningParametersFromRequest(Request $request): SigningParameters
    {
        $body = $request->toArray();

        if (!isset($body['data']) || !is_array($body['data'])) {
            $this->uploadLogger->info(
                'No data key found in request body.',
                [
                    'parameters' => $body
                ]
            );

            throw SigningException::invalidParameters(
                new InvalidArgumentException('No data key found in request body.')
            );
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
            return $this->getSigningParameters($parameters);
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

    public function buildSignedUploadUrl(SigningParameters $signingParameters): SignedUrl
    {
        $uploadId = $this->uniqueIdGeneratorService->generate();

        $uploadKey = sprintf(
            '%s/%s/%s/%s.%s',
            $signingParameters->getOwner(),
            $signingParameters->getRepository(),
            $signingParameters->getCommit(),
            $uploadId,
            pathinfo($signingParameters->getFileName(), PATHINFO_EXTENSION)
        );

        $input = $this->getSignedPutRequest(
            sprintf(
                self::TARGET_BUCKET,
                $this->environmentService->getEnvironment()->value
            ),
            $uploadKey,
            $uploadId,
            $signingParameters
        );

        $expiry = new DateTimeImmutable(sprintf('+%s min', self::EXPIRY_MINUTES));

        return new SignedUrl(
            $uploadId,
            $this->s3Client->presign(
                $input,
                $expiry,
            ),
            $expiry
        );
    }

    /**
     * @throws SigningException
     */
    private function getSigningParameters(array $parameters): SigningParameters
    {
        return new SigningParameters($parameters);
    }

    /**
     * @throws JsonException
     */
    private function getSignedPutRequest(
        string $bucket,
        string $key,
        string $uploadId,
        SigningParameters $signingParameters
    ): PutObjectRequest {
        return new PutObjectRequest([
            'Bucket' => $bucket,
            'Key' => $key,
            'Metadata' => [
                'uploadid' => $uploadId,
                ...$signingParameters->jsonSerialize()
            ]
        ]);
    }
}
