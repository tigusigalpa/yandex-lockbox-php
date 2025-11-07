<?php

declare(strict_types=1);

namespace Tigusigalpa\YandexLockbox\Token;

use GuzzleHttp\Client;
use Tigusigalpa\YandexLockbox\Auth\OAuthTokenManager;
use Tigusigalpa\YandexLockbox\Exceptions\AuthenticationException;

/**
 * OAuth Token Provider
 * 
 * Automatically obtains IAM token from OAuth token using OAuthTokenManager.
 * IAM tokens expire after 12 hours, so they are refreshed automatically.
 */
class OAuthTokenProvider implements TokenProviderInterface
{
    private OAuthTokenManager $tokenManager;

    /**
     * @param string $oauthToken OAuth token from Yandex OAuth
     * @param Client|null $httpClient Optional HTTP client for testing
     */
    public function __construct(string $oauthToken, ?Client $httpClient = null)
    {
        $this->tokenManager = new OAuthTokenManager($oauthToken, $httpClient);
    }

    /**
     * Get IAM token (automatically refreshes if expired)
     * 
     * @return string
     * @throws AuthenticationException
     */
    public function getToken(): string
    {
        return $this->tokenManager->getIamToken();
    }

    /**
     * Get OAuth Token Manager instance for advanced operations
     * 
     * @return OAuthTokenManager
     */
    public function getTokenManager(): OAuthTokenManager
    {
        return $this->tokenManager;
    }

    /**
     * Force token refresh on next getToken() call
     * 
     * @return void
     */
    public function invalidateToken(): void
    {
        $this->tokenManager->invalidateToken();
    }

    /**
     * Get OAuth token
     * 
     * @return string
     */
    public function getOAuthToken(): string
    {
        return $this->tokenManager->getOAuthToken();
    }

    /**
     * Check if cached token is still valid
     * 
     * @return bool
     */
    public function hasValidToken(): bool
    {
        return $this->tokenManager->hasValidToken();
    }

    /**
     * Get token expiration timestamp
     * 
     * @return int|null Unix timestamp or null if no token cached
     */
    public function getTokenExpiresAt(): ?int
    {
        return $this->tokenManager->getTokenExpiresAt();
    }

    /**
     * Get first cloud from the list
     * 
     * @return array|null First cloud or null if no clouds found
     * @throws AuthenticationException
     */
    public function getFirstCloud(): ?array
    {
        return $this->tokenManager->getFirstCloud();
    }

    /**
     * Get first cloud ID
     * 
     * @return string|null First cloud ID or null if no clouds found
     * @throws AuthenticationException
     */
    public function getFirstCloudId(): ?string
    {
        return $this->tokenManager->getFirstCloudId();
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
        return $this->tokenManager->getFirstFolder($cloudId);
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
        return $this->tokenManager->getFirstFolderId($cloudId);
    }

    /**
     * Get first folder ID from first cloud (convenience method)
     * 
     * @return string|null First folder ID or null if no clouds/folders found
     * @throws AuthenticationException
     */
    public function getFirstFolderIdFromFirstCloud(): ?string
    {
        return $this->tokenManager->getFirstFolderIdFromFirstCloud();
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
        return $this->tokenManager->getUserByLogin($login);
    }
}
