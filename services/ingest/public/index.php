<?php

use App\Controller\IngestController;
use App\Kernel;
use Bref\Event\S3\S3Event;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], $context['APP_DEBUG']);
    $kernel->boot();

    $service = $kernel->getContainer()
        ->get(IngestController::class);

    return $service->handle(
        new S3Event(
            json_decode(
                (Request::createFromGlobals())->getContent(),
                true
            )
        )
    );
};
