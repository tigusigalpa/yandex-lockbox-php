<?php

namespace Tigusigalpa\YandexLockbox\Laravel\Commands;

use Illuminate\Console\Command;
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;

class LockboxDeleteCommand extends Command
{
    protected $signature = 'lockbox:delete 
                            {secret-id : Secret ID to delete}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a secret from Yandex Lockbox';

    public function handle(Client $client): int
    {
        $secretId = $this->argument('secret-id');

        try {
            // Get secret details first
            $secret = $client->getSecret($secretId);

            $this->warn("You are about to delete the following secret:");
            $this->line('ID: ' . $secret['id']);
            $this->line('Name: ' . $secret['name']);
            $this->line('Status: ' . $secret['status']);
            $this->newLine();

            // Confirm deletion
            if (!$this->option('force')) {
                if (!$this->confirm('Are you sure you want to delete this secret?', false)) {
                    $this->info('Deletion cancelled.');
                    return self::SUCCESS;
                }
            }

            $this->info('Deleting secret...');
            $client->deleteSecret($secretId);

            $this->newLine();
            $this->info('✅ Secret deleted successfully!');

            return self::SUCCESS;

        } catch (LockboxException $e) {
            $this->error('Lockbox API Error: ' . $e->getMessage());
            $this->line('Code: ' . $e->getCode());
            return self::FAILURE;
        }
    }
}
