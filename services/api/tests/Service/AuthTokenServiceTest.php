<?php

namespace App\Tests\Service;

use App\Entity\Project;
use App\Exception\AuthenticationException;
use App\Exception\TokenException;
use App\Model\SigningParameters;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenService;
use Packages\Models\Enum\Provider;
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

    public function setUp(): void
    {
        parent::setUp();

        $this->projectRepository = $this->createMock(ProjectRepository::class);
    }

    #[DataProvider('authorizationHeaderDataProvider')]
    public function testGetProjectTokenFromRequest(?string $authHeader, ?string $expectedResponse): void
    {
        $authTokenService = new AuthTokenService($this->projectRepository, new Randomizer(), new NullLogger());

        $token = $authTokenService->getUploadTokenFromRequest(
            new Request(server: $authHeader ? ['HTTP_AUTHORIZATION' => $authHeader] : [], content: '{}')
        );

        $this->assertEquals($expectedResponse, $token);
    }

    public function testValidateProjectTokenWithEnabledProject(): void
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

    public function testValidateProjectTokenWithDisabledProject(): void
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

    public function testValidateProjectTokenWithNoProject(): void
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
    public function testGenerateProjectTokenWithNoRetry(): void
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
    public function testGenerateProjectTokenWithConsecutiveRetry(): void
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

    public function testGenerateProjectTokenFailure(): void
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
