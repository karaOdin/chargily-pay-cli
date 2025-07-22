<?php

namespace App\Commands;

use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\select;

class AppSwitchCommand extends Command
{
    protected $signature = 'app:switch';

    protected $description = 'Switch between configured applications';

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
        $apps = $this->config->getApplications();
        $current = $this->config->getCurrentApplication();

        if (empty($apps)) {
            $this->error('No applications configured. Run "chargily configure" first.');

            return 1;
        }

        if (count($apps) === 1) {
            $this->info('Only one application configured.');

            return 0;
        }

        $this->line('');
        $this->line('🏢 Switch Application');
        $this->line('');

        $options = [];
        foreach ($apps as $id => $app) {
            $indicator = $id === $current ? '🟢' : '⚪';
            $mode = $this->config->getCurrentMode($id);
            $modeIcon = $mode === 'live' ? '🔴' : '🧪';
            $options[$id] = "{$indicator} {$app['name']} ({$modeIcon} {$mode})";
        }

        $selected = select('Select application to switch to:', $options);

        if ($selected === $current) {
            $this->info('Already using this application.');

            return 0;
        }

        $this->config->setCurrentApplication($selected);
        $app = $this->config->getApplication($selected);
        $mode = $this->config->getCurrentMode($selected);

        $this->info("✅ Switched to: {$app['name']} ({$mode} mode)");

        // Show quick stats
        try {
            $result = $this->api->setApplication($selected)->setMode($mode)->testConnection();
            if ($result['success']) {
                $balance = $result['data']['wallets'][0]['balance'] ?? 0;
                $this->line("💰 Balance: {$balance} DZD");
            }
        } catch (\Exception $e) {
            // Ignore errors for quick switch
        }

        return 0;
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
