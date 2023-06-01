<?php

namespace App\Tests\Service;

use App\Enum\EnvironmentEnum;
use App\Exception\SigningException;
use App\Model\SigningParameters;
use App\Service\EnvironmentService;
use App\Service\UploadService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use AsyncAws\S3\S3Client;
use Monolog\DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

class UploadServiceTest extends TestCase
{
    #[DataProvider('signingParametersDataProvider')]
    public function testGetSigningParametersFromRequest(array $parameters, bool $isParameterSetValid): void
    {
        $uploadService = new UploadService(
            $this->createMock(S3Client::class),
            $this->createMock(EnvironmentService::class),
            new NullLogger()
        );

        if (!$isParameterSetValid) {
            $this->expectException(SigningException::class);
        }

        $request = new Request([], [], [], [], [], [], json_encode(['data' => $parameters]));

        $signingParameters = $uploadService->getSigningParametersFromRequest($request);

        $this->assertEquals(new SigningParameters($parameters), $signingParameters);
    }

    public function testBuildSignedUploadUrl(): void
    {
        $s3Client = new S3Client();

        $uploadService = new UploadService(
            $s3Client,
            MockEnvironmentServiceFactory::getMock($this, EnvironmentEnum::PRODUCTION),
            new NullLogger()
        );

        $signedUrl = $uploadService->buildSignedUploadUrl(
            new SigningParameters([
                'owner' => '1',
                'repository' => 'a',
                'commit' => 2,
                'pullRequest' => 12,
                'parent' => 'd',
                'provider' => 'github',
                'fileName' => 'test.xml',
                'tag' => 'frontend'
            ])
        );

        $this->assertGreaterThan(new DateTimeImmutable('+5 min'), $signedUrl->getExpiration());

        $url = parse_url($signedUrl->getSignedUrl());
        $this->assertEquals('coverage-ingest-prod.s3.amazonaws.com', $url['host']);

        parse_str($url['query'], $metadata);
        $this->assertEquals('frontend', $metadata['x-amz-meta-tag']);
        $this->assertEquals('1', $metadata['x-amz-meta-owner']);
        $this->assertEquals('a', $metadata['x-amz-meta-repository']);
        $this->assertEquals('2', $metadata['x-amz-meta-commit']);
        $this->assertEquals('12', $metadata['x-amz-meta-pullrequest']);
        $this->assertEquals('d', $metadata['x-amz-meta-parent']);
        $this->assertEquals('github', $metadata['x-amz-meta-provider']);
    }

    public static function signingParametersDataProvider(): array
    {
        return [
            'With pull request' => [
                [
                    'owner' => '1',
                    'repository' => 'a',
                    'commit' => 2,
                    'pullRequest' => 12,
                    'parent' => 'd',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                true
            ],
            'Without to pull request' => [
                [
                    'owner' => '1',
                    'repository' => 'a',
                    'commit' => 2,
                    'parent' => 'd',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'backend'
                ],
                true
            ],
            'Without commit' => [
                [
                    'owner' => '1',
                    'repository' => 'a',
                    'parent' => 'd',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                false
            ],
            'Without to file name' => [
                [
                    'owner' => '1',
                    'repository' => 'a',
                    'commit' => 2,
                    'parent' => 'd',
                    'provider' => 'github',
                    'tag' => 'backend'
                ],
                false
            ],
            'Without owner or repository' => [
                [
                    'commit' => 2,
                    'parent' => 'd',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                false
            ],
            'Without tag' => [
                [
                    'owner' => '1',
                    'repository' => 'a',
                    'commit' => 2,
                    'pullRequest' => 12,
                    'parent' => 'd',
                    'provider' => 'github',
                    'fileName' => 'test.xml'
                ],
                false
            ],
        ];
    }
}
