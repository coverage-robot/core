<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
