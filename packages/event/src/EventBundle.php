<?php

namespace Packages\Event;

use Packages\Event\Processor\EventProcessorInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class EventBundle extends AbstractBundle
{
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder
    ): void {
        $container->import(__DIR__ . '/../config/services.yaml');

        // Register any event processors which implement the interface so that the service
        // can auto discover all of the available processors
        $builder->registerForAutoconfiguration(EventProcessorInterface::class)
            ->addTag('event.processor');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Register configuration for the Event Bus client
        $container->import('../config/packages/async_aws.yaml');
    }
}
