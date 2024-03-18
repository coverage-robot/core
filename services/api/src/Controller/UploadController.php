<?php

namespace App\Controller;

use App\Exception\AuthenticationException;
use App\Exception\SigningException;
use App\Service\AuthTokenService;
use App\Service\AuthTokenServiceInterface;
use App\Service\UploadService;
use App\Service\UploadServiceInterface;
use Packages\Telemetry\Service\MetricServiceInterface;
use Packages\Telemetry\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class UploadController extends AbstractController
{
    public function __construct(
        #[Autowire(service: UploadService::class)]
        private readonly UploadServiceInterface $uploadService,
        #[Autowire(service: AuthTokenService::class)]
        private readonly AuthTokenServiceInterface $authTokenService,
        private readonly LoggerInterface $uploadLogger,
        private readonly MetricServiceInterface $metricService
    ) {
        TraceContext::setTraceHeaderFromEnvironment();
    }

    /**
     * @throws AuthenticationException
     * @throws SigningException
     */
    #[Route('/upload', name: 'upload', defaults: ['_format' => 'json'], methods: ['POST'])]
    public function handleUpload(Request $request): JsonResponse
    {
        $parameters = $this->uploadService->getSigningParametersFromRequest($request);

        $token = $this->authTokenService->getUploadTokenFromRequest($request);

        if ($token === null || !$this->authTokenService->validateParametersWithUploadToken($parameters, $token)) {
            throw AuthenticationException::invalidUploadToken();
        }

        $signedUrl = $this->uploadService->buildSignedUploadUrl($parameters);

        $this->uploadLogger->info(
            'Successfully generated signed url for upload request.',
            [
                'parameters' => $parameters,
                'signedUrl' => $signedUrl
            ]
        );

        $this->metricService->increment(
            metric: 'SignedUploads',
            dimensions: [
                ['owner']
            ],
            properties: [
                'owner' => $parameters->getOwner()
            ]
        );

        return $this->json($signedUrl);
    }
}
