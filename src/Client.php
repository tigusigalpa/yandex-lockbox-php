<?php

namespace Tigusigalpa\YandexLockbox;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Throwable;
use Tigusigalpa\YandexLockbox\Exceptions\AuthenticationException;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;
use Tigusigalpa\YandexLockbox\Exceptions\NotFoundException;
use Tigusigalpa\YandexLockbox\Exceptions\RateLimitException;
use Tigusigalpa\YandexLockbox\Exceptions\ValidationException;
use Tigusigalpa\YandexLockbox\Token\TokenProviderInterface;

class Client
{
    private const SECRETS_ENDPOINT = 'https://lockbox.api.cloud.yandex.net/lockbox/v1/secrets';
    private const SECRETS_PAYLOAD_ENDPOINT = 'https://payload.lockbox.api.cloud.yandex.net/lockbox/v1/secrets';
    private const SECRETS_OPERATIONS_ENDPOINT = 'https://operation.api.cloud.yandex.net/operations';
    
    private GuzzleClient $http;
    private GuzzleClient $httpPayload;
    private GuzzleClient $httpOperations;
    private TokenProviderInterface $tokenProvider;
    private string $baseUri;
    private string $baseUriPayload;
    private string $baseUriOperations;
    private ?LoggerInterface $logger;

    public function __construct(
        TokenProviderInterface $tokenProvider,
        ?GuzzleClient $http = null,
        ?string $baseUri = null,
        ?LoggerInterface $logger = null
    ) {
        $this->tokenProvider = $tokenProvider;
        // Ensure base URI ends with / for proper path concatenation in Guzzle
        $baseUri = $baseUri ?: self::SECRETS_ENDPOINT;
        $baseUriPayload = self::SECRETS_PAYLOAD_ENDPOINT;
        $baseUriOperations = self::SECRETS_OPERATIONS_ENDPOINT;
        $this->baseUri = rtrim($baseUri, '/').'/';
        $this->baseUriPayload = rtrim($baseUriPayload, '/').'/';
        $this->baseUriOperations = rtrim($baseUriOperations, '/').'/';
        $this->http = $http ?: new GuzzleClient([
            'base_uri' => $this->baseUri,
            'http_errors' => true,
            'timeout' => 10,
        ]);
        $this->httpPayload = $http ?: new GuzzleClient([
            'base_uri' => $this->baseUriPayload,
            'http_errors' => true,
            'timeout' => 10,
        ]);
        $this->httpOperations = $http ?: new GuzzleClient([
            'base_uri' => $this->baseUriOperations,
            'http_errors' => true,
            'timeout' => 10,
        ]);
        $this->logger = $logger;
    }

    public function listSecrets(string $folderId, array $query = []): array
    {
        $query = array_merge(['folderId' => $folderId], $query);
        return $this->request('GET', '', ['query' => $query]);
    }

    // Secrets

