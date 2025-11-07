<?php

namespace Tigusigalpa\YandexLockbox\Laravel\Commands;

use Illuminate\Console\Command;
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;

class LockboxListCommand extends Command
{
    protected $signature = 'lockbox:list 
                            {--folder= : Folder ID to list secrets from}
                            {--page-size=50 : Number of secrets per page}';

    protected $description = 'List all secrets in a Yandex Lockbox folder';

    public function handle(Client $client): int
    {
        $folderId = $this->option('folder') ?? config('lockbox.default_folder_id');

        if (!$folderId) {
            $this->error('Folder ID is required. Provide it via --folder option or set YANDEX_LOCKBOX_FOLDER_ID in .env');
            return self::FAILURE;
        }

        $this->info("Listing secrets in folder: {$folderId}");
        $this->newLine();

        try {
            $result = $client->listSecrets($folderId, [
                'pageSize' => (int) $this->option('page-size'),
            ]);

            if (empty($result['secrets'])) {
                $this->warn('No secrets found in this folder.');
                return self::SUCCESS;
            }

            $headers = ['ID', 'Name', 'Status', 'Created At', 'Description'];
            $rows = [];

            foreach ($result['secrets'] as $secret) {
                $rows[] = [
                    $secret['id'],
                    $secret['name'],
                    $secret['status'],
                    $secret['createdAt'] ?? 'N/A',
                    substr($secret['description'] ?? '', 0, 50),
                ];
            }

            $this->table($headers, $rows);
            $this->info('Total: ' . count($result['secrets']) . ' secret(s)');

            if (isset($result['nextPageToken'])) {
                $this->warn('More results available. Use pagination to see all secrets.');
            }

            return self::SUCCESS;

        } catch (LockboxException $e) {
            $this->error('Lockbox API Error: ' . $e->getMessage());
            $this->line('Code: ' . $e->getCode());
            return self::FAILURE;
        }
    }
}
