<?php

namespace Tigusigalpa\YandexLockbox\Laravel\Commands;

use Illuminate\Console\Command;
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;

class LockboxTestCommand extends Command
{
    protected $signature = 'lockbox:test 
                            {--folder= : Folder ID to test with}
                            {--cleanup : Delete test secret after test}';

    protected $description = 'Test Yandex Lockbox connection and operations';

    public function handle(Client $client): int
    {
        $this->info('🚀 Testing Yandex Lockbox Connection');
        $this->line(str_repeat('=', 50));
        $this->newLine();

        $folderId = $this->option('folder') ?? config('lockbox.default_folder_id');

        if (!$folderId) {
            $this->error('Folder ID is required. Provide it via --folder option or set YANDEX_LOCKBOX_FOLDER_ID in .env');
            return self::FAILURE;
        }

        $testSecretId = null;
        $testsPassed = 0;
        $testsFailed = 0;

        try {
            // Test 1: List secrets
            $this->task('Test 1: Listing secrets', function () use ($client, $folderId) {
                $result = $client->listSecrets($folderId);
                return is_array($result) && isset($result['secrets']);
            }) ? $testsPassed++ : $testsFailed++;

            // Test 2: Create secret
            $this->newLine();
            $secretName = 'test-secret-' . time();
            
            $created = $this->task('Test 2: Creating test secret', function () use ($client, $folderId, $secretName, &$testSecretId) {
                $result = $client->createSecret([
                    'folderId' => $folderId,
                    'name' => $secretName,
                    'description' => 'Test secret created by lockbox:test command',
                    'labels' => ['test' => 'true'],
                ]);
                
                $testSecretId = $result['id'] ?? null;
                return !empty($testSecretId);
            });

            $created ? $testsPassed++ : $testsFailed++;

            if (!$testSecretId) {
                throw new \Exception('Failed to create test secret');
            }

            $this->line("   Secret ID: {$testSecretId}");

            // Test 3: Get secret
            $this->newLine();
            $this->task('Test 3: Getting secret details', function () use ($client, $testSecretId) {
                $result = $client->getSecret($testSecretId);
                return isset($result['id']) && $result['id'] === $testSecretId;
            }) ? $testsPassed++ : $testsFailed++;

            // Test 4: Add version
            $this->newLine();
            $this->task('Test 4: Adding version with payload', function () use ($client, $testSecretId) {
                $result = $client->addVersion($testSecretId, [
                    'entries' => [
                        ['key' => 'TEST_KEY_1', 'textValue' => 'test-value-1'],
                        ['key' => 'TEST_KEY_2', 'textValue' => 'test-value-2'],
                    ],
                ]);
                return isset($result['id']);
            }) ? $testsPassed++ : $testsFailed++;

            // Test 5: Get payload
            $this->newLine();
            $this->task('Test 5: Getting secret payload', function () use ($client, $testSecretId) {
                $result = $client->getPayload($testSecretId);
                return isset($result['entries']) && count($result['entries']) === 2;
            }) ? $testsPassed++ : $testsFailed++;

            // Test 6: List versions
            $this->newLine();
            $this->task('Test 6: Listing versions', function () use ($client, $testSecretId) {
                $result = $client->listVersions($testSecretId);
                return isset($result['versions']) && count($result['versions']) > 0;
            }) ? $testsPassed++ : $testsFailed++;

            // Test 7: Deactivate secret
            $this->newLine();
            $this->task('Test 7: Deactivating secret', function () use ($client, $testSecretId) {
                $client->deactivateSecret($testSecretId);
                return true;
            }) ? $testsPassed++ : $testsFailed++;

            // Test 8: Activate secret
            $this->newLine();
            $this->task('Test 8: Activating secret', function () use ($client, $testSecretId) {
                $client->activateSecret($testSecretId);
                return true;
            }) ? $testsPassed++ : $testsFailed++;

            // Cleanup
            if ($this->option('cleanup') || $this->confirm('Delete test secret?', true)) {
                $this->newLine();
                $this->task('Cleanup: Deleting test secret', function () use ($client, $testSecretId) {
                    $client->deleteSecret($testSecretId);
                    return true;
                });
            } else {
                $this->newLine();
                $this->comment("Test secret kept: {$testSecretId}");
                $this->line("Delete it with: php artisan lockbox:delete {$testSecretId}");
            }

            // Summary
            $this->newLine();
            $this->line(str_repeat('=', 50));
            $this->info("✅ Tests passed: {$testsPassed}");
            
            if ($testsFailed > 0) {
                $this->error("❌ Tests failed: {$testsFailed}");
                return self::FAILURE;
            }

            $this->info('🎉 All tests passed successfully!');
            return self::SUCCESS;

        } catch (LockboxException $e) {
            $this->newLine();
            $this->error('❌ Lockbox API Error: ' . $e->getMessage());
            $this->line('Code: ' . $e->getCode());

            // Try cleanup
            if ($testSecretId) {
                $this->newLine();
                $this->warn('Attempting to cleanup test secret...');
                try {
                    $client->deleteSecret($testSecretId);
                    $this->info('Test secret deleted.');
                } catch (\Throwable $cleanupError) {
                    $this->error("Could not delete test secret: {$testSecretId}");
                }
            }

            return self::FAILURE;

        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('❌ Unexpected Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
