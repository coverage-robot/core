<?php

namespace App\Tests\Service;

use App\Service\EnvironmentService;
use App\Service\UploadService;
use AsyncAws\S3\S3Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UploadServiceTest extends TestCase
{
    #[DataProvider('validatablePayloadDataProvider')]
    public function testValidatePayload(array $body, bool $expectedValidity): void
    {
        $uploadService = new UploadService(
            $this->createMock(S3Client::class),
            $this->createMock(EnvironmentService::class)
        );

        $isValid = $uploadService->validatePayload($body);

        $this->assertEquals($expectedValidity, $isValid);
    }

    private static function validatablePayloadDataProvider(): array
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
                    'fileName' => 'test.xml'
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
                    'fileName' => 'test.xml'
                ],
                true
            ],
            'Without commit' => [
                [
                    'owner' => '1',
                    'repository' => 'a',
                    'parent' => 'd',
                    'provider' => 'github',
                    'fileName' => 'test.xml'
                ],
                false
            ],
            'Without to file name' => [
                [
                    'owner' => '1',
                    'repository' => 'a',
                    'commit' => 2,
                    'parent' => 'd',
                    'provider' => 'github'
                ],
                false
            ],
            'Without owner or repository' => [
                [
                    'commit' => 2,
                    'parent' => 'd',
                    'provider' => 'github',
                    'fileName' => 'test.xml'
                ],
                false
            ],
        ];
    }
}
