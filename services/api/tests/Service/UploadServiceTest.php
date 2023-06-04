<?php

namespace App\Tests\Service;

use App\Enum\EnvironmentEnum;
use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use App\Service\EnvironmentService;
use App\Service\UniqueIdGeneratorService;
use App\Service\UploadService;
use App\Service\UploadSignerService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use AsyncAws\S3\Input\PutObjectRequest;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

class UploadServiceTest extends TestCase
{
    #[DataProvider('signingParametersDataProvider')]
    public function testGetSigningParametersFromRequest(array $parameters, ?SigningParameters $expectedParameters): void
    {
        $uploadService = new UploadService(
            $this->createMock(UploadSignerService::class),
            $this->createMock(EnvironmentService::class),
            $this->createMock(UniqueIdGeneratorService::class),
            new NullLogger()
        );

        if (!$expectedParameters) {
            $this->expectException(SigningException::class);
        }

        $request = new Request([], [], [], [], [], [], json_encode(['data' => $parameters]));

        $signingParameters = $uploadService->getSigningParametersFromRequest($request);

        $this->assertEquals($expectedParameters, $signingParameters);
    }

    #[DataProvider('signingParametersDataProvider')]
    public function testSignedParentIsJsonEncoded(array $parameters, ?SigningParameters $expectedParameters): void
    {
        $uploadService = new UploadService(
            $this->createMock(UploadSignerService::class),
            $this->createMock(EnvironmentService::class),
            $this->createMock(UniqueIdGeneratorService::class),
            new NullLogger()
        );

        $request = new Request([], [], [], [], [], [], json_encode(['data' => $parameters]));

        if (!$expectedParameters) {
            $this->expectException(SigningException::class);
        }

        $signingParameters = $uploadService->getSigningParametersFromRequest($request);

        $parent = $signingParameters->jsonSerialize()['parent'];

        $this->assertJson($parent);
        $this->assertEquals(json_encode((array)$parameters['parent']), $parent);
    }

    public function testBuildSignedUploadUrl(): void
    {
        $mockUniqueIdGeneratorService = $this->createMock(UniqueIdGeneratorService::class);
        $mockUniqueIdGeneratorService->expects($this->once())
            ->method('generate')
            ->willReturn('mock-uuid');

        $mockUploadSignerService = $this->createMock(UploadSignerService::class);

        $mockUploadSignerService->expects($this->once())
            ->method('sign')
            ->with(
                'mock-uuid',
                new PutObjectRequest([
                    'Bucket' => 'coverage-ingest-prod',
                    'Key' => '1/a/2/mock-uuid.xml',
                    'Metadata' => [
                        'owner' => '1',
                        'repository' => 'a',
                        'commit' => '2',
                        'parent' => '["d"]',
                        'tag' => 'frontend',
                        'provider' => 'github',
                        'fileName' => 'test.xml',
                        'pullRequest' => '12',
                        'uploadid' => 'mock-uuid'
                    ]
                ]),
                $this->isInstanceOf(DateTimeImmutable::class)
            )
            ->willReturn($this->createMock(SignedUrl::class));

        $uploadService = new UploadService(
            $mockUploadSignerService,
            MockEnvironmentServiceFactory::getMock($this, EnvironmentEnum::PRODUCTION),
            $mockUniqueIdGeneratorService,
            new NullLogger()
        );

        $uploadService->buildSignedUploadUrl(
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
                new SigningParameters([
                        'owner' => '1',
                        'repository' => 'a',
                        'commit' => 2,
                        'parent' => 'd',
                        'provider' => 'github',
                        'fileName' => 'test.xml',
                        'tag' => 'backend'
                ])
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
                null
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
                null
            ],
            'Without owner or repository' => [
                [
                    'commit' => 2,
                    'parent' => 'd',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                null
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
                null
            ],
            'Multiple parents' => [
                [
                    'owner' => '1',
                    'repository' => 'a',
                    'commit' => 2,
                    'pullRequest' => 12,
                    'parent' => ['d', 'e'],
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                new SigningParameters([
                    'owner' => '1',
                    'repository' => 'a',
                    'commit' => 2,
                    'pullRequest' => 12,
                    'parent' => ['d', 'e'],
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ])
            ],
        ];
    }
}
