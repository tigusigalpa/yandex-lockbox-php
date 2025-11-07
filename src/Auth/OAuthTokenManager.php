<?php

declare(strict_types = 1);

namespace Tigusigalpa\YandexLockbox\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Tigusigalpa\YandexLockbox\Exceptions\AuthenticationException;

/**
 * OAuth Token Manager
 *
 * Manages OAuth token and provides helper methods for working with
 * Yandex Cloud resources (clouds, folders, IAM tokens).
 */
class OAuthTokenManager
{
    private const IAM_TOKEN_ENDPOINT = 'https://iam.api.cloud.yandex.net/iam/v1/tokens';
    private const CLOUDS_ENDPOINT = 'https://resource-manager.api.cloud.yandex.net/resource-manager/v1/clouds';
    private const FOLDERS_ENDPOINT = 'https://resource-manager.api.cloud.yandex.net/resource-manager/v1/folders';
    /**
     * @see https://yandex.cloud/ru/docs/lockbox/api-ref/Secret/
     */
    public const SECRETS_ENDPOINT = 'https://lockbox.api.cloud.yandex.net/lockbox/v1/secrets';
    public const SECRETS_PAYLOAD_ENDPOINT = 'https://payload.lockbox.api.cloud.yandex.net/lockbox/v1/secrets';
    public const SECRETS_OPERATIONS_ENDPOINT = 'https://operation.api.cloud.yandex.net/operations';
    private const USER_ACCOUNT_ENDPOINT = 'https://iam.api.cloud.yandex.net/iam/v1/yandexPassportUserAccounts:byLogin';
    private const OPERATIONS_ENDPOINT = 'https://operation.api.cloud.yandex.net/operations';

    private Client $httpClient;
    private string $oauthToken;
    private ?string $cachedIamToken = null;
    private ?int $tokenExpiresAt = null;

    /**
     * @param  string  $oauthToken  OAuth token from Yandex OAuth
     * @param  Client|null  $httpClient  Optional HTTP client for testing
     */
    public function __construct(string $oauthToken, ?Client $httpClient = null)
    {
        $this->oauthToken = $oauthToken;
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
    }

