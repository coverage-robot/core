<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Sentry\SentryBundle\SentryBundle::class => ['prod' => true],
    Packages\Telemetry\TelemetryBundle::class => ['all' => true],
    Packages\Clients\ClientsBundle::class => ['all' => true],
];
