<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class HealthController extends AbstractController
{
    #[Route(
        '/health',
        name: 'health',
        defaults: ['_format' => 'json'],
        methods: ['GET']
    )]
    public function health(): Response
    {
        return new Response(
            'OK',
            Response::HTTP_OK
        );
    }
}
