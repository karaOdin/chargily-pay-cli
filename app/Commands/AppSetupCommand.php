<?php

namespace App\Commands;

use App\Exceptions\ChargilyApiException;
use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class AppSetupCommand extends Command
{
    protected $signature = 'app:setup';

    protected $description = 'First-time setup for Chargily Pay CLI';

    protected ConfigurationService $config;

    protected ChargilyApiService $api;

    public function __construct(ConfigurationService $config, ChargilyApiService $api)
    {
        parent::__construct();
        $this->config = $config;
        $this->api = $api;
    }

    public function handle(): int
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                     Initial Setup                           ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');

        // Get API credentials
        $testApiKey = $this->getApiKey('test');
        if (! $testApiKey) {
            return 1;
        }

        $liveApiKey = $this->getApiKey('live');
        if (! $liveApiKey) {
            return 1;
        }

        // Test the API keys
        if (! $this->testApiKeys($testApiKey, $liveApiKey)) {
            return 1;
        }

        // Create the application configuration
        if (! $this->createApplication($testApiKey, $liveApiKey)) {
            return 1;
        }

        $this->line('');
        $this->info('✅ Setup completed successfully!');
        $this->line('🎉 You can now use all CLI commands.');
        $this->line('');

        return 0;
    }

    protected function getApiKey(string $mode): ?string
    {
        $modeDisplay = $mode === 'test' ? '🧪 TEST' : '🔴 LIVE';

        $this->line("📋 {$modeDisplay} Mode API Key");
        $this->line('');

        $attempt = 0;
        $maxAttempts = 3;

        while ($attempt < $maxAttempts) {
            $apiKey = text(
                "Enter your {$mode} API key",
                placeholder: $mode === 'test' ? 'test_sk_...' : 'live_sk_...',
                required: true,
                validate: fn ($value) => str_starts_with($value, $mode === 'test' ? 'test_' : 'live_')
                    ? null
                    : "API key must start with '{$mode}_'"
            );

            if ($apiKey) {
                return $apiKey;
            }

            $attempt++;
            if ($attempt < $maxAttempts) {
                $this->warn("⚠️ Invalid API key format. Please try again. ({$attempt}/{$maxAttempts})");
            }
        }

        $this->error('❌ Maximum attempts exceeded. Setup cancelled.');

        return null;
    }

    protected function testApiKeys(string $testKey, string $liveKey): bool
    {
        $this->line('🔍 Validating API keys...');
        $this->line('');

        // Test the test key
        if (! $this->testSingleApiKey($testKey, 'test')) {
            return false;
        }

        // Test the live key
        if (! $this->testSingleApiKey($liveKey, 'live')) {
            return false;
        }

        return true;
    }

    protected function testSingleApiKey(string $apiKey, string $mode): bool
    {
        $modeDisplay = $mode === 'test' ? '🧪 TEST' : '🔴 LIVE';

        try {
            // Create temporary application config for testing
            $tempAppId = 'temp_setup';
            $this->config->createApplication($tempAppId, 'Setup Temp', [
                $mode => ['api_key' => $apiKey],
            ]);

            // Test API connection
            $this->api->setApplication($tempAppId)->setMode($mode);
            $this->api->getBalance(true);

            // Clean up temporary config
            $this->config->deleteApplication($tempAppId);

            $this->info("✅ {$modeDisplay} API key is valid");

            return true;

        } catch (ChargilyApiException $e) {
            // Clean up temporary config
            try {
                $this->config->deleteApplication($tempAppId);
            } catch (\Exception $cleanupError) {
                // Ignore cleanup errors
            }

            $this->error("❌ {$modeDisplay} API key validation failed");

            if (confirm("Would you like to try a different {$mode} API key?", true)) {
                $newKey = $this->getApiKey($mode);
                if ($newKey) {
                    return $this->testSingleApiKey($newKey, $mode);
                }
            }

            return false;
        }
    }

    protected function createApplication(string $testKey, string $liveKey): bool
    {
        try {
            $appName = text(
                'Application name',
                default: 'Main Business',
                required: true
            );

            $appId = 'main';

            $this->config->createApplication($appId, $appName, [
                'test' => [
                    'api_key' => $testKey,
                    'webhook_secret' => '',
                    'default_success_url' => '',
                    'default_failure_url' => '',
                ],
                'live' => [
                    'api_key' => $liveKey,
                    'webhook_secret' => '',
                    'default_success_url' => '',
                    'default_failure_url' => '',
                ],
            ]);

            // Set as current application
            $this->config->setCurrentApplication($appId);
            $this->config->setCurrentMode($appId, 'test'); // Start in test mode

            $this->info("✅ Application '{$appName}' created successfully");

            return true;

        } catch (\Exception $e) {
            $this->error('❌ Failed to create application: '.$e->getMessage());

            return false;
        }
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
