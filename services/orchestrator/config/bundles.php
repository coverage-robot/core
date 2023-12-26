<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    AsyncAws\Symfony\Bundle\AsyncAwsBundle::class => ['all' => true],
    Sentry\SentryBundle\SentryBundle::class => ['prod' => true],
    Packages\Event\EventBundle::class => ['all' => true],
    Packages\Telemetry\TelemetryBundle::class => ['all' => true],
    Packages\Clients\ClientsBundle::class => ['all' => true],
    Packages\Configuration\ConfigurationBundle::class => ['all' => true],
];
