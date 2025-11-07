<?php

namespace Tigusigalpa\YandexLockbox\Laravel\Commands;

use Illuminate\Console\Command;
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;

class LockboxShowCommand extends Command
{
    protected $signature = 'lockbox:show 
                            {secret-id : Secret ID to display}
                            {--payload : Show secret payload (requires payloadViewer role)}
                            {--version= : Specific version ID to show}';

    protected $description = 'Show details of a specific secret';

    public function handle(Client $client): int
    {
        $secretId = $this->argument('secret-id');

        $this->info("Fetching secret: {$secretId}");
        $this->newLine();

        try {
            // Get secret metadata
            $secret = $client->getSecret($secretId);

            $this->line('<fg=cyan>Secret Details:</>');
            $this->line('ID: ' . $secret['id']);
            $this->line('Name: ' . $secret['name']);
            $this->line('Status: ' . $secret['status']);
            $this->line('Folder ID: ' . $secret['folderId']);
            $this->line('Created At: ' . $secret['createdAt']);
            
            if (isset($secret['description'])) {
                $this->line('Description: ' . $secret['description']);
            }

            if (isset($secret['labels']) && !empty($secret['labels'])) {
                $this->newLine();
                $this->line('<fg=cyan>Labels:</>');
                foreach ($secret['labels'] as $key => $value) {
                    $this->line("  {$key}: {$value}");
                }
            }

            if (isset($secret['currentVersion'])) {
                $this->newLine();
                $this->line('<fg=cyan>Current Version:</>');
                $this->line('ID: ' . $secret['currentVersion']['id']);
                $this->line('Status: ' . $secret['currentVersion']['status']);
                $this->line('Created At: ' . $secret['currentVersion']['createdAt']);
            }

            // Show payload if requested
            if ($this->option('payload')) {
                $this->newLine();
                $this->line('<fg=cyan>Payload:</>');

                try {
                    $versionId = $this->option('version');
                    $payload = $client->getPayload($secretId, $versionId);

                    $this->line('Version ID: ' . $payload['versionId']);
                    $this->newLine();

                    $headers = ['Key', 'Value'];
                    $rows = [];

                    foreach ($payload['entries'] as $entry) {
                        $value = $entry['textValue'] ?? '[binary data]';
                        
                        // Mask sensitive values
                        if (strlen($value) > 20 && !$this->option('no-interaction')) {
                            $value = substr($value, 0, 10) . '...' . substr($value, -10);
                        }

                        $rows[] = [$entry['key'], $value];
                    }

                    $this->table($headers, $rows);

                } catch (LockboxException $e) {
                    $this->error('Could not retrieve payload: ' . $e->getMessage());
                    $this->warn('Make sure you have lockbox.payloadViewer role');
                }
            } else {
                $this->newLine();
                $this->comment('Use --payload option to show secret values');
            }

            return self::SUCCESS;

        } catch (LockboxException $e) {
            $this->error('Lockbox API Error: ' . $e->getMessage());
            $this->line('Code: ' . $e->getCode());
            return self::FAILURE;
        }
    }
}
