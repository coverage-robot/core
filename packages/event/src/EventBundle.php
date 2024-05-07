<?php

namespace Packages\Event;

use Override;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Processor\EventProcessorInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class EventBundle extends AbstractBundle
{
    #[Override]
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

        $this->populateContainerWithConfiguration($container, $config);
        $this->populateTemplatedContainerParameters($container);
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('event_bus')
                    ->children()
                        ->scalarNode('name')->end()
                        ->scalarNode('account_id')->end()
                        ->scalarNode('region')->end()
                        ->scalarNode('scheduler_role')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Register configuration for the Event Bus client
        $container->import('../config/packages/async_aws.yaml');

        // Register the configuration for the Event Processors logger
        $container->import('../config/packages/monolog.yaml');
    }

    /**
     * Extract any configuration values from the configuration and populate the container with them.
     */
    private function populateContainerWithConfiguration(ContainerConfigurator $container, array $config): void
    {
        $container->parameters()
            ->set('event_bus.name', $config['event_bus']['name'] ?? EventBusClient::EVENT_BUS_NAME)
            ->set('event_bus.scheduler_role', $config['event_bus']['scheduler_role'] ?? EventBusClient::EVENT_SCHEDULER_ROLE)
            ->set('event_bus.account_id', $config['event_bus']['account_id'])
            ->set('event_bus.region', $config['event_bus']['region']);
    }

    /**
     * Load the container with any templated parameters which usually come from values set in the
     * configuration, strung together using interpolation.
     */
    private function populateTemplatedContainerParameters(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('event_bus.event_bus_arn', EventBusClient::EVENT_BUS_ARN)
            ->set('event_bus.scheduler_role_arn', EventBusClient::EVENT_SCHEDULER_ROLE_ARN);
    }
}
