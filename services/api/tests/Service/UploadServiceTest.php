<?php

namespace App\Tests\Service;

use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use App\Service\UniqueIdGeneratorService;
use App\Service\UniqueIdGeneratorServiceInterface;
use App\Service\UploadService;
use App\Service\UploadSignerServiceInterface;
use AsyncAws\S3\Input\PutObjectRequest;
use DateTimeImmutable;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class UploadServiceTest extends KernelTestCase
{
    public function testGetMissingSigningParametersFromRequest(): void
    {
        $uploadService = new UploadService(
            $this->createMock(UploadSignerServiceInterface::class),
            $this->createMock(EnvironmentServiceInterface::class),
            new UniqueIdGeneratorService(),
            $this->createMock(Serializer::class),
            new NullLogger(),
        );

        $request = new Request([], [], [], [], [], [], json_encode([]));

        $this->expectException(SigningException::class);

        $uploadService->getSigningParametersFromRequest($request);
    }

    #[DataProvider('signingParametersDataProvider')]
    public function testGetSigningParametersFromRequest(array $parameters, ?SigningParameters $expectedParameters): void
    {
        $uploadService = new UploadService(
            $this->createMock(UploadSignerServiceInterface::class),
            $this->createMock(EnvironmentServiceInterface::class),
            new UniqueIdGeneratorService(),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        if (!$expectedParameters instanceof SigningParameters) {
            $this->expectException(MissingConstructorArgumentsException::class);
        }

        $request = new Request([], [], [], [], [], [], json_encode(['data' => $parameters], JSON_THROW_ON_ERROR));

        $signingParameters = $uploadService->getSigningParametersFromRequest($request);

        $this->assertEquals($expectedParameters, $signingParameters);
    }

    #[DataProvider('signingParametersDataProvider')]
    public function testSignedParentIsJsonEncoded(array $parameters, ?SigningParameters $expectedParameters): void
    {
        $uploadService = new UploadService(
            $this->createMock(UploadSignerServiceInterface::class),
            $this->createMock(EnvironmentServiceInterface::class),
            new UniqueIdGeneratorService(),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $request = new Request([], [], [], [], [], [], json_encode(['data' => $parameters], JSON_THROW_ON_ERROR));

        if (!$expectedParameters instanceof SigningParameters) {
            $this->expectException(MissingConstructorArgumentsException::class);
        }

        $signingParameters = $uploadService->getSigningParametersFromRequest($request);

        $parent = $signingParameters->getParent();

        $this->assertIsArray($parent);
        $this->assertEquals($parameters['parent'], $parent);
    }

    public function testBuildSignedUploadUrl(): void
    {
        $mockUniqueIdGeneratorService = $this->createMock(UniqueIdGeneratorServiceInterface::class);
        $mockUniqueIdGeneratorService->expects($this->once())
            ->method('generate')
            ->willReturn('mock-uuid');

        $mockUploadSignerService = $this->createMock(UploadSignerServiceInterface::class);
        $mockUploadSignerService->expects($this->once())
            ->method('sign')
            ->with(
                $this->isType('string'),
                new PutObjectRequest([
                    'Bucket' => 'coverage-ingest-prod',
                    'Key' => '1/a/2/mock-uuid.xml',
                    'Metadata' => [
                        'owner' => '1',
                        'repository' => 'a',
                        'commit' => '2',
                        'parent' => '["mock-parent-hash"]',
                        'tag' => 'frontend',
                        'provider' => 'github',
                        'fileName' => 'some/root/test.xml',
                        'pullRequest' => '12',
                        'baseRef' => 'main',
                        'baseCommit' => 'mock-base-commit',
                        'uploadId' => 'mock-uuid',
                        'ref' => 'mock-branch-reference',
                        'projectRoot' => 'some/root/'
                    ]
                ]),
                $this->isInstanceOf(DateTimeImmutable::class)
            )
            ->willReturn(
                new SignedUrl(
                    uploadId: 'mock-uuid',
                    signedUrl: 'https://mock-signed-url',
                    expiration: new DateTimeImmutable()
                )
            );

        $uploadService = new UploadService(
            $mockUploadSignerService,
            MockEnvironmentServiceFactory::createMock($this, Environment::PRODUCTION),
            $mockUniqueIdGeneratorService,
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $uploadService->buildSignedUploadUrl(
            new SigningParameters(
                owner: '1',
                repository: 'a',
                provider: Provider::GITHUB,
                fileName: 'some/root/test.xml',
                projectRoot: 'some/root/',
                tag: 'frontend',
                commit: '2',
                parent: ['mock-parent-hash'],
                ref: 'mock-branch-reference',
                pullRequest: '12',
                baseRef: 'main',
                baseCommit: 'mock-base-commit'
            )
        );
    }

    public static function signingParametersDataProvider(): array
    {
        return [
            'With pull request' => [
                [
                    'owner' => 'mock-owner-id',
                    'repository' => 'mock-repository-name',
                    'projectRoot' => 'some/root/',
                    'commit' => '2',
                    'pullRequest' => '12',
                    'baseRef' => 'main',
                    'baseCommit' => 'mock-base-commit',
                    'parent' => ['mock-parent-hash'],
                    'ref' => 'mock-branch-reference',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                new SigningParameters(
                    owner: 'mock-owner-id',
                    repository: 'mock-repository-name',
                    provider: Provider::GITHUB,
                    fileName: 'test.xml',
                    projectRoot: 'some/root/',
                    tag: 'frontend',
                    commit: '2',
                    parent: ['mock-parent-hash'],
                    ref: 'mock-branch-reference',
                    pullRequest: '12',
                    baseRef: 'main',
                    baseCommit: 'mock-base-commit'
                )
            ],
            'Without to pull request' => [
                [
                    'owner' => 'mock-owner-id',
                    'repository' => 'mock-repository-name',
                    'projectRoot' => 'some/root/',
                    'commit' => '2',
                    'parent' => ['mock-parent-hash'],
                    'ref' => 'mock-branch-reference',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'backend'
                ],
                new SigningParameters(
                    owner: 'mock-owner-id',
                    repository: 'mock-repository-name',
                    provider: Provider::GITHUB,
                    fileName: 'test.xml',
                    projectRoot: 'some/root/',
                    tag: 'backend',
                    commit: '2',
                    parent: ['mock-parent-hash'],
                    ref: 'mock-branch-reference',
                    pullRequest: null,
                    baseRef: null,
                    baseCommit: null
                )
            ],
            'Without commit' => [
                [
                    'owner' => 'mock-owner-id',
                    'repository' => 'mock-repository-name',
                    'projectRoot' => 'some/root/',
                    'parent' => ['mock-parent-hash'],
                    'ref' => 'mock-branch-reference',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                null
            ],
            'Without to file name' => [
                [
                    'owner' => 'mock-owner-id',
                    'repository' => 'mock-repository-name',
                    'projectRoot' => 'some/root/',
                    'commit' => '2',
                    'parent' => ['mock-parent-hash'],
                    'ref' => 'mock-branch-reference',
                    'provider' => 'github',
                    'tag' => 'backend'
                ],
                null
            ],
            'Without owner or repository' => [
                [
                    'commit' => '2',
                    'projectRoot' => 'some/root/',
                    'parent' => ['mock-parent-hash'],
                    'ref' => 'mock-branch-reference',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                null
            ],
            'Without tag' => [
                [
                    'owner' => 'mock-owner-id',
                    'repository' => 'mock-repository-name',
                    'projectRoot' => 'some/root/',
                    'commit' => '2',
                    'pullRequest' => '12',
                    'parent' => ['mock-parent-hash'],
                    'ref' => 'mock-branch-reference',
                    'provider' => 'github',
                    'fileName' => 'test.xml'
                ],
                null
            ],
            'Multiple parents' => [
                [
                    'owner' => 'mock-owner-id',
                    'repository' => 'mock-repository-name',
                    'projectRoot' => 'some/root/',
                    'commit' => '2',
                    'pullRequest' => '12',
                    'baseRef' => 'main',
                    'baseCommit' => 'mock-base-commit',
                    'parent' => ['mock-parent-hash', 'e'],
                    'ref' => 'mock-branch-reference',
                    'provider' => 'github',
                    'fileName' => 'test.xml',
                    'tag' => 'frontend'
                ],
                new SigningParameters(
                    owner: 'mock-owner-id',
                    repository: 'mock-repository-name',
                    provider: Provider::GITHUB,
                    fileName: 'test.xml',
                    projectRoot: 'some/root/',
                    tag: 'frontend',
                    commit: '2',
                    parent: ['mock-parent-hash', 'e'],
                    ref: 'mock-branch-reference',
                    pullRequest: '12',
                    baseRef: 'main',
                    baseCommit: 'mock-base-commit'
                )
            ],
        ];
    }
}
