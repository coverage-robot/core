<?php

namespace App\Tests\Controller;

use Exception;
use JsonException;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RoutingSnapshotTest extends KernelTestCase
{
    use MatchesSnapshots;

    public function testCurrentRoutingConfigurationMatchesSnapshot(): void
    {
        try {
            $router = $this->getContainer()->get('router');

            if ($router === null) {
                $this->fail('Unable to get router from container.');
            }
        } catch (Exception $exception) {
            $this->fail($exception->getMessage());
        }

        $routeCollection = $router->getRouteCollection();

        $routeMap = [];
        foreach ($routeCollection->all() as $name => $route) {
            $routeMap[$name] = [
                'path' => $route->getPath(),
                'requirements' => $route->getRequirements(),
                'defaults' => $route->getDefaults(),
                'methods' => $route->getMethods(),
            ];
        }

        try {
            $currentRouteMapJson = json_encode($routeMap, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            $this->fail(
                sprintf(
                    'Unable to encode routing snapshot to JSON: %s',
                    $jsonException->getMessage()
                )
            );
        }

        $this->assertMatchesSnapshot($currentRouteMapJson);
    }
}
