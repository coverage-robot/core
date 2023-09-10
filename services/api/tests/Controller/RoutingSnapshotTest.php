<?php

namespace App\Tests\Controller;

use Exception;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RoutingSnapshotTest extends KernelTestCase
{
    public function testCurrentRoutingConfigurationMatchesSnapshot(): void
    {
        try {
            $router = (static::getContainer())->get('router');
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        if ($router === null) {
            $this->fail('Unable to get router from container.');
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

        $expectedRouteMapFile = __DIR__ . '/../Fixture/routing.json';

        $this->assertJsonStringEqualsJsonFile(
            $expectedRouteMapFile,
            $currentRouteMapJson
        );
    }
}
