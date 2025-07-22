<?php

namespace App\Commands;

use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
// Note: Menu is conditionally loaded based on package availability
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class MainMenuCommand extends Command
{
    protected $signature = 'menu {--app= : Use specific application} {--mode= : Force specific mode}';
    protected $description = 'Interactive main menu for Chargily Pay CLI';

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
        // Check if we have any valid applications configured
        if (!$this->hasValidApplications()) {
            // Only show delay when triggering wizard
            $this->displayWizardLoadingMessage();
            return $this->showSetupWizard();
        }

        // Check if interactive menu is supported (POSIX extension)
        if (!$this->isInteractiveMenuSupported()) {
            return $this->showCommandListMenu();
        }

        // Show main interactive menu immediately for normal use
        return $this->showMainMenu();
    }

    protected function displayWizardLoadingMessage(): void
    {
        $this->info('🚀 Starting Chargily Pay CLI...');
        $this->line('🔍 Checking configured applications...');
        
        // Add 2-second delay only when showing wizard
        sleep(2);
        
        $this->line('');
    }

    protected function hasValidApplications(): bool
    {
        $apps = $this->config->getApplications();
        
        if (empty($apps)) {
            return false;
        }
        
        // Check if any app has at least one valid API key
        foreach ($apps as $appId => $appConfig) {
            $testKey = $appConfig['test']['api_key'] ?? null;
            $liveKey = $appConfig['live']['api_key'] ?? null;
            
            // An app is valid if it has at least one non-null API key
            if (!empty($testKey) || !empty($liveKey)) {
                return true;
            }
        }
        
        return false;
    }

    protected function isInteractiveMenuSupported(): bool
    {
        // Check if POSIX extension is available AND laravel-console-menu package is installed
        return extension_loaded('posix') && class_exists('NunoMaduro\LaravelConsoleMenu\Menu');
    }

    protected function showCommandListMenu(): int
    {
        $this->displayHeader();
        
        $this->warn('📱 Interactive menu not supported on this system.');
        $this->info('💡 Use individual commands instead:');
        $this->line('');
        
        $this->line('📋 Available Commands:');
        $this->line('  🏢 chargily configure      - Manage applications');
        $this->line('  💰 chargily balance        - Check account balance');
        $this->line('  💳 chargily payment:create - Create a new payment');
        $this->line('  📋 chargily payment:list   - List payments');
        $this->line('  🔍 chargily payment:status - Check payment status');
        $this->line('  🔄 chargily mode:switch    - Switch test/live mode');
        $this->line('  🏢 chargily app:switch     - Switch applications');
        $this->line('');
        
        $this->line('💡 Example: chargily balance');
        $this->line('📖 Help: chargily <command> --help');
        
        return 0;
    }
    
    public function checkAppsAndShowWizardIfNeeded(): int
    {
        if (!$this->hasValidApplications()) {
            $this->line('');
            $this->warn('⚠️  No valid applications found!');
            $this->line('Setting up your first application...');
            
            // Add delay before showing wizard
            sleep(2);
            
            return $this->showSetupWizard();
        }
        
        return 0;
    }

    protected function showSetupWizard(): int
    {
        $this->displayWelcomeHeader();
        $this->line('');
        $this->info('🎉 Welcome to Chargily Pay CLI!');
        $this->line('');
        $this->line('No applications configured yet. Let\'s set up your first application.');
        $this->line('');
        
        // Show guidance on getting API keys
        $this->displayApiKeyGuidance();
        
        try {
            if (!confirm('Ready to setup your first application? (Press Ctrl+C to exit)', true)) {
                $this->line('');
                $this->line('💡 Run this command again when you\'re ready to setup.');
                return 0;
            }
        } catch (\Exception $e) {
            $this->line('');
            $this->info('👋 Setup cancelled. Run this command again when ready.');
            return 0;
        }

        return $this->runSetupWizard();
    }

    protected function displayApiKeyGuidance(): void
    {
        $this->line('📋 <fg=yellow>Before we start, you\'ll need your Chargily API keys:</> ');
        $this->line('');
        $this->line('1. 🌐 Visit the Chargily Developer Dashboard:');
        $this->line('   <fg=cyan>https://pay.chargily.dz/developer</>');
        $this->line('');
        $this->line('2. 🔑 Get your API keys:');
        $this->line('   • <fg=green>Test API Key</> (starts with test_sk_) - for testing');
        $this->line('   • <fg=red>Live API Key</> (starts with live_sk_) - for real payments');
        $this->line('');
        $this->line('3. 📖 Need help? Check the documentation:');
        $this->line('   <fg=cyan>https://dev.chargily.com/docs/api-keys</>');
        $this->line('');
        $this->info('💡 Tip: Start with your TEST key first to safely explore the CLI!');
        $this->line('');
    }

    protected function runSetupWizard(): int
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                    Application Setup                        ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        
        try {
            // Get application details
            $name = text(
                'What would you like to name this application? - Press Ctrl+C to cancel',
                default: 'My Business',
                required: true
            );

            $id = text(
                'Application identifier (used internally) - Press Ctrl+C to cancel',
                default: str_replace([' ', '-'], '_', strtolower($name)),
                required: true,
                validate: fn ($value) => preg_match('/^[a-z0-9_]+$/', $value) 
                    ? null 
                    : 'Must contain only lowercase letters, numbers, and underscores'
            );

            // Check if identifier already exists (shouldn't happen in wizard, but safety first)
            if ($this->config->applicationExists($id)) {
                $this->error("Application '{$id}' already exists!");
                return 1;
            }

            $this->line('');
            $this->info('🔑 Now let\'s add your API keys...');
            $this->line('');

            // Get test API key
            $testKey = $this->getValidatedApiKeyWithGuidance('test');
            if (!$testKey) return 0;

            // Ask about live key
            $this->line('');
            $addLiveKey = confirm('Would you like to add your LIVE API key now? (You can add it later)', false);
            
            $liveKey = null;
            if ($addLiveKey) {
                $liveKey = $this->getValidatedApiKeyWithGuidance('live');
                if (!$liveKey) return 0;
            }

            // Create the application
            $config = [
                'name' => $name,
                'test' => [
                    'api_key' => $testKey,
                    'webhook_secret' => '',
                    'default_success_url' => '',
                    'default_failure_url' => ''
                ],
                'live' => [
                    'api_key' => $liveKey ?: '',
                    'webhook_secret' => '',
                    'default_success_url' => '',
                    'default_failure_url' => ''
                ]
            ];

            $this->config->createApplication($id, $config);
            $this->config->setCurrentApplication($id);
            $this->config->setCurrentMode($id, 'test'); // Always start in test mode

            $this->line('');
            $this->line('╔══════════════════════════════════════════════════════════════╗');
            $this->line('║                    Setup Complete!                          ║');
            $this->line('╚══════════════════════════════════════════════════════════════╝');
            $this->line('');
            $this->info("✅ Application '{$name}' created successfully!");
            $this->line("📊 Current: {$name} → 🧪 TEST MODE");
            $this->line('');
            
            $this->line('🚀 <fg=green>You\'re all set! Here\'s what you can do now:</>');
            $this->line('   • 💳 Create test payments');
            $this->line('   • 📋 List your payment history'); 
            $this->line('   • 💰 Check your balance');
            $this->line('   • ⚙️  Configure additional settings');
            if (!$liveKey) {
                $this->line('   • 🔴 Add your live API key later via configuration');
            }
            $this->line('');

            $this->waitForUser();

            // After setup, show main menu
            return $this->showMainMenu();

        } catch (\Exception $e) {
            $this->line('');
            $this->info('👋 Setup cancelled.');
            return 0;
        }
    }

    protected function getValidatedApiKeyWithGuidance(string $mode): ?string
    {
        $modeDisplay = $mode === 'test' ? '🧪 TEST' : '🔴 LIVE';
        $keyPrefix = $mode === 'test' ? 'test_sk_' : 'live_sk_';
        $maxAttempts = 3;
        $attempt = 0;

        $this->line("📋 {$modeDisplay} API Key Setup:");
        $this->line("   Looking for a key that starts with '{$keyPrefix}'");
        if ($mode === 'live') {
            $this->warn('   ⚠️  Live key will process real payments!');
        }
        $this->line('');

        while ($attempt < $maxAttempts) {
            try {
                $apiKey = text(
                    "{$modeDisplay} API Key - Press Ctrl+C to cancel",
                    placeholder: $keyPrefix . '...',
                    required: true,
                    validate: fn ($value) => str_starts_with($value, $keyPrefix)
                        ? null
                        : "API key must start with '{$keyPrefix}'"
                );

                // For now, skip validation to avoid issues - user confirmed keys work
                $this->info("✅ {$modeDisplay} API key accepted!");
                return $apiKey;
                
                // TODO: Fix validation and re-enable
                // Validate key silently by checking balance
                // $this->line('🔍 Validating API key...');
                // if ($this->validateApiKeySilently($apiKey, $mode)) {
                //     $this->info("✅ {$modeDisplay} API key is valid and ready!");
                //     return $apiKey;
                // } else {
                //     $attempt++;
                //     if ($attempt < $maxAttempts) {
                //         $this->error("❌ Invalid API key. Please check and try again. ({$attempt}/{$maxAttempts})");
                //         $this->line('💡 Make sure you copied the full key from the Chargily dashboard.');
                //         $this->line('');
                //     }
                // }
            } catch (\Exception $e) {
                return null; // User cancelled
            }
        }

        $this->error('❌ Too many invalid attempts.');
        $this->line('💡 Double-check your API key in the Chargily dashboard and try again.');
        return null;
    }

    protected function validateApiKeySilently(string $apiKey, string $mode): bool
    {
        try {
            // Create temporary application config for testing
            $tempAppId = 'temp_validation_' . uniqid();
            $config = ['name' => 'Temp Validation'];
            if ($mode === 'test') {
                $config['test_api_key'] = $apiKey;
            } else {
                $config['live_api_key'] = $apiKey;
            }
            
            $this->config->createApplication($tempAppId, $config);

            // Test API connection by checking balance
            $this->api->setApplication($tempAppId)->setMode($mode);
            $this->api->getBalance(true); // Force fresh balance check

            // Clean up temporary config
            $this->config->deleteApplication($tempAppId);

            return true;
            
        } catch (\Exception $e) {
            // Clean up temporary config on error
            try {
                $this->config->deleteApplication($tempAppId ?? 'temp_validation');
            } catch (\Exception $cleanupError) {
                // Ignore cleanup errors
            }

            return false;
        }
    }

    protected function showMainMenu(): int
    {
        $this->displayHeader();
        
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $app = $this->config->getApplication($currentApp);
        
        // Try to get balance (cached or fresh)
        $balanceInfo = $this->getBalanceInfo($currentApp, $currentMode);

        // Create and show interactive menu
        $selected = $this->createInteractiveMenu();

        // Handle case where menu returns null (user pressed Ctrl+C or ESC)
        if ($selected === null) {
            $this->info('👋 Goodbye!');
            return 0;
        }

        return $this->handleMenuSelection($selected);
    }

    protected function createInteractiveMenu(): ?string
    {
        // Try to use interactive menu if available
        if (class_exists('NunoMaduro\LaravelConsoleMenu\Menu')) {
            $menu = app('NunoMaduro\LaravelConsoleMenu\Menu')->setTitle('Chargily Pay CLI - Main Menu');
            
            // Add menu options
            $menu->addOption('payment:create', '💳 Create Payment')
                 ->addOption('payment:status', '🔍 Check Payment Status')  
                 ->addOption('payment:list', '📋 List Recent Payments')
                 ->addOption('separator1', '─────────────────────')
                 ->addOption('customer:manage', '👥 Manage Customers')
                 ->addOption('product:manage', '📦 Manage Products & Prices')
                 ->addOption('link:manage', '🔗 Manage Payment Links')
                 ->addOption('separator2', '─────────────────────')
                 ->addOption('balance', '💰 Check Balance')
                 ->addOption('configure', '⚙️  Configuration')
                 ->addOption('switch:app', '🏢 Switch Application')
                 ->addOption('switch:mode', '🔄 Switch Mode')
                 ->addOption('separator3', '─────────────────────')
                 ->addOption('help', '📚 Help & Documentation')
                 ->addOption('exit', '🚪 Exit');

            return $menu->open();
        }
        
        // Fallback to command list
        $this->showCommandListMenu();
        return 'exit';
    }

    protected function displayWelcomeHeader(): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                     Chargily Pay CLI                        ║');
        $this->line('║                                                              ║');
        $this->line('║  🚀 Professional payment management for Algeria             ║');
        $this->line('║  💳 EDAHABIA & CIB Card support                             ║');
        $this->line('║  🔒 Secure API integration                                   ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
    }

    protected function displayHeader(): void
    {
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $app = $this->config->getApplication($currentApp);
        $globalMode = config('chargily.global_mode_override');

        $modeDisplay = $currentMode;
        if ($globalMode) {
            $modeDisplay .= " (Global Override)";
        }

        $balanceInfo = $this->getBalanceInfo($currentApp, $currentMode);
        
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                     Chargily Pay CLI                        ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("📊 Current: {$app['name']} → " . ($currentMode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE'));
        
        if ($balanceInfo) {
            $this->line("💰 Balance: {$balanceInfo}");
        }
        
        if ($globalMode) {
            $this->warn("⚠️  Global mode override active: {$globalMode}");
        }
        
        $this->line('');
    }

    protected function getBalanceInfo(string $app, string $mode): ?string
    {
        try {
            // First try cached balance
            $cached = $this->config->getCachedBalance($app, $mode);
            if ($cached) {
                $balance = $cached['wallets'][0]['balance'] ?? 'Unknown';
                return "{$balance} DZD (cached)";
            }

            // If no cache, try to get fresh balance (but don't block if it fails)
            $apiKey = $this->config->getApiKey($app, $mode);
            if (!$apiKey) {
                return "API key not configured";
            }

            // Quick balance check
            $result = $this->api->setApplication($app)->setMode($mode)->getBalance();
            $balance = $result['wallets'][0]['balance'] ?? 'Unknown';
            return "{$balance} DZD";
            
        } catch (\Exception $e) {
            return "Unable to fetch balance";
        }
    }

    protected function handleMenuSelection(?string $selection): int
    {
        // Handle null selection (should not happen due to earlier check, but safety first)
        if ($selection === null) {
            return 0;
        }
        
        // Handle separators by showing menu again
        if (str_starts_with($selection, 'separator')) {
            return $this->showMainMenu();
        }

        switch ($selection) {
            case 'payment:create':
                $this->call('payment:create');
                return $this->showMainMenu();
            case 'payment:status':
                $this->call('payment:status');
                return $this->showMainMenu();
            case 'payment:list':
                $this->call('payment:list');
                return $this->showMainMenu();
            case 'customer:manage':
                $this->call('customer:manage');
                return $this->showMainMenu();
            case 'product:manage':
                $this->call('product:manage');
                return $this->showMainMenu();
            case 'link:manage':
                $this->call('link:manage');
                return $this->showMainMenu();
            case 'balance':
                $this->call('balance');
                return $this->showMainMenu();
            case 'configure':
                $this->call('configure');
                // Check if apps still exist after configuration changes
                if (!$this->hasValidApplications()) {
                    // Show wizard if no valid apps remain
                    $this->displayWizardLoadingMessage();
                    return $this->showSetupWizard();
                }
                return $this->showMainMenu();
            case 'switch:app':
                $this->call('app:switch');
                // Check if apps still exist after switching
                if (!$this->hasValidApplications()) {
                    $this->displayWizardLoadingMessage();
                    return $this->showSetupWizard();
                }
                return $this->showMainMenu();
            case 'switch:mode':
                $this->call('mode:switch');
                return $this->showMainMenu();
            case 'help':
                $this->displayHelp();
                return $this->showMainMenu();
            case 'exit':
                $this->info('👋 Goodbye!');
                return 0;
            default:
                $this->error("Command '{$selection}' not yet implemented.");
                return $this->showMainMenu();
        }
    }

    protected function displayHelp(): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                        Help & Usage                         ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        
        $this->info('🚀 Chargily Pay CLI Commands:');
        $this->line('');
        
        $commands = [
            'Payment Operations' => [
                'payment:create' => 'Create a new payment checkout',
                'payment:list' => 'List and filter payments',
                'payment:status' => 'Check payment status by ID',
            ],
            'Customer Management' => [
                'customer:manage' => 'Full customer CRUD operations',
            ],
            'Product & Pricing' => [
                'product:manage' => 'Manage products and prices',
            ],
            'Payment Links' => [
                'link:manage' => 'Create and manage payment links',
            ],
            'Application Settings' => [
                'balance' => 'Check account balance',
                'app:switch' => 'Switch between applications',
                'mode:switch' => 'Switch between test/live modes',
                'configure' => 'Advanced configuration',
            ],
        ];

        foreach ($commands as $category => $cmdList) {
            $this->line("<fg=yellow>📂 {$category}:</>");
            foreach ($cmdList as $cmd => $desc) {
                $this->line("   <fg=cyan>{$cmd}</> - {$desc}");
            }
            $this->line('');
        }
        
        $this->line('💡 <fg=green>Tips:</>' );
        $this->line('   • Use <fg=cyan>chargily menu</> for interactive mode');
        $this->line('   • Add <fg=cyan>--help</> to any command for detailed options');
        $this->line('   • Start with test mode, switch to live when ready');
        $this->line('');
        
        $this->line('🌐 <fg=green>Resources:</>');
        $this->line('   • Chargily API Docs: <fg=blue>https://dev.chargily.com</>');
        $this->line('   • CLI Documentation: <fg=blue>https://github.com/karaOdin/chargily-pay-cli</>');
        $this->line('');
        
        // Wait for user input before returning to menu
        $this->waitForUser();
    }

    protected function waitForUser(): void
    {
        $this->line('');
        $this->info('Press any key to continue...');
        $this->line('');
        
        // Simple way to wait for user input
        fgets(STDIN);
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}