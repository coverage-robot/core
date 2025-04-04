<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Client\CognitoClientInterface;
use App\Enum\TokenType;
use App\Exception\AuthenticationException;
use App\Model\GraphParameters;
use App\Model\Project;
use App\Model\SigningParameters;
use App\Service\AuthTokenService;
use Iterator;
use Override;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Random\Randomizer;
use Symfony\Component\HttpFoundation\Request;

final class AuthTokenServiceTest extends TestCase
{
    private CognitoClientInterface $cognitoClient;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cognitoClient = $this->createMock(CognitoClientInterface::class);
    }

    #[DataProvider('authorizationHeaderDataProvider')]
    public function testGetUploadTokenFromRequest(?string $authHeader, ?string $expectedResponse): void
    {
        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $token = $authTokenService->getUploadTokenFromRequest(
            new Request(
                server: $authHeader !== null ? ['HTTP_AUTHORIZATION' => $authHeader] : [],
                content: '{}'
            )
        );

        $this->assertEquals($expectedResponse, $token);
    }

    public function testValidateUploadTokenWithEnabledProject(): void
    {
        $project = new Project(
            Provider::GITHUB,
            'mock-project-id',
            'mock-owner',
            'mock-repository',
            'mock-email',
            'mock-graph-token',
        );

        $parameters = new SigningParameters(
            owner: 'mock-owner',
            repository: 'mock-repository',
            provider: Provider::GITHUB,
            fileName: 'mock-file-name',
            projectRoot: 'mock-project-root',
            tag: 'mock-tag',
            commit: 'mock-commit',
            parent: [],
            ref: 'mock-ref',
            pullRequest: null,
            baseRef: null,
            baseCommit: null
        );

        $this->cognitoClient->expects($this->once())
            ->method('authenticate')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                TokenType::UPLOAD,
                'mock-token'
            )
            ->willReturn(true);
        $this->cognitoClient->expects($this->once())
            ->method('getProject')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository'
            )
            ->willReturn($project);

        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $this->assertEquals(
            $project,
            $authTokenService->getProjectUsingUploadToken(
                $parameters,
                'mock-token'
            )
        );
    }

    public function testValidateUploadTokenWithDisabledProject(): void
    {
        $parameters = new SigningParameters(
            owner: 'mock-owner',
            repository: 'mock-repository',
            provider: Provider::GITHUB,
            fileName: 'mock-file-name',
            projectRoot: 'mock-project-root',
            tag: 'mock-tag',
            commit: 'mock-commit',
            parent: [],
            ref: 'mock-ref',
            pullRequest: null,
            baseRef: null,
            baseCommit: null
        );

        $this->cognitoClient->expects($this->once())
            ->method('authenticate')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                TokenType::UPLOAD,
                'mock-token'
            )
            ->willReturn(false);
        $this->cognitoClient->expects($this->never())
            ->method('getProject');

        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $this->assertFalse($authTokenService->getProjectUsingUploadToken(
            $parameters,
            'mock-token'
        ));
    }

    /**
     * @throws AuthenticationException
     */
    public function testGenerateUploadToken(): void
    {
        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $generatedToken = $authTokenService->createNewUploadToken();

        $this->assertIsString($generatedToken);
        $this->assertSame(AuthTokenService::TOKEN_LENGTH, strlen($generatedToken) / 2);
    }

    public function testGetGraphTokenFromRequest(): void
    {
        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $token = $authTokenService->getGraphTokenFromRequest(
            new Request(query: ['token' => '1234'], content: '{}')
        );

        $this->assertSame('1234', $token);
    }

    public function testGetMissingGraphTokenFromRequest(): void
    {
        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $token = $authTokenService->getGraphTokenFromRequest(
            new Request(query: [], content: '{}')
        );

        $this->assertEquals(null, $token);
    }

    public function testValidateGraphTokenWithEnabledProject(): void
    {
        $project = new Project(
            Provider::GITHUB,
            'mock-project-id',
            'mock-owner',
            'mock-repository',
            'mock-email',
            'mock-graph-token',
        );

        $parameters = new GraphParameters(
            owner: 'mock-owner',
            repository: 'mock-repository',
            provider: Provider::GITHUB
        );

        $this->cognitoClient->expects($this->once())
            ->method('authenticate')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                TokenType::GRAPH,
                'mock-token'
            )
            ->willReturn(true);

        $this->cognitoClient->expects($this->once())
            ->method('getProject')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository'
            )
            ->willReturn($project);

        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $this->assertEquals(
            $project,
            $authTokenService->getProjectUsingGraphToken(
                $parameters,
                'mock-token'
            )
        );
    }

    public function testValidateGraphTokenWithDisabledProject(): void
    {
        $parameters = new GraphParameters(
            owner: 'mock-owner',
            repository: 'mock-repository',
            provider: Provider::GITHUB
        );

        $this->cognitoClient->expects($this->once())
            ->method('authenticate')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                TokenType::GRAPH,
                'mock-token'
            )
            ->willReturn(false);
        $this->cognitoClient->expects($this->never())
            ->method('getProject');

        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $this->assertFalse($authTokenService->getProjectUsingGraphToken(
            $parameters,
            'mock-token'
        ));
    }

    /**
     * @throws AuthenticationException
     */
    public function testGenerateGraphTokenWithNoRetry(): void
    {
        $authTokenService = new AuthTokenService(
            $this->cognitoClient,
            new Randomizer(),
            new NullLogger()
        );

        $generatedToken = $authTokenService->createNewGraphToken();

        $this->assertIsString($generatedToken);
        $this->assertSame(AuthTokenService::TOKEN_LENGTH, strlen($generatedToken) / 2);
    }

    public static function authorizationHeaderDataProvider(): Iterator
    {
        yield 'No Authorization' => [
            null,
            null
        ];

        yield 'Basic Authorization with username' => [
            sprintf('Basic %s', base64_encode('mock-token:')),
            'mock-token'
        ];

        yield 'Basic Authorization with password' => [
            sprintf('Basic %s', base64_encode(':mock-token')),
            'mock-token'
        ];

        yield 'Bearer Authorization' => [
            sprintf('Bearer %s', base64_encode('some-invalid-bearer-token')),
            null
        ];
    }
}
