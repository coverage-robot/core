<?php

namespace App\Tests\Client;

use App\Client\CognitoClient;
use App\Enum\EnvironmentVariable;
use App\Enum\TokenType;
use App\Model\Tokens;
use AsyncAws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use AsyncAws\CognitoIdentityProvider\Input\AdminConfirmSignUpRequest;
use AsyncAws\CognitoIdentityProvider\Input\AdminGetUserRequest;
use AsyncAws\CognitoIdentityProvider\Input\SignUpRequest;
use AsyncAws\CognitoIdentityProvider\Result\AdminConfirmSignUpResponse;
use AsyncAws\CognitoIdentityProvider\Result\AdminGetUserResponse;
use AsyncAws\CognitoIdentityProvider\Result\AdminInitiateAuthResponse;
use AsyncAws\CognitoIdentityProvider\Result\SignUpResponse;
use AsyncAws\CognitoIdentityProvider\ValueObject\AttributeType;
use AsyncAws\CognitoIdentityProvider\ValueObject\AuthenticationResultType;
use AsyncAws\Core\Test\ResultMockFactory;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CognitoClientTest extends TestCase
{
    public function testCreatingProject(): void
    {
        $client = $this->createMock(CognitoIdentityProviderClient::class);
        $client->expects($this->once())
            ->method('signUp')
            ->with(
                $this->callback(
                    function (SignUpRequest $request): bool {
                        $this->assertEquals('owner-github-repository', $request->getUsername());
                        $this->assertEquals('upload-token', $request->getPassword());

                        return true;
                    }
                )
            )
            ->willReturn(
                ResultMockFactory::create(
                    SignUpResponse::class,
                    [
                        'userSub' => 'mock-sub',
                    ]
                )
            );

        $client->expects($this->once())
            ->method('adminConfirmSignUp')
            ->with(
                $this->callback(
                    function (AdminConfirmSignUpRequest $request): bool {
                        $this->assertEquals('owner-github-repository', $request->getUsername());

                        return true;
                    }
                )
            )
            ->willReturn(ResultMockFactory::create(AdminConfirmSignUpResponse::class));

        $cognitoClient = new CognitoClient(
            $client,
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::PROJECT_POOL_ID->value => 'mock-pool-id',
                    EnvironmentVariable::PROJECT_POOL_CLIENT_ID->value => 'mock-client-id',
                    EnvironmentVariable::PROJECT_POOL_CLIENT_SECRET->value => 'mock-client-secret'
                ]
            ),
            new NullLogger()
        );

        $this->assertTrue(
            $cognitoClient->createProject(
                Provider::GITHUB,
                'owner',
                'repository',
                'mock-email@example.com',
                new Tokens('upload-token', 'graph-token')
            )
        );
    }

    #[DataProvider('successfulTokenDataProvider')]
    public function testAuthenticatingTokensSuccessfully(
        TokenType $tokenType,
        string $token
    ): void {
        $client = $this->createMock(CognitoIdentityProviderClient::class);

        if ($tokenType === TokenType::UPLOAD) {
            $client->expects($this->once())
                ->method('adminInitiateAuth')
                ->willReturn(
                    ResultMockFactory::create(
                        AdminInitiateAuthResponse::class,
                        [
                            'authenticationResult' => new AuthenticationResultType(
                                [
                                    'AccessToken' => 'mock'
                                ]
                            )
                        ]
                    )
                );
        } else {
            $client->expects($this->once())
                ->method('adminGetUser')
                ->with(
                    $this->callback(
                        function (AdminGetUserRequest $request): bool {
                            $this->assertEquals('owner-github-repository', $request->getUsername());

                            return true;
                        }
                    )
                )
                ->willReturn(
                    ResultMockFactory::create(
                        AdminGetUserResponse::class,
                        [
                            'userAttributes' => [
                                new AttributeType(
                                    [
                                        'Name' => 'custom:graph_token',
                                        'Value' => 'graph-token'
                                    ]
                                )
                            ]
                        ]
                    )
                );
        }

        $cognitoClient = new CognitoClient(
            $client,
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::PROJECT_POOL_ID->value => 'mock-pool-id',
                    EnvironmentVariable::PROJECT_POOL_CLIENT_ID->value => 'mock-client-id',
                    EnvironmentVariable::PROJECT_POOL_CLIENT_SECRET->value => 'mock-client-secret'
                ]
            ),
            new NullLogger()
        );

        $this->assertTrue(
            $cognitoClient->authenticate(
                Provider::GITHUB,
                'owner',
                'repository',
                $tokenType,
                $token
            )
        );
    }

    #[DataProvider('invalidTokenDataProvider')]
    public function testAuthenticatingTokensUnsuccessfully(
        TokenType $tokenType,
        string $token
    ): void {
        $client = $this->createMock(CognitoIdentityProviderClient::class);

        if ($tokenType === TokenType::UPLOAD) {
            $client->expects($this->once())
                ->method('adminInitiateAuth')
                ->willReturn(
                    ResultMockFactory::createFailing(
                        AdminInitiateAuthResponse::class,
                        400
                    )
                );
        } else {
            $client->expects($this->once())
                ->method('adminGetUser')
                ->with(
                    $this->callback(
                        function (AdminGetUserRequest $request): bool {
                            $this->assertEquals('owner-github-repository', $request->getUsername());

                            return true;
                        }
                    )
                )
                ->willReturn(
                    ResultMockFactory::create(
                        AdminGetUserResponse::class,
                        [
                            'userAttributes' => []
                        ]
                    )
                );
        }

        $cognitoClient = new CognitoClient(
            $client,
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::PROJECT_POOL_ID->value => 'mock-pool-id',
                    EnvironmentVariable::PROJECT_POOL_CLIENT_ID->value => 'mock-client-id',
                    EnvironmentVariable::PROJECT_POOL_CLIENT_SECRET->value => 'mock-client-secret'
                ]
            ),
            new NullLogger()
        );

        $this->assertFalse(
            $cognitoClient->authenticate(
                Provider::GITHUB,
                'owner',
                'repository',
                $tokenType,
                $token
            )
        );
    }

    public static function successfulTokenDataProvider(): array
    {
        return [
            'Valid upload token' => [TokenType::UPLOAD, 'upload-token'],
            'Valid graph token' => [TokenType::GRAPH, 'graph-token'],
        ];
    }

    public static function invalidTokenDataProvider(): array
    {
        return [
            'Invalid upload token' => [TokenType::UPLOAD, 'invalid-upload-token'],
            'Invalid graph token' => [TokenType::GRAPH, 'invalid-graph-token'],
        ];
    }
}
