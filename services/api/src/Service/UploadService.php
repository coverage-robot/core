<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\SigningException;
use App\Model\Project;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use AsyncAws\S3\Input\PutObjectRequest;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class UploadService implements UploadServiceInterface
{
    private const string TARGET_BUCKET = 'coverage-ingest-%s';

    private const int EXPIRY_MINUTES = 5;

    public function __construct(
        #[Autowire(service: UploadSignerService::class)]
        private readonly UploadSignerServiceInterface $uploadSignerService,
        private readonly EnvironmentServiceInterface $environmentService,
        #[Autowire(service: UniqueIdGeneratorService::class)]
        private readonly UniqueIdGeneratorServiceInterface $uniqueIdGeneratorService,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer,
        private readonly LoggerInterface $uploadLogger
    ) {
    }

    /**
     * @throws SigningException
     */
    #[Override]
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

            throw SigningException::invalidSignature();
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
            /** @var SigningParameters $parameters */
            $parameters = $this->serializer->denormalize(
                $parameters,
                SigningParameters::class
            );

            return $parameters;
        } catch (SigningException $signingException) {
            $this->uploadLogger->error(
                $signingException->getMessage(),
                [
                    'parameters' => $parameters
                ]
            );

            throw $signingException;
        }
    }

    #[Override]
    public function buildSignedUploadUrl(Project $project, SigningParameters $signingParameters): SignedUrl
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
            $project->getProjectId(),
            $signingParameters
        );

        $expiry = new DateTimeImmutable(sprintf('+%s min', self::EXPIRY_MINUTES));

        return $this->uploadSignerService->sign($uploadId, $input, $expiry);
    }

    private function getSignedPutRequest(
        string $bucket,
        string $key,
        string $uploadId,
        string $projectId,
        SigningParameters $signingParameters
    ): PutObjectRequest {
        /** @var array<string, string> $metadata */
        $metadata = [
            ...(array)$this->serializer->normalize($signingParameters),
            'uploadId' => $uploadId,
            'projectId' => $projectId,
            'parent' => $this->serializer->serialize(
                $signingParameters->getParent(),
                'json'
            )
        ];

        return new PutObjectRequest([
            'Bucket' => $bucket,
            'Key' => $key,
            'Metadata' => $metadata
        ]);
    }
}
