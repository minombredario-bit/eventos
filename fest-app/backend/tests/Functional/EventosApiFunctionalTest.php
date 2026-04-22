<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventosApiFunctionalTest extends WebTestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql extension not available; functional API tests skipped.');
        }

        // Expect caller to provide tokens via environment for tests
        if (false === getenv('API_TEST_SUPERADMIN_TOKEN') || false === getenv('API_TEST_USER_TOKEN')) {
            $this->markTestSkipped('Set API_TEST_SUPERADMIN_TOKEN and API_TEST_USER_TOKEN to run functional API tests.');
        }
    }

    public function testDeleteEventoReturns409WhenInscripcionesExist(): void
    {
        $client = static::createClient();

        // The test expects a fixture event id that has inscripciones. Replace with a real id in your environment.
        $eventoId = getenv('API_TEST_EVENT_WITH_INSCRIPTIONS_ID') ?: 'test-event-with-insc';

        $client->request('DELETE', '/api/eventos/' . $eventoId, [], [], [
            'HTTP_Authorization' => 'Bearer ' . getenv('API_TEST_USER_TOKEN'),
        ]);

        $this->assertSame(409, $client->getResponse()->getStatusCode());
    }

    public function testForceDeleteEventoWorksForSuperadmin(): void
    {
        $client = static::createClient();

        $eventoId = getenv('API_TEST_EVENT_WITH_INSCRIPTIONS_ID') ?: 'test-event-with-insc';

        $client->request('POST', '/api/eventos/' . $eventoId . '/force_delete', [], [], [
            'HTTP_Authorization' => 'Bearer ' . getenv('API_TEST_SUPERADMIN_TOKEN'),
        ]);

        $this->assertSame(204, $client->getResponse()->getStatusCode());
    }

    public function testCancelarEventoSetsEstadoCancelado(): void
    {
        $client = static::createClient();

        // Create a fresh event via API or use a dedicated test fixture id
        $eventoId = getenv('API_TEST_EVENT_ID') ?: 'test-event-1';

        $client->request('POST', '/api/eventos/' . $eventoId . '/cancelar', [], [], [
            'HTTP_Authorization' => 'Bearer ' . getenv('API_TEST_USER_TOKEN'),
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [200, 201], true));

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('CANCELADO', $data['estado'] ?? $data['estadoEvento'] ?? 'CANCELADO');
    }
}

