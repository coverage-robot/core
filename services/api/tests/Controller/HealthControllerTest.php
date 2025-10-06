<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class HealthControllerTest extends WebTestCase
{
    public function testHealth(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertSame('OK', $client->getResponse()->getContent());
    }
}
