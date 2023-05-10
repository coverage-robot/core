<?php

namespace Controller;

use App\Controller\UploadController;
use App\Model\SignedUrl;
use App\Service\UploadService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class UploadControllerTest extends KernelTestCase
{
    #[DataProvider('validPayloadDataProvider')]
    public function testHandleUpload(array $body): void
    {
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('validatePayload')
            ->with($body)
            ->willReturn(true);

        $uploadService->expects($this->once())
            ->method('buildSignedUploadUrl')
            ->with(
                $body["owner"],
                $body["repository"],
                $body["fileName"],
                $body["pullRequest"] ?? null,
                $body["commit"],
                $body["parent"],
                $body["provider"]
            )
            ->willReturn(
                new SignedUrl(
                    'mock-signed-url',
                    new DateTimeImmutable("2023-05-10 10:10:10")
                )
            );

        $uploadController = new UploadController($uploadService);

        $uploadController->setContainer($this->getContainer());

        $request = new Request([], [], [], [], [], [], json_encode($body));

        $response = $uploadController->handleUpload($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            '{"signedUrl":"mock-signed-url","expiration":"2023-05-10T10:10:10+00:00"}',
            $response->getContent()
        );
    }

    #[DataProvider('validPayloadDataProvider')]
    public function testHandleUploadWithInvalidBody(array $body): void
    {
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('validatePayload')
            ->with($body)
            ->willReturn(false);

        $uploadService->expects($this->never())
            ->method('buildSignedUploadUrl');

        $uploadController = new UploadController($uploadService);

        $uploadController->setContainer($this->getContainer());

        $request = new Request([], [], [], [], [], [], json_encode($body));

        $response = $uploadController->handleUpload($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"Invalid payload"}', $response->getContent());
    }

    private static function validPayloadDataProvider(): array
    {
        return [
            "With pull request" => [
                 [
                    "owner" => "1",
                    "repository" => "a",
                    "commit" => 2,
                    "pullRequest" => 12,
                    "parent" => "d",
                    "provider" => "github",
                    "fileName" => "test.xml"
                 ]
            ],
            "Without to pull request" => [
                [
                    "owner" => "1",
                    "repository" => "a",
                    "commit" => 2,
                    "parent" => "d",
                    "provider" => "github",
                    "fileName" => "test.xml"
                ]
            ]
        ];
    }
}
