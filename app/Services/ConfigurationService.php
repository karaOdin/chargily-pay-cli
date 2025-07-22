<?php

namespace App\Services;

use App\Exceptions\ConfigurationException;
use Illuminate\Support\Facades\File;

class ConfigurationService
{
    protected array $applications;

    protected string $currentApplication;

    protected string $configPath;

    public function __construct()
    {
        $this->configPath = storage_path('app/chargily_applications.json');
        $this->loadApplications();
        $this->setDefaultApplication();
    }

    protected function setDefaultApplication(): void
    {
        $defaultApp = config('chargily.default_application');

        // If the configured default doesn't exist, use first available app or empty string
        if (!$this->applicationExists($defaultApp)) {
            $availableApps = array_keys($this->applications);
            $this->currentApplication = !empty($availableApps) ? $availableApps[0] : '';
        } else {
            $this->currentApplication = $defaultApp;
        }
    }

    /**
     * Load applications from configuration file
     */
    protected function loadApplications(): void
    {
        if (File::exists($this->configPath)) {
            $content = File::get($this->configPath);
            $this->applications = json_decode($content, true) ?? [];
        } else {
            // Initialize with default configuration
            $this->applications = config('applications.applications', []);
            $this->saveApplications();
        }
    }

    /**
     * Save applications to configuration file
     */
    protected function saveApplications(): void
    {
        File::put($this->configPath, json_encode($this->applications, JSON_PRETTY_PRINT));
    }

    /**
     * Get all applications
     */
    public function getApplications(): array
    {
        return $this->applications;
    }

    /**
     * Get application names
     */
    public function getApplicationNames(): array
    {
        return array_keys($this->applications);
    }

    /**
     * Check if application exists
     */
    public function applicationExists(string $application): bool
    {
        return isset($this->applications[$application]);
    }

    /**
     * Get application configuration
     */
    public function getApplication(string $application): array
    {
        if (!$this->applicationExists($application)) {
            throw new ConfigurationException("Application '{$application}' does not exist");
        }

        return $this->applications[$application];
    }

    /**
     * Create a new application
     */
    public function createApplication(string $id, array $config): void
    {
        if ($this->applicationExists($id)) {
            throw new ConfigurationException("Application '{$id}' already exists");
        }

        $this->applications[$id] = array_merge([
            'name' => $config['name'],
            'test' => [
                'api_key' => $config['test_api_key'] ?? null,
                'webhook_url' => $config['test_webhook_url'] ?? null,
                'default_success_url' => $config['test_success_url'] ?? null,
                'default_failure_url' => $config['test_failure_url'] ?? null,
                'balance_cache' => null,
                'last_balance_check' => null,
            ],
            'live' => [
                'api_key' => $config['live_api_key'] ?? null,
                'webhook_url' => $config['live_webhook_url'] ?? null,
                'default_success_url' => $config['live_success_url'] ?? null,
                'default_failure_url' => $config['live_failure_url'] ?? null,
                'balance_cache' => null,
                'last_balance_check' => null,
            ],
            'current_mode' => 'test',
            'settings' => $config['settings'] ?? $this->getDefaultSettings(),
            'created_at' => now()->toISOString(),
            'last_used' => now()->toISOString(),
            'metadata' => $config['metadata'] ?? [],
        ]);

        $this->saveApplications();
    }

    /**
     * Update an existing application
     */
    public function updateApplication(string $application, array $updates): void
    {
        if (!$this->applicationExists($application)) {
            throw new ConfigurationException("Application '{$application}' does not exist");
        }

        $this->applications[$application] = array_merge_recursive(
            $this->applications[$application],
            $updates
        );

        $this->saveApplications();
    }

