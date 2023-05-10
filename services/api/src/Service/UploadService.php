<?php

namespace App\Service;

use App\Model\SignedUrl;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;

class UploadService
{
    private const TARGET_BUCKET = 'coverage-ingest-%s';

    private const EXPIRY_MINUTES = 30;

    public function __construct(
        private readonly S3Client $s3Client,
        private readonly EnvironmentService $environmentService
    ) {
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

        $uploadKey = sprintf('%s/%s/%s.%s', $owner, $repository, $commit, $fileExtension);

        $input = new PutObjectRequest([
            'Bucket' => sprintf(
                self::TARGET_BUCKET,
                $this->environmentService->getEnvironment()->value
            ),
            'Key' => $uploadKey,
            'Metadata' => [
                'owner' => $owner,
                'repository' => $repository,
                'pullrequest' => $pullRequest,
                'commit' => $commit,
                'parent' => $parent,
                'provider' => $provider
            ]
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

    public function validatePayload(array $payload): bool
    {
        if (
            isset($payload['owner']) &&
            isset($payload['repository']) &&
            isset($payload['fileName']) &&
            isset($payload['commit']) &&
            isset($payload['parent']) &&
            isset($payload['provider'])
        ) {
            return true;
        }

        return false;
    }
}