    /**
     * Create folder in cloud
     *
     * @param  string  $iamToken
     * @param  string  $cloudId
     * @param  string  $folderName
     * @param  string|null  $description
     * @return array
     * @throws AuthenticationException
     */
    public function createFolder(string $iamToken, string $cloudId, string $folderName, ?string $description = null): array
    {
        try {
            $response = $this->httpClient->post(self::FOLDERS_ENDPOINT, [
                'json' => [
                    'cloudId' => $cloudId,
                    'name' => $folderName,
                    'description' => $description ?? '',
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$iamToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error creating folder: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Assign role to folder
     *
     * @param  string  $iamToken
     * @param  string  $folderId
     * @param  string  $subjectId  Subject ID (user or service account)
     * @param  string  $role  Role ID (e.g., 'lockbox.editor', 'lockbox.admin')
     * @param  string  $subjectType  Subject type: 'userAccount' or 'serviceAccount'
     * @param  bool  $waitForCompletion  Whether to wait for the operation to complete
     * @param  int  $maxWaitSeconds  Maximum time to wait for operation completion (default: 60)
     * @return array
     * @throws AuthenticationException
     * @see https://yandex.cloud/en/docs/resource-manager/api-ref/Folder/setAccessBindings
     * @see https://yandex.cloud/ru/docs/lockbox/security/
     */
    public function assignRoleToFolder(
        string $iamToken,
        string $folderId,
        string $subjectId,
        string $role = 'lockbox.editor',
        string $subjectType = 'userAccount',
        bool $waitForCompletion = false,
        int $maxWaitSeconds = 60
    ): array {
        try {
            $response = $this->httpClient->post(self::FOLDERS_ENDPOINT."/{$folderId}:setAccessBindings", [
                'json' => [
                    'accessBindings' => [
                        [
                            'roleId' => $role,
                            'subject' => [
                                'id' => $subjectId,
                                'type' => $subjectType,
                            ],
                        ],
                    ],
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$iamToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // If waitForCompletion is true and operation is not done, wait for it
            if ($waitForCompletion && isset($result['id']) && isset($result['done']) && !$result['done']) {
                return $this->waitForOperation($iamToken, $result['id'], $maxWaitSeconds);
            }

            return $result;
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error assigning role: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Wait for an asynchronous operation to complete
     *
     * @param  string  $iamToken
     * @param  string  $operationId
     * @param  int  $maxWaitSeconds  Maximum time to wait (default: 60)
     * @param  int  $pollIntervalSeconds  Interval between status checks (default: 2)
     * @return array
     * @throws AuthenticationException
     */
    public function waitForOperation(
        string $iamToken,
        string $operationId,
        int $maxWaitSeconds = 60,
        int $pollIntervalSeconds = 2
    ): array {
        $startTime = time();

        while (true) {
            $operation = $this->getOperation($iamToken, $operationId);

            // Check if operation is done
            if (isset($operation['done']) && $operation['done'] === true) {
                return $operation;
            }

            // Check timeout
            if (time() - $startTime >= $maxWaitSeconds) {
                throw new AuthenticationException(
                    "Operation {$operationId} did not complete within {$maxWaitSeconds} seconds"
                );
            }

            // Wait before next poll
            sleep($pollIntervalSeconds);
        }
    }

    /**
     * Get operation status
     *
     * @param  string  $iamToken
     * @param  string  $operationId
     * @return array
     * @throws AuthenticationException
     */
    public function getOperation(string $iamToken, string $operationId): array
    {
        try {
            $response = $this->httpClient->get(self::OPERATIONS_ENDPOINT.'/'.$operationId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$iamToken,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error getting operation status: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Get folder details
     *
     * @param  string  $folderId
     * @return array
     * @throws AuthenticationException
     */
    public function getFolder(string $folderId): array
    {
        $iamToken = $this->getIamToken();

        try {
            $response = $this->httpClient->get(self::FOLDERS_ENDPOINT.'/'.$folderId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$iamToken,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error getting folder: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Get IAM token using OAuth token
     *
     * @return string
     * @throws AuthenticationException
     */
    public function getIamToken(): string
    {
        // Return cached token if still valid
        if ($this->cachedIamToken !== null && $this->tokenExpiresAt !== null) {
            // Refresh token 5 minutes before expiration
            if (time() < ($this->tokenExpiresAt - 300)) {
                return $this->cachedIamToken;
            }
        }

        try {
            $response = $this->httpClient->post(self::IAM_TOKEN_ENDPOINT, [
                'json' => [
                    'yandexPassportOauthToken' => $this->oauthToken,
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['iamToken'])) {
                throw new AuthenticationException('Failed to get IAM token: response does not contain iamToken field');
            }

            // Cache token for 12 hours
            $this->cachedIamToken = $data['iamToken'];
            $this->tokenExpiresAt = time() + 43200; // 12 hours

            return $this->cachedIamToken;
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error getting IAM token: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Get all access bindings for a folder (handles pagination automatically)
     *
     * @param  string  $iamToken
     * @param  string  $folderId
     * @return array  Returns array of all access bindings
     * @throws AuthenticationException
     */
    public function getAllFolderAccessBindings(string $iamToken, string $folderId): array
    {
        $allBindings = [];
        $pageToken = null;

        do {
            $result = $this->listFolderAccessBindings($iamToken, $folderId, 1000, $pageToken);

            if (isset($result['accessBindings'])) {
                $allBindings = array_merge($allBindings, $result['accessBindings']);
            }

            $pageToken = $result['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $allBindings;
    }

    /**
     * List access bindings for a folder
     *
     * @param  string  $iamToken
     * @param  string  $folderId
     * @param  int  $pageSize  Maximum number of results per page (default: 100, max: 1000)
     * @param  string|null  $pageToken  Token for pagination
     * @return array  Returns array with 'accessBindings' and 'nextPageToken' keys
     * @throws AuthenticationException
     * @see https://yandex.cloud/en/docs/resource-manager/api-ref/Folder/listAccessBindings
     */
    public function listFolderAccessBindings(
        string $iamToken,
        string $folderId,
        int $pageSize = 100,
        ?string $pageToken = null
    ): array {
        try {
            $queryParams = ['pageSize' => min($pageSize, 1000)];
            if ($pageToken !== null) {
                $queryParams['pageToken'] = $pageToken;
            }

            $response = $this->httpClient->get(
                self::FOLDERS_ENDPOINT."/{$folderId}:listAccessBindings",
                [
                    'query' => $queryParams,
                    'headers' => [
                        'Authorization' => 'Bearer '.$iamToken,
                    ],
                ]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error listing folder access bindings: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Get cloud details
     *
     * @param  string  $cloudId
     * @return array
     * @throws AuthenticationException
     */
    public function getCloud(string $cloudId): array
    {
        $iamToken = $this->getIamToken();

        try {
            $response = $this->httpClient->get(self::CLOUDS_ENDPOINT.'/'.$cloudId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$iamToken,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error getting cloud: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Force token refresh on next getIamToken() call
     *
     * @return void
     */
    public function invalidateToken(): void
    {
        $this->cachedIamToken = null;
        $this->tokenExpiresAt = null;
    }

    /**
     * Get OAuth token
     *
     * @return string
     */
    public function getOAuthToken(): string
    {
        return $this->oauthToken;
    }

    /**
     * Check if cached IAM token is still valid
     *
     * @return bool
     */
    public function hasValidToken(): bool
    {
        if ($this->cachedIamToken === null || $this->tokenExpiresAt === null) {
            return false;
        }

        return time() < $this->tokenExpiresAt;
    }

    /**
     * Get token expiration timestamp
     *
     * @return int|null Unix timestamp or null if no token cached
     */
    public function getTokenExpiresAt(): ?int
    {
        return $this->tokenExpiresAt;
    }

    /**
     * Get first folder ID from first cloud (convenience method)
     *
     * @return string|null First folder ID or null if no clouds/folders found
     * @throws AuthenticationException
     */
    public function getFirstFolderIdFromFirstCloud(): ?string
    {
        $cloudId = $this->getFirstCloudId();

        if ($cloudId === null) {
            return null;
        }

        return $this->getFirstFolderId($cloudId);
    }

    /**
     * Get first cloud ID
     *
     * @return string|null First cloud ID or null if no clouds found
     * @throws AuthenticationException
     */
    public function getFirstCloudId(): ?string
    {
        $cloud = $this->getFirstCloud();

        return $cloud['id'] ?? null;
    }

    /**
     * Get first cloud from the list
     *
     * @return array|null First cloud or null if no clouds found
     * @throws AuthenticationException
     */
    public function getFirstCloud(): ?array
    {
        $clouds = $this->listClouds();

        if (empty($clouds)) {
            return null;
        }

        return $clouds[0];
    }

    /**
     * Get list of clouds (public method)
     *
     * @return array
     * @throws AuthenticationException
     */
    public function listClouds(): array
    {
        $iamToken = $this->getIamToken();
        return $this->getClouds($iamToken);
    }

    /**
     * Get list of clouds
     *
     * @param  string  $iamToken
     * @return array
     * @throws AuthenticationException
     */
    public function getClouds(string $iamToken): array
    {
        try {
            $response = $this->httpClient->get(self::CLOUDS_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer '.$iamToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['clouds'] ?? [];
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error getting clouds list: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Get first folder ID in specified cloud
     *
     * @param  string  $cloudId  Cloud ID
     * @return string|null First folder ID or null if no folders found
     * @throws AuthenticationException
     */
    public function getFirstFolderId(string $cloudId): ?string
    {
        $folder = $this->getFirstFolder($cloudId);

        return $folder['id'] ?? null;
    }

    /**
     * Get first folder from the list in specified cloud
     *
     * @param  string  $cloudId  Cloud ID
     * @return array|null First folder or null if no folders found
     * @throws AuthenticationException
     */
    public function getFirstFolder(string $cloudId): ?array
    {
        $folders = $this->listFolders($cloudId);

        if (empty($folders)) {
            return null;
        }

        return $folders[0];
    }

    /**
     * Get list of folders in cloud (public method)
     *
     * @param  string  $cloudId
     * @return array
     * @throws AuthenticationException
     */
    public function listFolders(string $cloudId): array
    {
        $iamToken = $this->getIamToken();
        return $this->getFolders($iamToken, $cloudId);
    }

    /**
     * Get list of folders in cloud
     *
     * @param  string  $iamToken
     * @param  string  $cloudId
     * @return array
     * @throws AuthenticationException
     */
    public function getFolders(string $iamToken, string $cloudId): array
    {
        try {
            $response = $this->httpClient->get(self::FOLDERS_ENDPOINT, [
                'query' => [
                    'cloudId' => $cloudId,
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$iamToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['folders'] ?? [];
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error getting folders list: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Assign role to a specific secret
     *
     * @param  string  $iamToken  IAM token
     * @param  string  $secretId  Secret ID
     * @param  string  $subjectId  Subject ID (user or service account)
     * @param  string  $role  Role to assign (default: lockbox.payloadViewer)
     * @param  string  $subjectType  Subject type: userAccount or serviceAccount (default: userAccount)
     * @return array Response from API
     * @throws AuthenticationException
     * @see https://yandex.cloud/en/docs/resource-manager/api-ref/Folder/setAccessBindings
     */
    public function assignRoleToSecret(
        string $iamToken,
        string $secretId,
        string $subjectId,
        string $role = 'lockbox.payloadViewer',
        string $subjectType = 'userAccount'
    ): array {
        try {
            $response = $this->httpClient->post(self::SECRETS_ENDPOINT.$secretId.':setAccessBindings',
                [
                    'json' => [
                        'accessBindings' => [
                            [
                                'roleId' => $role,
                                'subject' => [
                                    'id' => $subjectId,
                                    'type' => $subjectType,
                                ],
                            ],
                        ],
                    ],
                    'headers' => [
                        'Authorization' => 'Bearer '.$iamToken,
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error assigning role to secret: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }

    /**
     * Get user ID (Subject ID) by login - convenience method
     *
     * @param  string  $login  Yandex Passport user login
     * @return string User Subject ID
     * @throws AuthenticationException
     */
    public function getUserIdByLogin(string $login): string
    {
        $user = $this->getUserByLogin($login);
        return $user['id'];
    }

    /**
     * Get user account information by login
     *
     * @param  string  $login  Yandex Passport user login
     * @return array User account data including 'id' (Subject ID)
     * @throws AuthenticationException
     * @see https://yandex.cloud/en/docs/iam/api-ref/YandexPassportUserAccount/getByLogin
     */
    public function getUserByLogin(string $login): array
    {
        $iamToken = $this->getIamToken();

        try {
            $response = $this->httpClient->get(self::USER_ACCOUNT_ENDPOINT, [
                'query' => [
                    'login' => $login,
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$iamToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['id'])) {
                throw new AuthenticationException('User account response does not contain id field');
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Error getting user by login: '.$e->getMessage(), $e->getCode(), [], $e);
        }
    }
}
