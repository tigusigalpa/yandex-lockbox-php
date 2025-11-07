<?php

namespace Tigusigalpa\YandexLockbox\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Exceptions\AuthenticationException;
use Tigusigalpa\YandexLockbox\Exceptions\NotFoundException;
use Tigusigalpa\YandexLockbox\Token\StaticTokenProvider;

class ClientTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);
        $tokenProvider = new StaticTokenProvider('test-token');

        return new Client($tokenProvider, $guzzle);
    }

    public function test_list_secrets_returns_array(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['secrets' => []])),
        ]);

        $result = $client->listSecrets('folder-123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('secrets', $result);
    }

    public function test_get_secret_returns_secret_data(): void
    {
        $secretData = [
            'id' => 'secret-123',
            'name' => 'test-secret',
            'folderId' => 'folder-123',
        ];

        $client = $this->createMockClient([
            new Response(200, [], json_encode($secretData)),
        ]);

        $result = $client->getSecret('secret-123');

        $this->assertEquals('secret-123', $result['id']);
        $this->assertEquals('test-secret', $result['name']);
    }

    public function test_create_secret_sends_correct_data(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['id' => 'new-secret-123'])),
        ]);

        $result = $client->createSecret([
            'folderId' => 'folder-123',
            'name' => 'my-secret',
        ]);

        $this->assertEquals('new-secret-123', $result['id']);
    }

    public function test_get_payload_returns_secret_values(): void
    {
        $payloadData = [
            'versionId' => 'version-123',
            'entries' => [
                ['key' => 'API_KEY', 'textValue' => 'secret-value'],
            ],
        ];

        $client = $this->createMockClient([
            new Response(200, [], json_encode($payloadData)),
        ]);

        $result = $client->getPayload('secret-123');

        $this->assertArrayHasKey('entries', $result);
        $this->assertCount(1, $result['entries']);
    }

    public function test_authentication_exception_on_401(): void
    {
        $this->expectException(AuthenticationException::class);

        $client = $this->createMockClient([
            new Response(401, [], json_encode(['message' => 'Unauthorized'])),
        ]);

        $client->getSecret('secret-123');
    }

    public function test_not_found_exception_on_404(): void
    {
        $this->expectException(NotFoundException::class);

        $client = $this->createMockClient([
            new Response(404, [], json_encode(['message' => 'Secret not found'])),
        ]);

        $client->getSecret('non-existent-secret');
    }

    public function test_add_version_creates_new_version(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['id' => 'version-456'])),
        ]);

        $result = $client->addVersion('secret-123', [
            'entries' => [
                ['key' => 'DB_PASSWORD', 'textValue' => 'new-password'],
            ],
        ]);

        $this->assertEquals('version-456', $result['id']);
    }

    public function test_schedule_version_destruction(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['done' => true])),
        ]);

        $result = $client->scheduleVersionDestruction('secret-123', 'version-456', '3600s');

        $this->assertTrue($result['done']);
    }

    public function test_cancel_version_destruction(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['done' => true])),
        ]);

        $result = $client->cancelVersionDestruction('secret-123', 'version-456');

        $this->assertTrue($result['done']);
    }

    public function test_activate_secret(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['done' => true])),
        ]);

        $result = $client->activateSecret('secret-123');

        $this->assertTrue($result['done']);
    }

    public function test_deactivate_secret(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['done' => true])),
        ]);

        $result = $client->deactivateSecret('secret-123');

        $this->assertTrue($result['done']);
    }

    public function test_delete_secret(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['done' => true])),
        ]);

        $result = $client->deleteSecret('secret-123');

        $this->assertTrue($result['done']);
    }

    public function test_list_versions(): void
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['versions' => []])),
        ]);

        $result = $client->listVersions('secret-123');

        $this->assertArrayHasKey('versions', $result);
    }
}
