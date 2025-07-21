<?php

namespace App\Commands;

use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;

class ModeSwitchCommand extends Command
{
    protected $signature = 'mode:switch {--app= : Switch mode for specific application}';
    protected $description = 'Switch between test and live modes';

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
        $appId = $this->option('app') ?: $this->config->getCurrentApplication();
        $app = $this->config->getApplication($appId);
        $currentMode = $this->config->getCurrentMode($appId);

        $this->line('');
        $this->line('🔄 Mode Switch');
        $this->line('');
        $this->line("Application: {$app['name']}");
        $this->line("Current Mode: " . ($currentMode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE'));
        $this->line('');

        $newMode = select(
            'Switch to which mode?',
            [
                'test' => '🧪 Test Mode (Safe development)',
                'live' => '🔴 Live Mode (Real payments)',
            ],
            default: $currentMode === 'test' ? 'live' : 'test'
        );

        if ($newMode === $currentMode) {
            $this->info('Already in that mode.');
            return 0;
        }

        // Safety check for live mode
        if ($newMode === 'live') {
            $this->warn('⚠️  You are switching to LIVE MODE!');
            $this->line('Real money transactions will be processed.');
            $this->line('');
            
            if (!confirm('Are you sure you want to switch to live mode?', false)) {
                $this->info('Mode switch cancelled.');
                return 0;
            }

            // Check if live API key is configured
            $liveKey = $this->config->getApiKey($appId, 'live');
            if (!$liveKey) {
                $this->error('❌ No live mode API key configured!');
                $this->line('💡 Run: chargily configure --app=' . $appId);
                return 1;
            }
        }

        $this->config->setCurrentMode($appId, $newMode);

        $modeDisplay = $newMode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE';
        $this->info("✅ Switched to {$modeDisplay}");

        // Test connection in new mode
        $this->line('');
        $this->line('🧪 Testing connection in new mode...');
        
        try {
            $result = $this->api->setApplication($appId)->setMode($newMode)->testConnection();
            
            if ($result['success']) {
                $this->info('✅ Connection successful!');
                $balance = $result['data']['wallets'][0]['balance'] ?? 0;
                $this->line("💰 Balance: {$balance} DZD");
            } else {
                $this->error('❌ Connection failed: ' . $result['message']);
                $this->line('💡 Check your API key configuration');
            }
        } catch (\Exception $e) {
            $this->error('❌ Connection test failed: ' . $e->getMessage());
        }

        return 0;
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}