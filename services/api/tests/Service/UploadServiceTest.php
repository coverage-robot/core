<?php

namespace App\Tests\Service;

use App\Exception\SigningException;
use App\Model\SigningParameters;
use App\Service\EnvironmentService;
use App\Service\UploadService;
use AsyncAws\S3\S3Client;
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
