<?php

namespace Packages\Configuration;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ConfigurationBundle extends AbstractBundle
{
    #[Override]
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder
    ): void {
        $container->import(__DIR__ . '/../config/services.yaml');
    }
}
