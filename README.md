# Yandex Lockbox PHP SDK

<p align="center">
  <img src="https://i.ibb.co/3yms3FTY/yandex-lockbox-php-hero.png" alt="Yandex Lockbox PHP SDK" />
</p>

> 🇷🇺 [Русская версия документации](README-ru.md)

[![Latest Version](https://img.shields.io/packagist/v/tigusigalpa/yandex-lockbox-php.svg?style=flat-square)](https://packagist.org/packages/tigusigalpa/yandex-lockbox-php)
[![PHP Version](https://img.shields.io/packagist/php-v/tigusigalpa/yandex-lockbox-php.svg?style=flat-square)](https://packagist.org/packages/tigusigalpa/yandex-lockbox-php)
[![License](https://img.shields.io/packagist/l/tigusigalpa/yandex-lockbox-php.svg?style=flat-square)](https://packagist.org/packages/tigusigalpa/yandex-lockbox-php)
[![Tests](https://img.shields.io/github/actions/workflow/status/tigusigalpa/yandex-lockbox-php/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/tigusigalpa/yandex-lockbox-php/actions)

PHP/Laravel client library for **Yandex Lockbox** — a secure secrets storage service in Yandex Cloud.

## 📚 Documentation

- [Yandex Lockbox Docs](https://yandex.cloud/en/docs/lockbox/)
- [Quickstart Guide](https://yandex.cloud/en/docs/lockbox/quickstart)
- [API Reference](https://yandex.cloud/en/docs/lockbox/api-ref/Secret/)
- [OAuth Token Guide](https://yandex.cloud/en/docs/iam/concepts/authorization/oauth-token)
- API Endpoint: `https://lockbox.api.cloud.yandex.net/lockbox/v1`

## ✨ Features

- ✅ Full Yandex Lockbox API support
- ✅ **Automatic IAM token generation from OAuth token**
- ✅ OAuth Token Manager for cloud/folder management
- ✅ **Async operation handling** (wait for operations to complete)
- ✅ **Folder permissions management** (list/assign access bindings)
- ✅ PHP 8.0+ with strict types
- ✅ Laravel 8-12 integration (service provider, facade, config)
- ✅ Extensible token provider interface
- ✅ Typed exceptions for better error handling
- ✅ PSR-3 logger support
- ✅ Comprehensive test coverage

## 📦 Installation

```bash
composer require tigusigalpa/yandex-lockbox-php
```

### Development (path repository)

For mono-repo development, add to your root `composer.json`:

```json
{
  "repositories": [
    { "type": "path", "url": "public_html/packages/yandex-lockbox-php" }
  ],
  "require": {
    "tigusigalpa/yandex-lockbox-php": "*"
  }
}
```

Then run:

```bash
composer update tigusigalpa/yandex-lockbox-php
```

## ⚙️ Configuration (Laravel)

Publish the configuration file:

```bash
php artisan vendor:publish --tag=yandex-lockbox-config
```

Add environment variables to your `.env`:

```env
# RECOMMENDED: Use OAuth token (starts with y0_, y1_, y2_, y3_)
# OAuth tokens don't expire and are automatically converted to IAM tokens
YANDEX_LOCKBOX_TOKEN=y0_your-oauth-token

# ALTERNATIVE: Use IAM token (starts with t1.)
# IAM tokens expire after 12 hours
# YANDEX_LOCKBOX_TOKEN=t1.your-iam-token

YANDEX_LOCKBOX_BASE_URI=https://lockbox.api.cloud.yandex.net/lockbox/v1
YANDEX_LOCKBOX_FOLDER_ID=your-default-folder-id
```

## 🔐 Authorization & API Connection Guide

### Step 1: Getting OAuth Token

**Documentation:** [OAuth Token Guide](https://yandex.cloud/en/docs/iam/concepts/authorization/oauth-token)

**Get token via OAuth request:**
```
https://oauth.yandex.com/authorize?response_type=token&client_id=1a6990aa636648e9b2ef855fa7bec2fb
```

1. Open the URL above in your browser
2. Authorize the application
3. Copy the OAuth token from the response URL (format: `y0_...`, `y1_...`, `y2_...`, `y3_...`)
4. Add token to `.env` (Laravel):
   ```env
   YANDEX_LOCKBOX_TOKEN=y0_your-oauth-token
   ```

**Or pass directly to OAuthTokenManager:**
```php
use Tigusigalpa\YandexLockbox\Auth\OAuthTokenManager;

$manager = new OAuthTokenManager('y0_your-oauth-token');
```

### Step 2: Getting IAM Token (Optional)

**Documentation:** [How to get IAM token](https://yandex.cloud/en/docs/iam/operations/iam-token/create#exchange-token)

IAM token is generated automatically from OAuth token. But you can get it manually:

```php
$manager = new OAuthTokenManager('y0_your-oauth-token');

// Get IAM token (cached for 12 hours)
$iamToken = $manager->getIamToken();
```

**Alternative - using Yandex CLI:**
```bash
yc iam create-token
```
⚠️ Note: IAM tokens expire after 12 hours


### Step 3: Getting Cloud ID

**Documentation:** [Retrieves the list of Cloud resources](https://yandex.cloud/en/docs/resource-manager/api-ref/Cloud/list)

**List all clouds:**
```php
$manager = new OAuthTokenManager('y0_your-oauth-token');

// Get all clouds
$clouds = $manager->listClouds();

foreach ($clouds as $cloud) {
    echo "Cloud: {$cloud['name']} (ID: {$cloud['id']})\n";
}

// Use first cloud
$cloudId = $clouds[0]['id'];
```

**Or get first cloud directly:**
```php
// Get first cloud ID (convenience method)
$cloudId = $manager->getFirstCloudId();
```

### Step 4: Getting Folder ID

**Documentation:** [Retrieves the list of Folder resources in the specified cloud](https://yandex.cloud/en/docs/resource-manager/api-ref/Folder/list)

**List all folders in cloud:**
```php
// Get all folders in cloud
$folders = $manager->listFolders($cloudId);

foreach ($folders as $folder) {
    echo "Folder: {$folder['name']} (ID: {$folder['id']})\n";
}

// Use first folder
$folderId = $folders[0]['id'];
```

**Or get first folder directly:**
```php
// Get first folder ID (convenience method)
$folderId = $manager->getFirstFolderId($cloudId);

// Or get first folder from first cloud in one call
$folderId = $manager->getFirstFolderIdFromFirstCloud();
```

### Step 5: Add permissions to a folder

**Documentation:** [Access management in Yandex Lockbox](https://yandex.cloud/en/docs/lockbox/security/)

> **You need to get Subject ID (user account ID that you want to assign permissions to) first**

**Documentation:** [Subjects that roles are assigned to](https://yandex.cloud/en/docs/iam/concepts/access-control/#subject)

**Documentation:** [Retrieves the list of Yandex Passport user accounts](https://yandex.cloud/en/docs/iam/api-ref/YandexPassportUserAccount/getByLogin)

```php
$subjectId = $manager->getUserIdByLogin('your-yandex-login'); // your-yandex-login@yandex.ru
```

**Documentation:** [lockbox.editor](https://yandex.cloud/en/docs/lockbox/security/#lockbox-editor)

**Documentation:** [Setting up folder access permissions](https://yandex.cloud/en/docs/resource-manager/operations/folder/set-access-bindings)

```php
$manager->assignRoleToFolder(
    $iamToken, 
    $folderId, 
    $subjectId, 
    'lockbox.editor',
    'userAccount',
    true  // waitForCompletion - waits until operation is done
);
```

### Step 6: Working with Yandex Lockbox API

**Documentation:** [Lockbox API, REST: Secret](https://yandex.cloud/en/docs/lockbox/api-ref/Secret/)

Now you can use the folder ID to work with secrets:

```php
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Token\OAuthTokenProvider;

// Create client with OAuth token
$tokenProvider = new OAuthTokenProvider('y0_your-oauth-token');
$client = new Client($tokenProvider);

// List all secrets in a folder
$secrets = $client->listSecrets($folderId);
foreach ($secrets['secrets'] as $secret) {
    echo "{$secret['name']} (ID: {$secret['id']})\n";
    echo "Description: {$secret['description']}\n";
    echo "Labels: " . json_encode($secret['labels']) . "\n";
    echo "Status: {$secret['status']}\n";
    echo "Created at: {$secret['createdAt']}\n";
    echo "Updated at: {$secret['updatedAt']}\n";
    echo "Current version: {$secret['currentVersion']}\n";
}

// Get secret metadata
// @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/get
$secret = $client->getSecret('your-secret-id');

// Get secret payload (actual values)
$payload = $client->getPayload('your-secret-id');
foreach ($payload['entries'] as $entry) {
    echo "{$entry['key']}: {$entry['textValue']}\n"; // or {$entry['binaryValue']}
}
echo $payload['versionId'];


// Optional: get specific version
$payload = $client->getPayload('your-secret-id', 'version-id');

// Create a new secret
// @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/create
$created = $client->createSecret([
    'folderId' => $folderId,
    'name' => 'my-api-keys',
    'description' => 'Production API keys',
    'labels' => ['env' => 'production'],
]);

$secretId = $created['id'];

// Add a new version with secret values
// Uses POST /secrets/{id}:addVersion endpoint
// @see https://yandex.cloud/en/docs/lockbox/api-ref/Secret/addVersion
$version = $client->addVersion($secretId, [
    'description' => 'Version with API keys',  // Optional
    'payloadEntries' => [
        ['key' => 'API_KEY', 'textValue' => 'super-secret-key'],
        ['key' => 'API_SECRET', 'textValue' => 'super-secret-value'],
    ],
]);

// Update secret metadata
$updated = $client->updateSecret($secretId, [
    'name' => 'updated-name',
    'description' => 'Updated description',
]);

// List all versions
$versions = $client->listVersions($secretId);

// Activate/Deactivate secret
$client->activateSecret($secretId);
$client->deactivateSecret($secretId);

// Schedule version destruction (7 days by default)
$client->scheduleVersionDestruction($secretId, 'version-id', '604800s');

// Cancel scheduled destruction
$client->cancelVersionDestruction($secretId, 'version-id');

// Delete secret
$client->deleteSecret($secretId);

// List operations
$operations = $client->listOperations($secretId);

// Access control
$bindings = $client->listAccessBindings($secretId);
$client->setAccessBindings($secretId, [
    ['roleId' => 'viewer', 'subject' => ['type' => 'userAccount', 'id' => 'user-id']],
]);
```

### Handling Asynchronous Operations

Some Yandex Cloud operations (like `assignRoleToFolder`) are asynchronous and return an operation object with `done=false`. You have two options:

**Option 1: Wait for completion automatically**

```php
$manager = new OAuthTokenManager('y0_your-oauth-token');
$iamToken = $manager->getIamToken();

// Set waitForCompletion to true (6th parameter)
$result = $manager->assignRoleToFolder(
    $iamToken,
    'folder-id',
    'user-id',
    'lockbox.editor',
    'userAccount',
    true,  // waitForCompletion
    60     // maxWaitSeconds (optional, default: 60)
);

// $result['done'] will be true
```

**Option 2: Poll operation status manually**

```php
// Start operation
$operation = $manager->assignRoleToFolder($iamToken, 'folder-id', 'user-id', 'lockbox.editor');

// Check if done
if (!$operation['done']) {
    // Wait for operation to complete
    $completed = $manager->waitForOperation(
        $iamToken,
        $operation['id'],
        60  // maxWaitSeconds (optional)
    );
    
    if ($completed['done']) {
        echo "Operation completed successfully!\n";
    }
}

// Or check status without waiting
$status = $manager->getOperation($iamToken, $operation['id']);
echo "Operation status: " . ($status['done'] ? 'completed' : 'in progress') . "\n";
```

### Managing Folder Permissions

List and manage access bindings (permissions) for folders:

```php
use Tigusigalpa\YandexLockbox\Auth\OAuthTokenManager;

$manager = new OAuthTokenManager('y0_your-oauth-token');
$iamToken = $manager->getIamToken();

// List access bindings with pagination
$result = $manager->listFolderAccessBindings($iamToken, 'folder-id', 100);
foreach ($result['accessBindings'] as $binding) {
    echo "Role: {$binding['roleId']}\n";
    echo "Subject: {$binding['subject']['id']} ({$binding['subject']['type']})\n";
}

// Handle pagination if needed
if (isset($result['nextPageToken'])) {
    $nextPage = $manager->listFolderAccessBindings(
        $iamToken, 
        'folder-id', 
        100, 
        $result['nextPageToken']
    );
}

// Get all bindings at once (automatic pagination)
$allBindings = $manager->getAllFolderAccessBindings($iamToken, 'folder-id');
echo "Total permissions: " . count($allBindings) . "\n";

// Group by role
$byRole = [];
foreach ($allBindings as $binding) {
    $byRole[$binding['roleId']][] = $binding['subject'];
}
```

**Response structure:**

```php
[
    'accessBindings' => [
        [
            'roleId' => 'lockbox.editor',  // Role identifier
            'subject' => [
                'id' => 'ajef55nu903fiklhapf9',  // User/SA ID
                'type' => 'userAccount'  // 'userAccount' or 'serviceAccount'
            ]
        ],
        // ... more bindings
    ],
    'nextPageToken' => 'token...'  // Present if more pages available
]
```

### Laravel Facade

```php
use Tigusigalpa\YandexLockbox\Laravel\Facades\Lockbox;

// List secrets using default folder from config
$secrets = Lockbox::listSecrets(config('lockbox.default_folder_id'));

// Get secret metadata
$secret = Lockbox::getSecret('secret-id');

// Get actual secret values
$payload = Lockbox::getPayload('secret-id');
foreach ($payload['entries'] as $entry) {
    echo $entry['key'] . ': ' . $entry['textValue'] . PHP_EOL;
}

// Create secret
$created = Lockbox::createSecret([
    'folderId' => config('lockbox.default_folder_id'),
    'name' => 'laravel-secrets',
    'description' => 'Laravel application secrets',
]);

// Add version
$version = Lockbox::addVersion('secret-id', [
    'payloadEntries' => [
        ['key' => 'DB_PASSWORD', 'textValue' => env('DB_PASSWORD')],
        ['key' => 'APP_KEY', 'textValue' => env('APP_KEY')],
    ],
]);
```

### Laravel Artisan Commands

```bash
# Test connection
php artisan lockbox:test

# List all secrets
php artisan lockbox:list

# Show secret details
php artisan lockbox:show <secret-id> --payload

# Create new secret
php artisan lockbox:create my-secret --description="My secret"

# Add version with values
php artisan lockbox:add-version <secret-id> \
  --entry=KEY1=value1 \
  --entry=KEY2=value2

# Delete secret
php artisan lockbox:delete <secret-id>
```

## 🔒 Exception Handling

The library provides specific exceptions for different error types:

```php
use Tigusigalpa\YandexLockbox\Exceptions\AuthenticationException;
use Tigusigalpa\YandexLockbox\Exceptions\NotFoundException;
use Tigusigalpa\YandexLockbox\Exceptions\RateLimitException;
use Tigusigalpa\YandexLockbox\Exceptions\ValidationException;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;

try {
    $payload = $client->getPayload('secret-id');
} catch (AuthenticationException $e) {
    // Handle 401/403 errors
    echo "Authentication failed: " . $e->getMessage();
} catch (NotFoundException $e) {
    // Handle 404 errors
    echo "Secret not found: " . $e->getMessage();
} catch (RateLimitException $e) {
    // Handle 429 errors
    echo "Rate limit exceeded: " . $e->getMessage();
} catch (ValidationException $e) {
    // Handle 400 errors
    echo "Validation error: " . $e->getMessage();
} catch (LockboxException $e) {
    // Handle other errors
    echo "API error: " . $e->getMessage();
    print_r($e->getContext());
}
```

## 🧪 Testing

### Artisan Commands

#### lockbox:test - Comprehensive Testing

Runs complete test suite with 8 tests:

```bash
# Basic run
php artisan lockbox:test

# With specific folder
php artisan lockbox:test --folder=b1g8dn6s4f5h6j7k8l9m

# With automatic cleanup
php artisan lockbox:test --cleanup
```

**Output:**
```
🚀 Testing Yandex Lockbox Connection
==================================================

✓ Test 1: Listing secrets
✓ Test 2: Creating test secret
   Secret ID: e6q7r8s9t0u1v2w3x4y5
✓ Test 3: Getting secret details
✓ Test 4: Adding version with payload
✓ Test 5: Getting secret payload
✓ Test 6: Listing versions
✓ Test 7: Deactivating secret
✓ Test 8: Activating secret

==================================================
✅ Tests passed: 8
🎉 All tests passed successfully!
```

#### lockbox:list - List Secrets

```bash
# List in default folder
php artisan lockbox:list

# List in specific folder
php artisan lockbox:list --folder=b1g8dn6s4f5h6j7k8l9m

# Limit results
php artisan lockbox:list --page-size=10
```

#### lockbox:show - Show Secret Details

```bash
# Metadata only
php artisan lockbox:show e6q7r8s9t0u1v2w3x4y5

# With payload
php artisan lockbox:show e6q7r8s9t0u1v2w3x4y5 --payload

# Specific version
php artisan lockbox:show e6q7r8s9t0u1v2w3x4y5 --payload --version=version-id
```

#### lockbox:create - Create Secret

```bash
# Simple creation
php artisan lockbox:create my-secret

# With description
php artisan lockbox:create my-secret --description="Production API keys"

# With labels
php artisan lockbox:create my-secret \
  --label=env=production \
  --label=service=api

# With specific folder
php artisan lockbox:create my-secret --folder=b1g8dn6s4f5h6j7k8l9m
```

#### lockbox:add-version - Add Version

```bash
# Interactive mode
php artisan lockbox:add-version e6q7r8s9t0u1v2w3x4y5

# With parameters
php artisan lockbox:add-version e6q7r8s9t0u1v2w3x4y5 \
  --entry=DB_HOST=localhost \
  --entry=DB_USER=admin \
  --entry=DB_PASSWORD=secret

# From JSON file
php artisan lockbox:add-version e6q7r8s9t0u1v2w3x4y5 --file=secrets.json
```

**JSON file format:**
```json
{
  "payloadEntries": [
    {"key": "API_KEY", "textValue": "my-key"},
    {"key": "API_SECRET", "textValue": "my-secret"}
  ]
}
```

#### lockbox:delete - Delete Secret

```bash
# With confirmation
php artisan lockbox:delete e6q7r8s9t0u1v2w3x4y5

# Without confirmation
php artisan lockbox:delete e6q7r8s9t0u1v2w3x4y5 --force
```

### Common Testing Scenarios

#### Scenario 1: First Run

```bash
# 1. Check connection
php artisan lockbox:test

# 2. View existing secrets
php artisan lockbox:list

# 3. View specific secret
php artisan lockbox:show <secret-id> --payload
```

#### Scenario 2: Create New Secret

```bash
# 1. Create secret
php artisan lockbox:create production-db \
  --description="Production database credentials" \
  --label=env=production

# 2. Add values
php artisan lockbox:add-version <secret-id> \
  --entry=DB_HOST=prod-db.example.com \
  --entry=DB_USER=prod_user \
  --entry=DB_PASSWORD=secure_password

# 3. Verify
php artisan lockbox:show <secret-id> --payload
```

#### Scenario 3: Update Secret

```bash
# 1. View current version
php artisan lockbox:show <secret-id> --payload

# 2. Add new version
php artisan lockbox:add-version <secret-id> \
  --entry=DB_PASSWORD=new_password

# 3. Verify new version
php artisan lockbox:show <secret-id> --payload
```

### PHPUnit Tests

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage
```

## 📚 API Reference

### OAuthTokenManager Methods

#### Authentication & Token Management
- `getIamToken(): string` - Get IAM token (cached automatically)
- `listClouds(): array` - List all clouds
- `getFirstCloud(): array` - Get first cloud
- `getFirstCloudId(): string` - Get first cloud ID

#### Folder Management
- `listFolders(string $cloudId): array` - List folders in cloud
- `getFolder(string $folderId): array` - Get folder details
- `getFirstFolder(string $cloudId): array` - Get first folder
- `getFirstFolderId(string $cloudId): string` - Get first folder ID
- `getFirstFolderIdFromFirstCloud(): string` - Get first folder ID from first cloud
- `createFolder(string $iamToken, string $cloudId, string $name, ?string $description = null): array` - Create folder

#### Access Control (Permissions)
- `assignRoleToFolder(string $iamToken, string $folderId, string $subjectId, string $role = 'lockbox.editor', string $subjectType = 'userAccount', bool $waitForCompletion = false, int $maxWaitSeconds = 60): array` - Assign role to folder
- `listFolderAccessBindings(string $iamToken, string $folderId, int $pageSize = 100, ?string $pageToken = null): array` - List folder access bindings (paginated)
- `getAllFolderAccessBindings(string $iamToken, string $folderId): array` - Get all folder access bindings (auto-pagination)

#### User Management
- `getUserByLogin(string $login): array` - Get full user info by Yandex login
- `getUserIdByLogin(string $login): string` - Get user ID (Subject ID) by Yandex login

#### Async Operations
- `waitForOperation(string $iamToken, string $operationId, int $maxWaitSeconds = 60, int $pollIntervalSeconds = 2): array` - Wait for operation to complete
- `getOperation(string $iamToken, string $operationId): array` - Get operation status

### Client Methods

#### Secret Management
- `listSecrets(string $folderId): array` - List secrets in folder
- `getSecret(string $secretId): array` - Get secret metadata
- `createSecret(array $data): array` - Create new secret
- `updateSecret(string $secretId, array $data): array` - Update secret
- `deleteSecret(string $secretId): void` - Delete secret

#### Version Management
- `addVersion(string $secretId, array $data): array` - Add new version to secret
- `getPayload(string $secretId, ?string $versionId = null): array` - Get secret payload

## 📝 Requirements

- PHP 8.0 or higher
- Laravel 8.x - 12.x (optional, for Laravel integration)
- Guzzle HTTP client 7.x or 8.x

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 👤 Author

**Igor Sazonov**

- GitHub: [@tigusigalpa](https://github.com/tigusigalpa)
- Email: sovletig@gmail.com

## 🔗 Links

- [Packagist](https://packagist.org/packages/tigusigalpa/yandex-lockbox-php)
- [GitHub Repository](https://github.com/tigusigalpa/yandex-lockbox-php)
- [Issue Tracker](https://github.com/tigusigalpa/yandex-lockbox-php/issues)
- [Yandex Cloud Documentation](https://yandex.cloud/en/docs/lockbox/)