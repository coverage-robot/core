<?php

namespace App\Tests\Service;

use App\Entity\Project;
use App\Exception\AuthenticationException;
use App\Exception\TokenException;
use App\Model\GraphParameters;
use App\Model\SigningParameters;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenService;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Random\Randomizer;
use Symfony\Component\HttpFoundation\Request;

class AuthTokenServiceTest extends TestCase
{
    private ProjectRepository|MockObject $projectRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRepository = $this->createMock(ProjectRepository::class);
    }

    #[DataProvider('authorizationHeaderDataProvider')]
    public function testGetUploadTokenFromRequest(?string $authHeader, ?string $expectedResponse): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $token = $authTokenService->getUploadTokenFromRequest(
            new Request(server: $authHeader ? ['HTTP_AUTHORIZATION' => $authHeader] : [], content: '{}')
        );

        $this->assertEquals($expectedResponse, $token);
    }

    public function testValidateUploadTokenWithEnabledProject(): void
    {
        $parameters = $this->createMock(SigningParameters::class);
        $parameters->expects($this->once())
            ->method('getProvider')
            ->willReturn(Provider::GITHUB);
        $parameters->expects($this->once())
            ->method('getRepository')
            ->willReturn('mock-repository');
        $parameters->expects($this->once())
            ->method('getOwner')
            ->willReturn('mock-owner');

        $project = $this->createMock(Project::class);
        $project->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
                'uploadToken' => 'mock-token'
            ])
            ->willReturn($project);

        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->assertTrue($authTokenService->validateParametersWithUploadToken(
            $parameters,
            'mock-token'
        ));
    }

    public function testValidateUploadTokenWithDisabledProject(): void
    {
        $parameters = $this->createMock(SigningParameters::class);
        $parameters->expects($this->once())
            ->method('getProvider')
            ->willReturn(Provider::GITHUB);
        $parameters->expects($this->once())
            ->method('getRepository')
            ->willReturn('mock-repository');
        $parameters->expects($this->once())
            ->method('getOwner')
            ->willReturn('mock-owner');

        $project = $this->createMock(Project::class);
        $project->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
                'uploadToken' => 'mock-token'
            ])
            ->willReturn($project);

        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->assertFalse($authTokenService->validateParametersWithUploadToken(
            $parameters,
            'mock-token'
        ));
    }

    public function testValidateUploadTokenWithNoProject(): void
    {
        $parameters = $this->createMock(SigningParameters::class);
        $parameters->expects($this->once())
            ->method('getProvider')
            ->willReturn(Provider::GITHUB);
        $parameters->expects($this->once())
            ->method('getRepository')
            ->willReturn('mock-repository');
        $parameters->expects($this->once())
            ->method('getOwner')
            ->willReturn('mock-owner');

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
                'uploadToken' => 'mock-token'
            ])
            ->willReturn(null);

        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->assertFalse($authTokenService->validateParametersWithUploadToken(
            $parameters,
            'mock-token'
        ));
    }

    /**
     * @throws AuthenticationException
     */
    public function testGenerateUploadTokenWithNoRetry(): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $generatedToken = $authTokenService->createNewUploadToken();

        $this->assertIsString($generatedToken);
        $this->assertEquals(AuthTokenService::TOKEN_LENGTH, strlen($generatedToken) / 2);
    }

    /**
     * @throws AuthenticationException
     * @throws Exception
     */
    public function testGenerateUploadTokenWithConsecutiveRetry(): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->projectRepository->expects($this->exactly(3))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(
                $this->createMock(Project::class),
                $this->createMock(Project::class),
                null
            );

        $generatedToken = $authTokenService->createNewUploadToken();

        $this->assertIsString($generatedToken);
        $this->assertEquals(AuthTokenService::TOKEN_LENGTH, strlen($generatedToken) / 2);
    }

    public function testGenerateUploadTokenFailure(): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->projectRepository->expects($this->exactly(AuthTokenService::MAX_TOKEN_RETRIES))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(
                $this->createMock(Project::class),
                $this->createMock(Project::class),
                $this->createMock(Project::class)
            );

        $this->expectExceptionObject(
            TokenException::failedToCreateToken(AuthTokenService::MAX_TOKEN_RETRIES)
        );

        $authTokenService->createNewUploadToken();
    }

    public function testGetGraphTokenFromRequest(): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $token = $authTokenService->getGraphTokenFromRequest(
            new Request(query: ['token' => '1234'], content: '{}')
        );

        $this->assertEquals('1234', $token);
    }

    public function testGetMissingGraphTokenFromRequest(): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $token = $authTokenService->getGraphTokenFromRequest(
            new Request(query: [], content: '{}')
        );

        $this->assertEquals(null, $token);
    }

    public function testValidateGraphTokenWithEnabledProject(): void
    {
        $parameters = $this->createMock(GraphParameters::class);
        $parameters->expects($this->once())
            ->method('getProvider')
            ->willReturn(Provider::GITHUB);
        $parameters->expects($this->once())
            ->method('getRepository')
            ->willReturn('mock-repository');
        $parameters->expects($this->once())
            ->method('getOwner')
            ->willReturn('mock-owner');

        $project = $this->createMock(Project::class);
        $project->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
                'graphToken' => 'mock-token'
            ])
            ->willReturn($project);

        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->assertTrue($authTokenService->validateParametersWithGraphToken(
            $parameters,
            'mock-token'
        ));
    }

    public function testValidateGraphTokenWithDisabledProject(): void
    {
        $parameters = $this->createMock(GraphParameters::class);
        $parameters->expects($this->once())
            ->method('getProvider')
            ->willReturn(Provider::GITHUB);
        $parameters->expects($this->once())
            ->method('getRepository')
            ->willReturn('mock-repository');
        $parameters->expects($this->once())
            ->method('getOwner')
            ->willReturn('mock-owner');

        $project = $this->createMock(Project::class);
        $project->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
                'graphToken' => 'mock-token'
            ])
            ->willReturn($project);

        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->assertFalse($authTokenService->validateParametersWithGraphToken(
            $parameters,
            'mock-token'
        ));
    }

    public function testValidateGraphTokenWithNoProject(): void
    {
        $parameters = $this->createMock(GraphParameters::class);
        $parameters->expects($this->once())
            ->method('getProvider')
            ->willReturn(Provider::GITHUB);
        $parameters->expects($this->once())
            ->method('getRepository')
            ->willReturn('mock-repository');
        $parameters->expects($this->once())
            ->method('getOwner')
            ->willReturn('mock-owner');

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
                'graphToken' => 'mock-token'
            ])
            ->willReturn(null);

        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->assertFalse($authTokenService->validateParametersWithGraphToken(
            $parameters,
            'mock-token'
        ));
    }

    /**
     * @throws AuthenticationException
     */
    public function testGenerateGraphTokenWithNoRetry(): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $generatedToken = $authTokenService->createNewGraphToken();

        $this->assertIsString($generatedToken);
        $this->assertEquals(AuthTokenService::TOKEN_LENGTH, strlen($generatedToken) / 2);
    }

    /**
     * @throws AuthenticationException
     * @throws Exception
     */
    public function testGenerateGraphTokenWithConsecutiveRetry(): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->projectRepository->expects($this->exactly(3))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(
                $this->createMock(Project::class),
                $this->createMock(Project::class),
                null
            );

        $generatedToken = $authTokenService->createNewGraphToken();

        $this->assertIsString($generatedToken);
        $this->assertEquals(AuthTokenService::TOKEN_LENGTH, strlen($generatedToken) / 2);
    }

    public function testGenerateGraphTokenFailure(): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $this->projectRepository->expects($this->exactly(AuthTokenService::MAX_TOKEN_RETRIES))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(
                $this->createMock(Project::class),
                $this->createMock(Project::class),
                $this->createMock(Project::class)
            );

        $this->expectExceptionObject(
            TokenException::failedToCreateToken(AuthTokenService::MAX_TOKEN_RETRIES)
        );

        $authTokenService->createNewGraphToken();
    }

    public static function authorizationHeaderDataProvider(): array
    {
        return [
            'No Authorization' => [
                null,
                null
            ],
            'Basic Authorization with username' => [
                sprintf('Basic %s', base64_encode('mock-token:')),
                'mock-token'
            ],
            'Basic Authorization with password' => [
                sprintf('Basic %s', base64_encode(':mock-token')),
                'mock-token'
            ],
            'Bearer Authorization' => [
                sprintf('Bearer %s', base64_encode('some-invalid-bearer-token')),
                null
            ],
        ];
    }
}
