<?php

namespace App\Service;

use App\Client\PresignableClientInterface;
use App\Model\SignedUrl;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UploadSignerService implements UploadSignerServiceInterface
{
    public function __construct(
        #[Autowire(service: S3Client::class)]
        private readonly S3Client|PresignableClientInterface $client,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Override]
    public function sign(string $uploadId, PutObjectRequest $input, DateTimeImmutable $expiry): SignedUrl
    {
        $signedUrl = new SignedUrl(
            $uploadId,
            $this->signRequest($input, $expiry),
            $expiry
        );

        $errors = $this->validator->validate($signedUrl);

        if ($errors->count() === 0) {
            return $signedUrl;
        }

         throw new RuntimeException(
             sprintf(
                 'Unable to sign upload: %s',
                 (string)$errors
             )
         );
    }

    /**
     * Sign the S3 PUT request, so that it can be returned, and then used to
     * upload the coverage file to S3.
     */
    private function signRequest(PutObjectRequest $input, DateTimeImmutable $expiry): string
    {
        return $this->client->presign(
            $input,
            $expiry,
        );
    }
}
