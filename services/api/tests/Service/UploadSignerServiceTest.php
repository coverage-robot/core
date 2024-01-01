<?php

namespace App\Tests\Service;

use App\Client\PresignableClientInterface;
use App\Model\SignedUrl;
use App\Service\UploadSignerService;
use AsyncAws\S3\Input\PutObjectRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UploadSignerServiceTest extends TestCase
{
    public function testSignRequest(): void
    {
        $mockClient = $this->createMock(PresignableClientInterface::class);

        $expiry = new DateTimeImmutable('+1 hour');
        $input = new PutObjectRequest();

        $mockClient->expects($this->once())
            ->method('presign')
            ->with(
                $input,
                $expiry
            )
            ->willReturn('https://mock-signed-url.com');

        $uploadSignerService = new UploadSignerService(
            $mockClient,
            $this->createMock(ValidatorInterface::class)
        );

        $signedUpload = $uploadSignerService->sign(
            'mock-upload-id',
            $input,
            $expiry
        );

        $this->assertInstanceOf(SignedUrl::class, $signedUpload);

        $this->assertEquals('mock-upload-id', $signedUpload->getUploadId());
        $this->assertEquals($expiry, $signedUpload->getExpiration());
        $this->assertEquals('https://mock-signed-url.com', $signedUpload->getSignedUrl());
    }
}