    /**
     * Delete an application
     */
    public function deleteApplication(string $application): void
    {
        if (!$this->applicationExists($application)) {
            throw new ConfigurationException("Application '{$application}' does not exist");
        }

        // Clear all cached data for this application
        $this->clearApplicationCache($application);

        // Remove from applications array
        unset($this->applications[$application]);

        // If this was the current application, clear current app reference
        if ($application === $this->currentApplication) {
            $this->currentApplication = '';
        }

        $this->saveApplications();
    }

    protected function clearApplicationCache(string $application): void
    {
        if (!$this->applicationExists($application)) {
            return;
        }

        // Clear balance cache for both test and live modes
        $this->applications[$application]['test']['balance_cache'] = null;
        $this->applications[$application]['test']['last_balance_check'] = null;
        $this->applications[$application]['live']['balance_cache'] = null;
        $this->applications[$application]['live']['last_balance_check'] = null;

        // Clear any payment cache files related to this app
        $cacheFiles = glob(storage_path('app/payments_export_' . $application . '_*.csv'));
        foreach ($cacheFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }

    /**
     * Clone an application
     */
    public function cloneApplication(string $sourceApp, string $newAppId, string $newAppName): void
    {
        if (!$this->applicationExists($sourceApp)) {
            throw new ConfigurationException("Source application '{$sourceApp}' does not exist");
        }

        if ($this->applicationExists($newAppId)) {
            throw new ConfigurationException("Application '{$newAppId}' already exists");
        }

        $sourceConfig = $this->getApplication($sourceApp);
        $clonedConfig = $sourceConfig;
        $clonedConfig['name'] = $newAppName;
        $clonedConfig['created_at'] = now()->toISOString();
        $clonedConfig['last_used'] = null;

        // Clear API keys and sensitive data
        $clonedConfig['test']['api_key'] = null;
        $clonedConfig['live']['api_key'] = null;
        $clonedConfig['test']['balance_cache'] = null;
        $clonedConfig['live']['balance_cache'] = null;

        $this->applications[$newAppId] = $clonedConfig;
        $this->saveApplications();
    }

    /**
     * Get current application
     */
    public function getCurrentApplication(): string
    {
        return $this->currentApplication;
    }

    /**
     * Set current application
     */
    public function setCurrentApplication(string $application): void
    {
        if (!$this->applicationExists($application)) {
            throw new ConfigurationException("Application '{$application}' does not exist");
        }

        $this->currentApplication = $application;

        // Update last used timestamp
        $this->applications[$application]['last_used'] = now()->toISOString();
        $this->saveApplications();
    }

    /**
     * Get current mode for an application
     */
    public function getCurrentMode(?string $application = null): string
    {
        $app = $application ?? $this->currentApplication;
        $globalOverride = config('chargily.global_mode_override');

        if ($globalOverride) {
            return $globalOverride;
        }

        // Handle case when no application is set
        if (empty($app) || !$this->applicationExists($app)) {
            return 'test'; // Default to test mode
        }

        return $this->getApplication($app)['current_mode'];
    }

    /**
     * Set current mode for an application
     */
    public function setCurrentMode(string $application, string $mode): void
    {
        if (!in_array($mode, ['test', 'live'])) {
            throw new ConfigurationException("Invalid mode '{$mode}'. Must be 'test' or 'live'");
        }

        if (!$this->applicationExists($application)) {
            throw new ConfigurationException("Application '{$application}' does not exist");
        }

        $this->applications[$application]['current_mode'] = $mode;
        $this->saveApplications();
    }

    /**
     * Get API key for application and mode
     */
    public function getApiKey(string $application, string $mode): ?string
    {
        $app = $this->getApplication($application);
        return $app[$mode]['api_key'] ?? null;
    }

    /**
     * Set API key for application and mode
     */
    public function setApiKey(string $application, string $mode, string $apiKey): void
    {
        if (!$this->applicationExists($application)) {
            throw new ConfigurationException("Application '{$application}' does not exist");
        }

        $this->applications[$application][$mode]['api_key'] = $apiKey;
        $this->saveApplications();
    }

    /**
     * Get base URL for mode
     */
    public function getBaseUrl(string $mode): string
    {
        return config("chargily.api.base_urls.{$mode}");
    }

    /**
     * Update balance cache
     */
    public function updateBalanceCache(string $application, string $mode, array $balance): void
    {
        $this->applications[$application][$mode]['balance_cache'] = $balance;
        $this->applications[$application][$mode]['last_balance_check'] = now()->toISOString();
        $this->saveApplications();
    }

    /**
     * Get cached balance
     */
    public function getCachedBalance(string $application, string $mode): ?array
    {
        return $this->applications[$application][$mode]['balance_cache'] ?? null;
    }

    /**
     * Get default settings
     */
    protected function getDefaultSettings(): array
    {
        return [
            'default_currency' => 'dzd',
            'auto_expire_minutes' => 30,
            'default_payment_method' => 'edahabia',
            'require_confirmation' => true,
            'enable_notifications' => true,
            'safety_limits' => [
                'max_single_payment' => 100000,
                'max_daily_payments' => 50,
                'max_daily_volume' => 500000,
            ],
        ];
    }

    /**
     * Get application templates
     */
    public function getTemplates(): array
    {
        return config('applications.templates', []);
    }

    /**
     * Create application from template
     */
    public function createApplicationFromTemplate(string $templateId, string $appId, string $appName): void
    {
        $templates = $this->getTemplates();

        if (!isset($templates[$templateId])) {
            throw new ConfigurationException("Template '{$templateId}' does not exist");
        }

        $template = $templates[$templateId];
        $config = [
            'name' => $appName,
            'settings' => $template['settings'],
            'metadata' => [
                'description' => $template['description'] ?? '',
                'template' => $templateId,
                'tags' => ['template', $templateId],
            ],
        ];

        $this->createApplication($appId, $config);
    }

    /**
     * Export application configuration
     */
    public function exportApplication(string $application): array
    {
        $config = $this->getApplication($application);

        // Remove sensitive data
        unset($config['test']['api_key']);
        unset($config['live']['api_key']);
        unset($config['test']['balance_cache']);
        unset($config['live']['balance_cache']);

        return $config;
    }

    /**
     * Import application configuration
     */
    public function importApplication(string $appId, array $config): void
    {
        $this->createApplication($appId, $config);
    }

    /**
     * Get application statistics
     */
    public function getApplicationStats(string $application): array
    {
        $app = $this->getApplication($application);

        return [
            'name' => $app['name'],
            'current_mode' => $app['current_mode'],
            'created_at' => $app['created_at'],
            'last_used' => $app['last_used'],
            'has_test_key' => !empty($app['test']['api_key']),
            'has_live_key' => !empty($app['live']['api_key']),
            'test_balance_cached' => !empty($app['test']['balance_cache']),
            'live_balance_cached' => !empty($app['live']['balance_cache']),
            'last_balance_check' => [
                'test' => $app['test']['last_balance_check'],
                'live' => $app['live']['last_balance_check'],
            ],
        ];
    }

    /**
     * Validate application configuration
     */
    public function validateApplication(string $application, string $mode): array
    {
        $errors = [];
        $app = $this->getApplication($application);

        if (empty($app[$mode]['api_key'])) {
            $errors[] = "Missing API key for {$mode} mode";
        }

        if (empty($app[$mode]['webhook_url'])) {
            $errors[] = "Missing webhook URL for {$mode} mode";
        }

        if (empty($app[$mode]['default_success_url'])) {
            $errors[] = "Missing default success URL for {$mode} mode";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Reset all configuration - DELETE EVERYTHING
     */
    public function resetAllConfiguration(): void
    {
        // Delete the configuration file
        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }

        // Reset in-memory data
        $this->applications = [];
        $this->currentApplication = '';

        // Clear any cached data
        $cacheFiles = [
            storage_path('app/balance_cache.json'),
            storage_path('app/payment_cache.json'),
        ];

        foreach ($cacheFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }
}
