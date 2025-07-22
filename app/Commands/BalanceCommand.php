<?php

namespace App\Commands;

use App\Exceptions\ChargilyApiException;
use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class BalanceCommand extends Command
{
    protected $signature = 'balance 
                           {--fresh : Fetch fresh balance (ignore cache)}
                           {--app= : Check balance for specific application}
                           {--all : Show balance for all applications}';

    protected $description = 'Check account balance';

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
        if ($this->option('all')) {
            return $this->showAllBalances();
        }

        // Check if we have valid applications configured
        if ($this->checkNoValidApps()) {
            return $this->showWizardWithDelay();
        }

        $appId = $this->option('app') ?: $this->config->getCurrentApplication();
        $fresh = $this->option('fresh');

        return $this->showSingleBalance($appId, $fresh);
    }

    protected function showSingleBalance(string $appId, bool $fresh = false): int
    {
        $app = $this->config->getApplication($appId);
        $mode = $this->config->getCurrentMode($appId);

        $this->displayHeader($app['name'], $mode);

        try {
            $balance = $this->api->setApplication($appId)->setMode($mode)->getBalance($fresh);

            $this->displayBalance($balance, $fresh);

            // Wait for user input before returning (when called from menu)
            $this->waitForUser();

            return 0;

        } catch (ChargilyApiException $e) {
            $this->error('❌ '.$e->getUserMessage());
            $this->line('💡 '.$e->getSuggestedAction());

            return 1;
        }
    }

    protected function showAllBalances(): int
    {
        $apps = $this->config->getApplications();

        $this->line('');
        $this->line('💰 All Application Balances');
        $this->line('');

        $totalTestDZD = 0;
        $totalLiveDZD = 0;

        foreach ($apps as $appId => $app) {
            $this->line("🏢 {$app['name']} ({$appId})");

            foreach (['test', 'live'] as $mode) {
                $apiKey = $this->config->getApiKey($appId, $mode);

                if (! $apiKey) {
                    $this->line("   {$mode}: ❌ Not configured");

                    continue;
                }

                try {
                    $balance = $this->api->setApplication($appId)->setMode($mode)->getBalance();
                    $dzdBalance = $balance['wallets'][0]['balance'] ?? 0;

                    if ($mode === 'test') {
                        $totalTestDZD += $dzdBalance;
                    } else {
                        $totalLiveDZD += $dzdBalance;
                    }

                    $modeIcon = $mode === 'live' ? '🔴' : '🧪';
                    $this->line("   {$modeIcon} {$mode}: {$dzdBalance} DZD");

                } catch (\Exception $e) {
                    $this->line("   {$mode}: ❌ Error: ".$e->getMessage());
                }
            }

            $this->line('');
        }

        $this->line('📊 Summary:');
        $this->line("   🧪 Total Test: {$totalTestDZD} DZD");
        $this->line("   🔴 Total Live: {$totalLiveDZD} DZD");
        $this->line('   💰 Grand Total: '.($totalTestDZD + $totalLiveDZD).' DZD');

        return 0;
    }

    protected function displayHeader(string $appName, string $mode): void
    {
        $modeDisplay = $mode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE';

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                     Account Balance                         ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("Application: {$appName}");
        $this->line("Mode: {$modeDisplay}");
        $this->line('');
    }

    protected function displayBalance(array $balance, bool $fresh): void
    {
        $this->line('💰 Balance Information:');
        $this->line('');

        $headers = ['Currency', 'Total Balance', 'Ready for Payout', 'On Hold'];
        $rows = [];

        foreach ($balance['wallets'] as $wallet) {
            $rows[] = [
                strtoupper($wallet['currency']),
                number_format($wallet['balance']),
                number_format($wallet['ready_for_payout']),
                number_format($wallet['on_hold']),
            ];
        }

        $this->table($headers, $rows);

        $cacheStatus = $fresh ? '(fresh)' : '(may be cached)';
        $this->line('');
        $this->line('Environment: '.($balance['livemode'] ? 'Live' : 'Test')." {$cacheStatus}");

        if (! $fresh) {
            $this->line('💡 Use --fresh to get real-time balance');
        }
    }

    protected function waitForUser(): void
    {
        // Only wait if we're in an interactive environment and this might be called from menu
        if ($this->input->isInteractive() && ! $this->option('fresh') && ! $this->option('all')) {
            $this->line('');
            $this->info('Press any key to continue...');
            $this->line('');

            // Simple way to wait for user input
            fgets(STDIN);
        }
    }

    protected function checkNoValidApps(): bool
    {
        $apps = $this->config->getApplications();

        if (empty($apps)) {
            return true;
        }

        // Check if any app has at least one valid API key
        foreach ($apps as $appId => $appConfig) {
            $testKey = $appConfig['test']['api_key'] ?? null;
            $liveKey = $appConfig['live']['api_key'] ?? null;

            // An app is valid if it has at least one non-null API key
            if (! empty($testKey) || ! empty($liveKey)) {
                return false;
            }
        }

        return true;
    }

    protected function showWizardWithDelay(): int
    {
        $this->line('');
        $this->warn('⚠️  No valid applications found!');
        $this->line('Setting up your first application...');

        // Add delay before showing wizard
        sleep(2);

        // Call the main menu command to show wizard
        return $this->call('menu');
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
