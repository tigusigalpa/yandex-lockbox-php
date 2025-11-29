<?php

declare(strict_types=1);

namespace Tigusigalpa\YandexLockbox\Token;

use GuzzleHttp\Client;
use Tigusigalpa\YandexCloudClient\YandexCloudClient;
use Tigusigalpa\YandexLockbox\Exceptions\AuthenticationException;

/**
 * OAuth Token Provider
 * 
 * Automatically obtains IAM token from OAuth token using YandexCloudClient.
 * IAM tokens expire after 12 hours, so they are refreshed automatically.
 */
class OAuthTokenProvider implements TokenProviderInterface
{
    private YandexCloudClient $cloudClient;

    /**
     * @param string $oauthToken OAuth token from Yandex OAuth
     * @param Client|null $httpClient Optional HTTP client for testing
     */
    public function __construct(string $oauthToken, ?Client $httpClient = null)
    {
        $this->cloudClient = new YandexCloudClient($oauthToken, $httpClient);
    }

    /**
     * Get IAM token (automatically refreshes if expired)
     * 
     * @return string
     * @throws AuthenticationException
     */
    public function getToken(): string
    {
        return $this->cloudClient->getAuthManager()->getValidIamToken();
    }

    /**
     * Get Yandex Cloud Client instance for advanced operations
     * 
     * @return YandexCloudClient
     */
    public function getCloudClient(): YandexCloudClient
    {
        return $this->cloudClient;
    }

    /**
     * Force token refresh on next getToken() call
     * 
     * @return void
     */
    public function invalidateToken(): void
    {
        $this->cloudClient->getAuthManager()->clearCache();
    }

    /**
     * Get OAuth token
     * 
     * @return string
     */
    public function getOAuthToken(): string
    {
        return $this->cloudClient->getAuthManager()->getOAuthToken();
    }

    /**
     * Check if cached token is still valid
     * 
     * @return bool
     */
    public function hasValidToken(): bool
    {
        return $this->cloudClient->getAuthManager()->hasValidCachedToken();
    }

    /**
     * Get token expiration timestamp
     * 
     * @return int|null Unix timestamp or null if no token cached
     */
    public function getTokenExpiresAt(): ?int
    {
        // YandexCloudClient doesn't expose expiration time directly
        return null;
    }

    /**
     * Get first cloud from the list
     * 
     * @return array|null First cloud or null if no clouds found
     * @throws AuthenticationException
     */
    public function getFirstCloud(): ?array
    {
        $clouds = $this->cloudClient->clouds()->list();
        return $clouds['clouds'][0] ?? null;
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
     * Get first folder from the list in specified cloud
     * 
     * @param string $cloudId Cloud ID
     * @return array|null First folder or null if no folders found
     * @throws AuthenticationException
     */
    public function getFirstFolder(string $cloudId): ?array
    {
        $folders = $this->cloudClient->folders()->list($cloudId);
        return $folders['folders'][0] ?? null;
    }

    /**
     * Get first folder ID in specified cloud
     * 
     * @param string $cloudId Cloud ID
     * @return string|null First folder ID or null if no folders found
     * @throws AuthenticationException
     */
    public function getFirstFolderId(string $cloudId): ?string
    {
        $folder = $this->getFirstFolder($cloudId);
        return $folder['id'] ?? null;
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
     * Get user account information by login
     * 
     * @param string $login Yandex Passport user login
     * @return array User account data including 'id' (Subject ID)
     * @throws AuthenticationException
     */
    public function getUserByLogin(string $login): array
    {
        return $this->cloudClient->userAccounts()->getByLogin($login);
    }
}