    /**
     * Low-level request helper.
     */
    public function request(string $method, string $path, array $options = [], string $type = 'secret'): array
    {
        $headers = $options['headers'] ?? [];
        $headers['Authorization'] = 'Bearer '.$this->tokenProvider->getToken();
        $headers['Accept'] = 'application/json';

        if (isset($options['json']) && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        $options['headers'] = $headers;
        try {
            $http = null;
            switch ($type) {
                case 'secret':
                    $http = $this->http;
                    break;
                case 'payload':
                    $http = $this->httpPayload;
                    break;
                case 'operations':
                    $http = $this->httpOperations;
                    break;
            }
            if ($http) {
                $response = $http->request($method, $path, $options);
                $body = (string) $response->getBody();
                $decoded = $body !== '' ? json_decode($body, true) : [];
                if (!is_array($decoded)) {
                    $decoded = ['raw' => $body];
                }
                return $decoded;
            }
            return [];
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $status = $response ? $response->getStatusCode() : 0;
            $responseBody = $response ? (string) $response->getBody() : null;
            $payload = [
                'status' => $status,
                'method' => $method,
                'path' => $path,
                'response' => $responseBody,
            ];
            if ($this->logger) {
                $this->logger->error('Yandex Lockbox API error', $payload);
            }

            // Throw specific exceptions based on HTTP status code
            $message = $this->extractErrorMessage($responseBody) ?? 'Lockbox API request failed';
            match ($status) {
                400 => throw new ValidationException($message, $status, $payload, $e),
                401, 403 => throw new AuthenticationException($message, $status, $payload, $e),
                404 => throw new NotFoundException($message, $status, $payload, $e),
                429 => throw new RateLimitException($message, $status, $payload, $e),
                default => throw new LockboxException($message, $status, $payload, $e),
            };
        } catch (Throwable $e) {
            throw new LockboxException('Lockbox API request error: '.$e->getMessage(), 0, ['method' => $method, 'path' => $path],
                $e);
        }
    }

    /**
     * Extract error message from API response.
     */
    private function extractErrorMessage(?string $responseBody): ?string
    {
        if (!$responseBody) {
            return null;
        }

        $decoded = json_decode($responseBody, true);
        if (is_array($decoded)) {
            return $decoded['message'] ?? $decoded['error'] ?? $decoded['details'] ?? null;
        }

        return null;
    }

    public function getSecret(string $secretId): array
    {
        return $this->request('GET', urlencode($secretId));
    }

    /**
     * @param  array  $data
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/create
     */
    public function createSecret(array $data): array
    {
        // Pass-through body per official API. Caller is responsible for providing valid fields.
        return $this->request('POST', '', ['json' => $data]);
    }

    /**
     * @param  string  $secretId
     * @param  array  $data
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/update
     */
    public function updateSecret(string $secretId, array $data): array
    {
        return $this->request('PATCH', urlencode($secretId), ['json' => $data]);
    }

    /**
     * @param  string  $secretId
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/delete
     */
    public function deleteSecret(string $secretId): array
    {
        return $this->request('DELETE', urlencode($secretId));
    }

    /**
     * @param  string  $secretId
     * @param  array  $data
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/activate
     */
    public function activateSecret(string $secretId, array $data = []): array
    {
        return $this->request('POST', urlencode($secretId).':activate', ['json' => $data]);
    }

    /**
     * @param  string  $secretId
     * @param  array  $data
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/deactivate
     */
    public function deactivateSecret(string $secretId, array $data = []): array
    {
        return $this->request('POST', urlencode($secretId).':deactivate', ['json' => $data]);
    }

    /**
     * @param  string  $secretId
     * @param  array  $query
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/listVersions
     */
    public function listVersions(string $secretId, array $query = []): array
    {
        return $this->request('GET', urlencode($secretId).'/versions', ['query' => $query]);
    }

    /**
     * Add a new version to a secret with payload entries
     *
     * @param  string  $secretId
     * @param  array  $data  Must contain 'payloadEntries' array with key-value pairs
     *                       Example: ['payloadEntries' => [['key' => 'API_KEY', 'textValue' => 'value']]]
     * @return array
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/addVersion
     */
    public function addVersion(string $secretId, array $data): array
    {
        return $this->request('POST', urlencode($secretId).':addVersion', ['json' => $data]);
    }

    /**
     * @param  string  $secretId
     * @param  string  $versionId
     * @param  string  $delay
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/scheduleVersionDestruction
     */
    public function scheduleVersionDestruction(string $secretId, string $versionId, string $delay = '604800s'): array
    {
        return $this->request('POST', urlencode($secretId).':scheduleVersionDestruction', [
            'json' => [
                'versionId' => $versionId,
                'pendingPeriod' => $delay,
            ],
        ]);
    }

    /**
     * @param  string  $secretId
     * @param  string  $versionId
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/cancelVersionDestruction
     */
    public function cancelVersionDestruction(string $secretId, string $versionId): array
    {
        return $this->request('POST', urlencode($secretId).':cancelVersionDestruction', [
            'json' => ['versionId' => $versionId],
        ]);
    }

    /**
     * Retrieves the payload (actual secret values) for a specific secret version.
     * Requires lockbox.payloadViewer role.
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Payload/get
     */
    public function getPayload(string $secretId, ?string $versionId = null): array
    {
        $query = $versionId ? ['versionId' => $versionId] : [];
        return $this->request('GET', urlencode($secretId).'/payload', ['query' => $query], 'payload');
    }

    // Access Bindings

    /**
     * @param  string  $secretId
     * @param  array  $query
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/listOperations
     */
    public function listOperations(string $secretId, array $query = []): array
    {
        return $this->request('GET', urlencode($secretId).'/operations', ['query' => $query], 'operations');
    }

    /**
     * @param  string  $secretId
     * @param  array  $query
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/listAccessBindings
     */
    public function listAccessBindings(string $secretId, array $query = []): array
    {
        return $this->request('GET', urlencode($secretId).':listAccessBindings', ['query' => $query]);
    }

    /**
     * @param  string  $secretId
     * @param  array  $accessBindings
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/setAccessBindings
     */
    public function setAccessBindings(string $secretId, array $accessBindings): array
    {
        return $this->request('POST', urlencode($secretId).':setAccessBindings', [
            'json' => ['accessBindings' => $accessBindings],
        ]);
    }

    /**
     * @param  string  $secretId
     * @param  array  $accessBindingDeltas
     * @return array|string[]
     *
     * @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/updateAccessBindings
     */
    public function updateAccessBindings(string $secretId, array $accessBindingDeltas): array
    {
        return $this->request('POST', urlencode($secretId).':updateAccessBindings', [
            'json' => ['accessBindingDeltas' => $accessBindingDeltas],
        ]);
    }

    /**
     * Generic action caller for secret-level actions not explicitly covered.
     * Example: $client->postSecretAction($secretId, 'customAction', ['param' => 'value'])
     */
    public function postSecretAction(string $secretId, string $action, array $data = []): array
    {
        return $this->request('POST', urlencode($secretId).':'.$action, ['json' => $data]);
    }
}
