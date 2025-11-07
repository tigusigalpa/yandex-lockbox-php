<?php

namespace Tigusigalpa\YandexLockbox\Laravel\Commands;

use Illuminate\Console\Command;
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;

class LockboxAddVersionCommand extends Command
{
    protected $signature = 'lockbox:add-version 
                            {secret-id : Secret ID}
                            {--entry=* : Entries in key=value format}
                            {--file= : JSON file with entries}';

    protected $description = 'Add a new version to a secret with payload';

    public function handle(Client $client): int
    {
        $secretId = $this->argument('secret-id');

        $this->info("Adding version to secret: {$secretId}");

        $entries = [];

        // Load from file if provided
        if ($file = $this->option('file')) {
            if (!file_exists($file)) {
                $this->error("File not found: {$file}");
                return self::FAILURE;
            }

            $json = file_get_contents($file);
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON file: ' . json_last_error_msg());
                return self::FAILURE;
            }

            if (isset($data['entries'])) {
                $entries = $data['entries'];
            } else {
                // Convert flat key-value to entries format
                foreach ($data as $key => $value) {
                    $entries[] = ['key' => $key, 'textValue' => $value];
                }
            }
        }

        // Parse command line entries
        foreach ($this->option('entry') as $entry) {
            if (strpos($entry, '=') !== false) {
                [$key, $value] = explode('=', $entry, 2);
                $entries[] = [
                    'key' => trim($key),
                    'textValue' => trim($value),
                ];
            }
        }

        // Interactive mode if no entries provided
        if (empty($entries)) {
            $this->warn('No entries provided. Entering interactive mode...');
            $this->newLine();

            while (true) {
                $key = $this->ask('Entry key (or press Enter to finish)');
                
                if (empty($key)) {
                    break;
                }

                $value = $this->secret('Entry value');
                
                $entries[] = [
                    'key' => $key,
                    'textValue' => $value,
                ];

                $this->info("Added: {$key}");
            }
        }

        if (empty($entries)) {
            $this->error('No entries to add. Version not created.');
            return self::FAILURE;
        }

        try {
            $version = $client->addVersion($secretId, ['entries' => $entries]);

            $this->newLine();
            $this->info('✅ Version added successfully!');
            $this->line('Version ID: ' . $version['id']);
            $this->line('Entries count: ' . count($entries));

            $this->newLine();
            $this->line('Keys added:');
            foreach ($entries as $entry) {
                $this->line('  - ' . $entry['key']);
            }

            return self::SUCCESS;

        } catch (LockboxException $e) {
            $this->error('Lockbox API Error: ' . $e->getMessage());
            $this->line('Code: ' . $e->getCode());
            return self::FAILURE;
        }
    }
}
