<?php

namespace App\Commands;

use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use App\Exceptions\ChargilyApiException;
use App\Exceptions\ConfigurationException;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
use function Laravel\Prompts\multiselect;

class ConfigureCommand extends Command
{
    protected $signature = 'configure 
                           {--app= : Configure specific application}
                           {--mode= : Set mode (test|live)}
                           {--reset : Reset all configuration}
                           {--export= : Export application configuration}
                           {--import= : Import application configuration}';

    protected $description = 'Configure Chargily Pay CLI applications and settings';

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
        $this->displayHeader();

        // Handle specific options
        if ($this->option('reset')) {
            return $this->handleReset();
        }

        if ($this->option('export')) {
            return $this->handleExport();
        }

        if ($this->option('import')) {
            return $this->handleImport();
        }

        // Main configuration flow
        return $this->handleMainConfiguration();
    }

    protected function displayHeader(): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                 Chargily Pay CLI Configuration              ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    protected function handleMainConfiguration(): int
    {
        $apps = $this->config->getApplications();

        if (empty($apps)) {
            $this->info('🎉 Welcome to Chargily Pay CLI!');
            $this->line('');
            $this->line('This is your first time using the CLI. Let\'s set up your first application.');
            $this->line('');
            
            return $this->createFirstApplication();
        }

        // Show current configuration
        $this->displayCurrentConfiguration();

        try {
            $action = select(
                'What would you like to do? (Press Ctrl+C to cancel)',
                [
                    'add_app' => '➕ Add New Application',
                    'switch_app' => '🔄 Switch Application', 
                    'switch_mode' => '🔀 Switch Mode (Test/Live)',
                    'separator1' => '─────────────────────',
                    'setup_current' => '⚙️  Setup Current Application',
                    'update_app' => '✏️  Update Application',
                    'remove_app' => '🗑️  Remove Application',
                    'separator2' => '─────────────────────',
                    'test_connection' => '🧪 Test API Connection',
                    'reset_all' => '🔥 Reset Everything',
                    'separator3' => '─────────────────────',
                    'exit' => '🚪 Exit',
                ]
            );
        } catch (\Exception $e) {
            $this->info('Configuration cancelled.');
            return 0;
        }

        switch ($action) {
            case 'add_app':
                return $this->addNewApplication();
            case 'switch_app':
                return $this->switchApplication();
            case 'switch_mode':
                return $this->switchMode();
            case 'setup_current':
                return $this->setupCurrentApplication();
            case 'update_app':
                return $this->updateApplication();
            case 'remove_app':
                return $this->removeApplication();
            case 'test_connection':
                return $this->testConnection();
            case 'reset_all':
                return $this->resetEverything();
            case 'exit':
                return 0;
            default:
                // Handle separators by continuing
                return $this->handleMainConfiguration();
        }

        return 0;
    }

    protected function displayCurrentConfiguration(): void
    {
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $app = $this->config->getApplication($currentApp);
        $globalMode = config('chargily.global_mode_override');

        $this->line('📊 Current Configuration:');
        $this->line("   Application: {$app['name']} ({$currentApp})");
        
        if ($globalMode) {
            $this->line("   Mode: {$currentMode} (Global Override: {$globalMode})");
            $this->warn("   ⚠️  Global mode override is active!");
        } else {
            $this->line("   Mode: {$currentMode}");
        }

        $apiKey = $this->config->getApiKey($currentApp, $currentMode);
        $this->line("   API Key: " . ($apiKey ? '✅ Configured' : '❌ Not configured'));
        
        $cachedBalance = $this->config->getCachedBalance($currentApp, $currentMode);
        if ($cachedBalance) {
            $balance = $cachedBalance['wallets'][0]['balance'] ?? 'Unknown';
            $this->line("   Balance: {$balance} DZD (cached)");
        }
        
        $this->line('');
    }

    protected function createFirstApplication(): int
    {
        $name = text(
            'Application name',
            placeholder: 'My Business',
            required: true
        );

        $id = text(
            'Application identifier (used in commands)',
            default: str_replace([' ', '-'], '_', strtolower($name)),
            required: true,
            validate: fn ($value) => preg_match('/^[a-z0-9_]+$/', $value) 
                ? null 
                : 'Identifier must contain only lowercase letters, numbers, and underscores'
        );

        if ($this->config->applicationExists($id)) {
            $this->error("Application '{$id}' already exists!");
            return 1;
        }

        $mode = select(
            'Which mode would you like to configure first?',
            [
                'test' => '🧪 Test Mode (Recommended for setup)',
                'live' => '🔴 Live Mode (Real payments)',
            ],
            default: 'test'
        );

        $apiKey = password(
            "Enter your Chargily Pay {$mode} API key",
            required: true,
            validate: fn ($value) => str_starts_with($value, $mode === 'test' ? 'test_sk_' : 'live_sk_')
                ? null
                : "API key should start with '{$mode}_sk_'"
        );

        $webhookUrl = text(
            'Webhook URL (optional)',
            placeholder: "https://mysite.com/webhooks/chargily"
        );

        $successUrl = text(
            'Default success URL',
            placeholder: "https://mysite.com/payment/success",
            required: true
        );

        $failureUrl = text(
            'Default failure URL (optional)',
            placeholder: "https://mysite.com/payment/failed"
        );

        // Create the application
        try {
            $config = [
                'name' => $name,
                "{$mode}_api_key" => $apiKey,
                "{$mode}_webhook_url" => $webhookUrl,
                "{$mode}_success_url" => $successUrl,
                "{$mode}_failure_url" => $failureUrl,
            ];

            $this->config->createApplication($id, $config);
            $this->config->setCurrentApplication($id);
            $this->config->setCurrentMode($id, $mode);

            $this->line('');
            $this->info('✅ Application created successfully!');

            // Test the API connection
            $this->line('');
            $this->line('🧪 Testing API connection...');
            
            $result = $this->api->setApplication($id)->setMode($mode)->testConnection();
            
            if ($result['success']) {
                $this->info('✅ API connection successful!');
                $balance = $result['data']['wallets'][0]['balance'] ?? 0;
                $this->line("💰 Balance: {$balance} DZD");
            } else {
                $this->error('❌ API connection failed: ' . $result['message']);
                $this->line('💡 ' . $result['error_code'] ?? 'Check your API key and try again');
            }

            $this->line('');
            $this->info('🎉 Setup complete! You can now use:');
            $this->line('   • chargily payment:create  - Create payments');
            $this->line('   • chargily balance         - Check balance');
            $this->line('   • chargily                 - Interactive menu');

            return 0;

        } catch (ConfigurationException $e) {
            $this->error('Configuration error: ' . $e->getMessage());
            $this->line('💡 ' . $e->getSuggestedAction());
            return 1;
        }
    }

    protected function setupCurrentApplication(): int
    {
        $currentApp = $this->config->getCurrentApplication();
        $app = $this->config->getApplication($currentApp);

        $this->info("Setting up: {$app['name']} ({$currentApp})");
        $this->line('');

        $mode = select(
            'Which mode would you like to configure?',
            [
                'test' => '🧪 Test Mode',
                'live' => '🔴 Live Mode',
                'both' => '🔄 Both Modes',
            ]
        );

        $modes = $mode === 'both' ? ['test', 'live'] : [$mode];

        foreach ($modes as $configMode) {
            $this->line('');
            $this->info("Configuring {$configMode} mode...");

            $currentKey = $this->config->getApiKey($currentApp, $configMode);
            $hasKey = !empty($currentKey);

            if ($hasKey) {
                $updateKey = confirm("Update existing {$configMode} API key?", false);
            } else {
                $updateKey = true;
                $this->line("No {$configMode} API key configured.");
            }

            if ($updateKey) {
                $apiKey = password(
                    "Enter {$configMode} API key",
                    required: true,
                    validate: fn ($value) => str_starts_with($value, $configMode === 'test' ? 'test_sk_' : 'live_sk_')
                        ? null
                        : "API key should start with '{$configMode}_sk_'"
                );

                $this->config->setApiKey($currentApp, $configMode, $apiKey);
                $this->info("✅ {$configMode} API key updated");
            }

            // Test connection if key was updated
            if ($updateKey) {
                $this->line("🧪 Testing {$configMode} API connection...");
                
                $result = $this->api->setApplication($currentApp)->setMode($configMode)->testConnection();
                
                if ($result['success']) {
                    $this->info("✅ {$configMode} API connection successful!");
                    $balance = $result['data']['wallets'][0]['balance'] ?? 0;
                    $this->line("💰 {$configMode} balance: {$balance} DZD");
                } else {
                    $this->error("❌ {$configMode} API connection failed: " . $result['message']);
                }
            }
        }

        // Configure URLs and settings
        if (confirm('Configure URLs and settings?', true)) {
            $this->configureApplicationSettings($currentApp);
        }

        $this->line('');
        $this->info('✅ Application configuration updated!');
        
        return 0;
    }

    protected function configureApplicationSettings(string $appId): void
    {
        $app = $this->config->getApplication($appId);

        // Configure webhook URLs
        if (confirm('Update webhook URLs?', false)) {
            foreach (['test', 'live'] as $mode) {
                $current = $app[$mode]['webhook_url'] ?? '';
                $webhook = text(
                    "Webhook URL for {$mode} mode",
                    default: $current,
                    placeholder: "https://mysite.com/webhooks/chargily"
                );

                if ($webhook !== $current) {
                    $this->config->updateApplication($appId, [
                        $mode => ['webhook_url' => $webhook]
                    ]);
                }
            }
        }

        // Configure success/failure URLs
        if (confirm('Update success/failure URLs?', false)) {
            foreach (['test', 'live'] as $mode) {
                $currentSuccess = $app[$mode]['default_success_url'] ?? '';
                $currentFailure = $app[$mode]['default_failure_url'] ?? '';

                $success = text(
                    "Success URL for {$mode} mode",
                    default: $currentSuccess,
                    required: true,
                    placeholder: "https://mysite.com/payment/success"
                );

                $failure = text(
                    "Failure URL for {$mode} mode",
                    default: $currentFailure,
                    placeholder: "https://mysite.com/payment/failed"
                );

                $this->config->updateApplication($appId, [
                    $mode => [
                        'default_success_url' => $success,
                        'default_failure_url' => $failure,
                    ]
                ]);
            }
        }

        // Configure safety limits for live mode
        if (confirm('Configure safety limits for live mode?', false)) {
            $currentLimits = $app['settings']['safety_limits'] ?? [];

            $maxSingle = (int) text(
                'Maximum single payment (DZD)',
                default: (string) ($currentLimits['max_single_payment'] ?? 100000),
                validate: fn ($value) => is_numeric($value) && $value > 0 ? null : 'Must be a positive number'
            );

            $maxDaily = (int) text(
                'Maximum daily payments count',
                default: (string) ($currentLimits['max_daily_payments'] ?? 50),
                validate: fn ($value) => is_numeric($value) && $value > 0 ? null : 'Must be a positive number'
            );

            $maxVolume = (int) text(
                'Maximum daily volume (DZD)',
                default: (string) ($currentLimits['max_daily_volume'] ?? 500000),
                validate: fn ($value) => is_numeric($value) && $value > 0 ? null : 'Must be a positive number'
            );

            $this->config->updateApplication($appId, [
                'settings' => [
                    'safety_limits' => [
                        'max_single_payment' => $maxSingle,
                        'max_daily_payments' => $maxDaily,
                        'max_daily_volume' => $maxVolume,
                    ]
                ]
            ]);

            $this->info('✅ Safety limits updated');
        }
    }

    protected function switchApplication(): int
    {
        $apps = $this->config->getApplications();
        $current = $this->config->getCurrentApplication();

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
        $result = $this->api->setApplication($selected)->testConnection();
        if ($result['success']) {
            $balance = $result['data']['wallets'][0]['balance'] ?? 0;
            $this->line("💰 Balance: {$balance} DZD");
        }

        return 0;
    }

    protected function createNewApplication(): int
    {
        $this->info('Creating new application...');
        $this->line('');

        // Choose creation method
        $method = select(
            'How would you like to create the application?',
            [
                'scratch' => '🆕 From scratch',
                'template' => '📋 From template',
                'clone' => '📄 Clone existing application',
            ]
        );

        switch ($method) {
            case 'template':
                return $this->createFromTemplate();
            case 'clone':
                return $this->cloneApplication();
            default:
                return $this->createFromScratch();
        }
    }

    protected function createFromTemplate(): int
    {
        $templates = $this->config->getTemplates();

        if (empty($templates)) {
            $this->error('No templates available.');
            return 1;
        }

        $options = [];
        foreach ($templates as $id => $template) {
            $options[$id] = "{$template['name']} - {$template['description']}";
        }

        $templateId = select('Select template:', $options);

        $name = text('Application name', required: true);
        $id = text(
            'Application identifier',
            default: str_replace([' ', '-'], '_', strtolower($name)),
            required: true,
            validate: fn ($value) => preg_match('/^[a-z0-9_]+$/', $value) 
                ? null 
                : 'Identifier must contain only lowercase letters, numbers, and underscores'
        );

        if ($this->config->applicationExists($id)) {
            $this->error("Application '{$id}' already exists!");
            return 1;
        }

        try {
            $this->config->createApplicationFromTemplate($templateId, $id, $name);
            $this->info("✅ Application '{$name}' created from template!");
            $this->line('💡 Don\'t forget to configure your API keys with: chargily configure --app=' . $id);
            return 0;
        } catch (ConfigurationException $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    protected function cloneApplication(): int
    {
        $apps = $this->config->getApplications();
        $options = [];
        foreach ($apps as $id => $app) {
            $options[$id] = $app['name'];
        }

        $sourceId = select('Select application to clone:', $options);
        
        $name = text('New application name', required: true);
        $id = text(
            'New application identifier',
            default: str_replace([' ', '-'], '_', strtolower($name)),
            required: true,
            validate: fn ($value) => preg_match('/^[a-z0-9_]+$/', $value) 
                ? null 
                : 'Identifier must contain only lowercase letters, numbers, and underscores'
        );

        if ($this->config->applicationExists($id)) {
            $this->error("Application '{$id}' already exists!");
            return 1;
        }

        try {
            $this->config->cloneApplication($sourceId, $id, $name);
            $this->info("✅ Application '{$name}' cloned successfully!");
            $this->line('💡 API keys were cleared for security. Configure them with: chargily configure --app=' . $id);
            return 0;
        } catch (ConfigurationException $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    protected function createFromScratch(): int
    {
        // This would be similar to createFirstApplication but for additional apps
        return $this->createFirstApplication();
    }

    protected function testConnection(): int
    {
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();

        $this->info("Testing connection for {$currentApp} in {$currentMode} mode...");
        $this->line('');

        try {
            $result = $this->api->setApplication($currentApp)->setMode($currentMode)->testConnection();
            
            if ($result['success']) {
                $this->info('✅ Connection successful!');
                
                $data = $result['data'];
                $this->line('');
                $this->line('📊 Account Information:');
                $this->line("   Environment: " . ($data['livemode'] ? 'Live' : 'Test'));
                
                foreach ($data['wallets'] as $wallet) {
                    $this->line("   {$wallet['currency']} Balance: {$wallet['balance']}");
                    $this->line("   {$wallet['currency']} Ready for payout: {$wallet['ready_for_payout']}");
                    $this->line("   {$wallet['currency']} On hold: {$wallet['on_hold']}");
                }
            } else {
                $this->error('❌ Connection failed: ' . $result['message']);
                $this->line('💡 Suggested action: Run "chargily configure" to check your settings');
            }
            
            return 0;
            
        } catch (ChargilyApiException $e) {
            $this->error('❌ API Error: ' . $e->getUserMessage());
            $this->line('💡 ' . $e->getSuggestedAction());
            return 1;
        }
    }

    protected function handleReset(): int
    {
        return $this->resetEverything();
    }

    protected function handleExport(): int
    {
        $appId = $this->option('export');
        
        if (!$this->config->applicationExists($appId)) {
            $this->error("Application '{$appId}' does not exist.");
            return 1;
        }

        $config = $this->config->exportApplication($appId);
        $json = json_encode($config, JSON_PRETTY_PRINT);
        
        $this->line($json);
        return 0;
    }

    protected function handleImport(): int
    {
        $file = $this->option('import');
        
        if (!file_exists($file)) {
            $this->error("File '{$file}' does not exist.");
            return 1;
        }

        // Import logic would go here
        $this->error('Import functionality not yet implemented.');
        return 1;
    }

    protected function configureGlobalSettings(): int
    {
        $this->info('Global Settings Configuration');
        $this->line('');

        // This would configure global settings
        $this->error('Global settings configuration not yet implemented.');
        return 1;
    }

    protected function addNewApplication(): int
    {
        $this->info('➕ Add New Application');
        $this->line('');

        try {
            // Get application details
            $name = text(
                'Application name - Press Ctrl+C to cancel',
                placeholder: 'My Store',
                required: true
            );

            $id = text(
                'Application identifier (used in commands) - Press Ctrl+C to cancel',
                default: str_replace([' ', '-'], '_', strtolower($name)),
                required: true,
                validate: fn ($value) => preg_match('/^[a-z0-9_]+$/', $value) 
                    ? null 
                    : 'Identifier must contain only lowercase letters, numbers, and underscores'
            );

            if ($this->config->applicationExists($id)) {
                $this->error("Application '{$id}' already exists!");
                return 1;
            }

            // Get API keys with validation
            $testKey = $this->getValidatedApiKey('test');
            if (!$testKey) return 1;

            $liveKey = $this->getValidatedApiKey('live');
            if (!$liveKey) return 1;

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
                    'api_key' => $liveKey,
                    'webhook_secret' => '',
                    'default_success_url' => '',
                    'default_failure_url' => ''
                ]
            ];

            $this->config->createApplication($id, $config);
            
            $this->line('');
            $this->info("✅ Application '{$name}' added successfully!");
            
            if (confirm('Set as current application?', true)) {
                $this->config->setCurrentApplication($id);
                $this->config->setCurrentMode($id, 'test'); // Default to test mode
                $this->info("✅ Switched to '{$name}' in test mode");
            }

            return 0;

        } catch (\Exception $e) {
            $this->info('Application creation cancelled.');
            return 0;
        }
    }

    protected function getValidatedApiKey(string $mode): ?string
    {
        $modeDisplay = $mode === 'test' ? '🧪 TEST' : '🔴 LIVE';
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $apiKey = text(
                    "{$modeDisplay} API Key - Press Ctrl+C to cancel",
                    placeholder: $mode === 'test' ? 'test_sk_...' : 'live_sk_...',
                    required: true,
                    validate: fn ($value) => str_starts_with($value, $mode === 'test' ? 'test_' : 'live_')
                        ? null
                        : "API key must start with '{$mode}_'"
                );

                // Validate key silently by checking balance
                $this->line('🔍 Validating API key...');
                
                if ($this->validateApiKeySilently($apiKey, $mode)) {
                    $this->info("✅ {$modeDisplay} API key is valid");
                    return $apiKey;
                } else {
                    $attempt++;
                    if ($attempt < $maxAttempts) {
                        $this->error("❌ Invalid API key. Please try again. ({$attempt}/{$maxAttempts})");
                    }
                }
            } catch (\Exception $e) {
                return null; // User cancelled
            }
        }

        $this->error('❌ Maximum attempts exceeded.');
        return null;
    }

    protected function validateApiKeySilently(string $apiKey, string $mode): bool
    {
        try {
            // Create temporary application config for testing
            $tempAppId = 'temp_validation_' . uniqid();
            $this->config->createApplication($tempAppId, [
                'name' => 'Temp Validation',
                $mode => ['api_key' => $apiKey]
            ]);

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

    protected function switchMode(): int
    {
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $app = $this->config->getApplication($currentApp);

        $this->info("🔀 Switch Mode for {$app['name']}");
        $this->line("Current mode: " . ($currentMode === 'live' ? '🔴 LIVE' : '🧪 TEST'));
        $this->line('');

        try {
            $newMode = select(
                'Select mode - Press Ctrl+C to cancel',
                [
                    'test' => '🧪 Test Mode (Safe for testing)',
                    'live' => '🔴 Live Mode (Real payments)',
                ],
                $currentMode === 'live' ? 'live' : 'test'
            );

            if ($newMode === $currentMode) {
                $this->info('Already in ' . ($newMode === 'live' ? 'live' : 'test') . ' mode.');
                return 0;
            }

            $this->config->setCurrentMode($currentApp, $newMode);
            $modeDisplay = $newMode === 'live' ? '🔴 LIVE' : '🧪 TEST';
            $this->info("✅ Switched to {$modeDisplay} mode");

            return 0;

        } catch (\Exception $e) {
            $this->info('Mode switch cancelled.');
            return 0;
        }
    }

    protected function updateApplication(): int
    {
        $apps = $this->config->getApplications();
        if (empty($apps)) {
            $this->error('No applications to update.');
            return 1;
        }

        try {
            // Select application to update
            $options = [];
            foreach ($apps as $id => $app) {
                $options[$id] = $app['name'];
            }

            $appId = select(
                'Select application to update - Press Ctrl+C to cancel',
                $options
            );

            $app = $this->config->getApplication($appId);
            $this->info("✏️  Update {$app['name']}");
            $this->line('');

            $action = select(
                'What would you like to update?',
                [
                    'name' => '📝 Application Name',
                    'test_key' => '🧪 Test API Key',
                    'live_key' => '🔴 Live API Key',
                    'both_keys' => '🔑 Both API Keys',
                    'cancel' => '🚫 Cancel'
                ]
            );

            switch ($action) {
                case 'name':
                    return $this->updateApplicationName($appId, $app);
                case 'test_key':
                    return $this->updateApplicationKey($appId, 'test');
                case 'live_key':
                    return $this->updateApplicationKey($appId, 'live');
                case 'both_keys':
                    $this->updateApplicationKey($appId, 'test');
                    return $this->updateApplicationKey($appId, 'live');
                case 'cancel':
                    $this->info('Update cancelled.');
                    return 0;
            }

            return 0;

        } catch (\Exception $e) {
            $this->info('Update cancelled.');
            return 0;
        }
    }

    protected function updateApplicationName(string $appId, array $app): int
    {
        try {
            $newName = text(
                'New application name - Press Ctrl+C to cancel',
                default: $app['name'],
                required: true
            );

            $this->config->updateApplication($appId, ['name' => $newName]);
            $this->info("✅ Application name updated to '{$newName}'");

            return 0;
        } catch (\Exception $e) {
            $this->info('Name update cancelled.');
            return 0;
        }
    }

    protected function updateApplicationKey(string $appId, string $mode): int
    {
        $modeDisplay = $mode === 'test' ? '🧪 TEST' : '🔴 LIVE';
        $this->info("Updating {$modeDisplay} API Key");
        
        $newKey = $this->getValidatedApiKey($mode);
        if (!$newKey) return 0;

        $this->config->updateApplication($appId, [
            $mode => ['api_key' => $newKey]
        ]);

        $this->info("✅ {$modeDisplay} API key updated successfully");
        return 0;
    }

    protected function removeApplication(): int
    {
        $apps = $this->config->getApplications();
        if (empty($apps)) {
            $this->error('No applications to remove.');
            return 1;
        }

        // Allow removing all apps - will trigger setup wizard

        try {
            // Select application to remove
            $options = [];
            foreach ($apps as $id => $app) {
                $current = $id === $this->config->getCurrentApplication() ? ' (current)' : '';
                $options[$id] = $app['name'] . $current;
            }

            $appId = select(
                'Select application to remove - Press Ctrl+C to cancel',
                $options
            );

            $app = $this->config->getApplication($appId);
            
            $this->warn("⚠️  You are about to remove '{$app['name']}'");
            $this->line('This action cannot be undone.');
            $this->line('');

            if (!confirm('Are you sure you want to remove this application?', false)) {
                $this->info('Removal cancelled.');
                return 0;
            }

            // If removing current application, switch to another one first (if any exist)
            $currentApp = $this->config->getCurrentApplication();
            if ($appId === $currentApp) {
                $remainingApps = array_diff_key($apps, [$appId => null]);
                if (!empty($remainingApps)) {
                    $firstRemaining = array_key_first($remainingApps);
                    $this->config->setCurrentApplication($firstRemaining);
                    $this->info("Switched to '{$remainingApps[$firstRemaining]['name']}' as current application.");
                }
            }

            $this->config->deleteApplication($appId);
            $this->info("✅ Application '{$app['name']}' removed successfully");

            // Check if this was the last application
            $remainingApps = $this->config->getApplications();
            if (empty($remainingApps)) {
                $this->line('');
                $this->warn('⚠️  No applications remaining!');
                $this->line('You need at least one application to use the CLI.');
                $this->line('');
                
                if (confirm('Would you like to set up a new application now?', true)) {
                    return $this->runSetupWizard();
                } else {
                    $this->line('');
                    $this->line('💡 Run the CLI again when you\'re ready to setup an application.');
                    return 0;
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->info('Removal cancelled.');
            return 0;
        }
    }

    protected function resetEverything(): int
    {
        $this->warn('🔥 RESET EVERYTHING');
        $this->line('');
        $this->error('⚠️  This will delete ALL applications and configuration!');
        $this->error('⚠️  This action cannot be undone!');
        $this->line('');

        try {
            if (!confirm('Are you absolutely sure? Type YES to confirm', false)) {
                $this->info('Reset cancelled.');
                return 0;
            }

            $confirmation = text(
                'Type "DELETE EVERYTHING" to confirm - Press Ctrl+C to cancel',
                required: true
            );

            if ($confirmation !== 'DELETE EVERYTHING') {
                $this->info('Reset cancelled - confirmation text did not match.');
                return 0;
            }

            // Perform reset
            $this->config->resetAllConfiguration();
            
            $this->line('');
            $this->info('🔥 All configuration has been reset!');
            $this->line('💡 Run the setup command to configure your first application again.');

            return 0;

        } catch (\Exception $e) {
            $this->info('Reset cancelled.');
            return 0;
        }
    }

    protected function runSetupWizard(): int
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                    Application Setup                        ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');

        // Show guidance
        $this->displayApiKeyGuidance();
        
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

            // Check if identifier already exists
            if ($this->config->applicationExists($id)) {
                $this->error("Application '{$id}' already exists!");
                return 1;
            }

            $this->line('');
            $this->info('🔑 Now let\'s add your API keys...');
            $this->line('');

            // Get test API key
            $testKey = $this->getValidatedApiKey('test');
            if (!$testKey) return 0;

            // Ask about live key
            $this->line('');
            $addLiveKey = confirm('Would you like to add your LIVE API key now? (You can add it later)', false);
            
            $liveKey = null;
            if ($addLiveKey) {
                $liveKey = $this->getValidatedApiKey('live');
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

            return 0;

        } catch (\Exception $e) {
            $this->line('');
            $this->info('👋 Setup cancelled.');
            return 0;
        }
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

    protected function manageApplications(): int
    {
        // This method is now replaced by individual methods above
        return $this->handleMainConfiguration();
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}