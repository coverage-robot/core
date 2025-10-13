<?php

declare(strict_types=1);

namespace Packages\Clients;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ClientsBundle extends AbstractBundle
{
    #[Override]
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder
    ): void {
        $container->import(__DIR__ . '/../config/services.yaml');
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        /**
         * @psalm-suppress UndefinedMethod
         * @psalm-suppress MixedMethodCall
         */
        $definition->rootNode()->end();
    }
}
