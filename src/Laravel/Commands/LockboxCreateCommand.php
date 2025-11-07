<?php

namespace Tigusigalpa\YandexLockbox\Laravel\Commands;

use Illuminate\Console\Command;
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;

class LockboxCreateCommand extends Command
{
    protected $signature = 'lockbox:create 
                            {name : Secret name}
                            {--folder= : Folder ID (default from config)}
                            {--description= : Secret description}
                            {--label=* : Labels in key=value format}';

    protected $description = 'Create a new secret in Yandex Lockbox';

    public function handle(Client $client): int
    {
        $name = $this->argument('name');
        $folderId = $this->option('folder') ?? config('lockbox.default_folder_id');

        if (!$folderId) {
            $this->error('Folder ID is required. Provide it via --folder option or set YANDEX_LOCKBOX_FOLDER_ID in .env');
            return self::FAILURE;
        }

        $this->info("Creating secret: {$name}");

        $data = [
            'folderId' => $folderId,
            'name' => $name,
        ];

        if ($description = $this->option('description')) {
            $data['description'] = $description;
        }

        // Parse labels
        $labels = [];
        foreach ($this->option('label') as $label) {
            if (strpos($label, '=') !== false) {
                [$key, $value] = explode('=', $label, 2);
                $labels[trim($key)] = trim($value);
            }
        }

        if (!empty($labels)) {
            $data['labels'] = $labels;
        }

        try {
            $secret = $client->createSecret($data);

            $this->newLine();
            $this->info('✅ Secret created successfully!');
            $this->line('ID: ' . $secret['id']);
            $this->line('Name: ' . $secret['name']);
            $this->line('Status: ' . $secret['status']);

            $this->newLine();
            $this->comment('Next steps:');
            $this->line('1. Add a version with payload:');
            $this->line("   php artisan lockbox:add-version {$secret['id']}");
            $this->line('2. View the secret:');
            $this->line("   php artisan lockbox:show {$secret['id']}");

            return self::SUCCESS;

        } catch (LockboxException $e) {
            $this->error('Lockbox API Error: ' . $e->getMessage());
            $this->line('Code: ' . $e->getCode());
            return self::FAILURE;
        }
    }
}
