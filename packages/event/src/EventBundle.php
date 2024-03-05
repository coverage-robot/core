<?php

namespace Packages\Event;

use Packages\Event\Client\EventBusClient;
use Packages\Event\Processor\EventProcessorInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

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

        // Load the configuration into the container
        $container->parameters()
            ->set('event_bus.name', $config['event_bus']['name'] ?? EventBusClient::EVENT_BUS_NAME);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('event_bus')
                    ->children()
                        ->scalarNode('name')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Register configuration for the Event Bus client
        $container->import('../config/packages/async_aws.yaml');
    }
}
