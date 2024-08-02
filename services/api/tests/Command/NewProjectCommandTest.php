<?php

namespace App\Tests\Command;

use App\Client\CognitoClient;
use App\Enum\EnvironmentVariable;
use App\Service\AuthTokenService;
use App\Service\AuthTokenServiceInterface;
use AsyncAws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use AsyncAws\CognitoIdentityProvider\Result\AdminConfirmSignUpResponse;
use AsyncAws\CognitoIdentityProvider\Result\SignUpResponse;
use AsyncAws\Core\Test\ResultMockFactory;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class NewProjectCommandTest extends KernelTestCase
{
    public function testCreatingNewProject(): void
    {
        $mockClient = $this->createMock(CognitoIdentityProviderClient::class);
        $mockClient->expects($this->once())
            ->method('signUp')
            ->willReturn(ResultMockFactory::create(SignUpResponse::class));
        $mockClient->expects($this->once())
            ->method('adminConfirmSignUp')
            ->willReturn(ResultMockFactory::create(AdminConfirmSignUpResponse::class));

        $mockCognitoClient = new CognitoClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::PROJECT_POOL_ID->value => 'mock-project-pool-id',
                    EnvironmentVariable::PROJECT_POOL_CLIENT_ID->value => 'mock-project-pool-client-id',
                    EnvironmentVariable::PROJECT_POOL_CLIENT_SECRET->value => 'mock-project-pool-client-secret',
                ]
            ),
            new NullLogger()
        );

        $mockAuthTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $mockAuthTokenService->expects($this->once())
            ->method('createNewGraphToken')
            ->willReturn('mock-graph-token');

        $mockAuthTokenService->expects($this->once())
            ->method('createNewUploadToken')
            ->willReturn('mock-upload-token');

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        static::getContainer()->set(AuthTokenService::class, $mockAuthTokenService);
        static::getContainer()->set(CognitoClient::class, $mockCognitoClient);

        $command = $application->find('app:new_project');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'provider' => Provider::GITHUB->value,
            'email' => 'mock-contact-email@example.com'
        ]);

        $commandTester->assertCommandIsSuccessful();
    }
}
