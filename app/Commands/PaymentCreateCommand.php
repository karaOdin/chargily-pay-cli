<?php

namespace App\Commands;

use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use App\Exceptions\ChargilyApiException;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;

class PaymentCreateCommand extends Command
{
    protected $signature = 'payment:create 
                           {--amount= : Payment amount in DZD}
                           {--currency=dzd : Payment currency}
                           {--description= : Payment description}
                           {--success-url= : Success redirect URL}
                           {--failure-url= : Failure redirect URL}';

    protected $description = 'Create a new payment checkout';

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
        // Check if we have valid applications configured
        if ($this->checkNoValidApps()) {
            return $this->showWizardWithDelay();
        }
        
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $app = $this->config->getApplication($currentApp);

        $this->displayHeader($app['name'], $currentMode);

        // Safety check for live mode
        if ($currentMode === 'live') {
            $this->warn('⚠️  You are in LIVE MODE - Real money will be processed!');
            $this->line('');
            
            if (!confirm('Continue with live payment creation?', false)) {
                $this->info('Payment creation cancelled.');
                return 0;
            }
        }

        // Get payment details
        $amount = $this->getAmount();
        $currency = $this->option('currency') ?: 'dzd';
        $description = $this->getPaymentDescription();
        $successUrl = $this->getSuccessUrl($currentApp, $currentMode);
        $failureUrl = $this->getFailureUrl($currentApp, $currentMode);
        
        // Check if any operation was cancelled
        if ($amount === null || $successUrl === null || $failureUrl === null) {
            $this->info('Payment creation cancelled.');
            return 0;
        }

        // Display payment preview
        $this->displayPaymentPreview($amount, $currency, $description, $successUrl, $failureUrl, $currentMode);

        try {
            if (!confirm('Create this payment? (Press Ctrl+C to cancel)', true)) {
                $this->info('Payment creation cancelled.');
                return 0;
            }
        } catch (\Exception $e) {
            $this->info('Payment creation cancelled.');
            return 0;
        }

        // Create the payment
        return $this->createPayment([
            'amount' => $amount,
            'currency' => $currency,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'description' => $description,
        ]);
    }

    protected function displayHeader(string $appName, string $mode): void
    {
        $modeDisplay = $mode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE';
        
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                    Create Payment                           ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("Application: {$appName}");
        $this->line("Mode: {$modeDisplay}");
        $this->line('');
    }

    protected function getAmount(): ?int
    {
        if ($this->option('amount')) {
            $amount = (int) $this->option('amount');
            if ($amount < 50) {
                $this->error('Amount must be at least 50 DZD (API requirement)');
                exit(1);
            }
            return $amount;
        }

        try {
            return (int) text(
                'Payment amount (DZD) - Press Ctrl+C to cancel',
                placeholder: '2500',
                required: true,
                validate: fn ($value) => is_numeric($value) && $value >= 50
                    ? null
                    : 'Amount must be at least 50 DZD (API requirement)'
            );
        } catch (\Exception $e) {
            return null; // Signal cancellation
        }
    }

    protected function getPaymentDescription(): ?string
    {
        if ($this->option('description')) {
            return $this->option('description');
        }

        try {
            return text(
                'Payment description (optional) - Press Ctrl+C to cancel',
                placeholder: 'Product purchase, subscription, etc.'
            );
        } catch (\Exception $e) {
            return null; // Signal cancellation
        }
    }

    protected function getSuccessUrl(string $appId, string $mode): ?string
    {
        if ($this->option('success-url')) {
            $url = $this->option('success-url');
            
            // Validate command-line provided URL
            if (!filter_var($url, FILTER_VALIDATE_URL) || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))) {
                $this->error('❌ Invalid success URL: Must be a valid URL starting with http:// or https://');
                exit(1);
            }
            
            return $url;
        }

        $app = $this->config->getApplication($appId);
        $defaultUrl = $app[$mode]['default_success_url'] ?? '';

        try {
            return text(
                'Success redirect URL - Press Ctrl+C to cancel',
                default: $defaultUrl,
                required: true,
                placeholder: 'https://mywebsite.com/payment/success',
                validate: fn ($value) => filter_var($value, FILTER_VALIDATE_URL) && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://'))
                    ? null
                    : 'Must be a valid URL starting with http:// or https://'
            );
        } catch (\Exception $e) {
            return null; // Signal cancellation
        }
    }

    protected function getFailureUrl(string $appId, string $mode): ?string
    {
        if ($this->option('failure-url')) {
            $url = $this->option('failure-url');
            
            // Validate command-line provided URL
            if (!empty($url) && (!filter_var($url, FILTER_VALIDATE_URL) || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')))) {
                $this->error('❌ Invalid failure URL: Must be a valid URL starting with http:// or https://');
                exit(1);
            }
            
            return $url ?: 'https://example.com/payment/failed';
        }

        $app = $this->config->getApplication($appId);
        $defaultUrl = $app[$mode]['default_failure_url'] ?? '';

        // If no default URL is set and we're in non-interactive mode, provide a fallback
        if (empty($defaultUrl) && !$this->input->isInteractive()) {
            return 'https://example.com/payment/failed';
        }

        try {
            return text(
                'Failure redirect URL (optional) - Press Ctrl+C to cancel',
                default: $defaultUrl ?: 'https://example.com/payment/failed',
                placeholder: 'https://mywebsite.com/payment/failed',
                validate: fn ($value) => empty($value) || (filter_var($value, FILTER_VALIDATE_URL) && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')))
                    ? null
                    : 'Must be a valid URL starting with http:// or https://'
            ) ?: 'https://example.com/payment/failed';
        } catch (\Exception $e) {
            return null; // Signal cancellation
        }
    }

    protected function displayPaymentPreview(int $amount, string $currency, ?string $description, string $successUrl, string $failureUrl, string $mode): void
    {
        $this->line('');
        $this->line('╔═══════════════════════════════════════════════════════════════╗');
        $this->line('║                     Payment Preview                          ║');
        $this->line('╚═══════════════════════════════════════════════════════════════╝');
        $this->line('');
        
        if ($mode === 'live') {
            $this->line('🔴 MODE: LIVE - REAL MONEY TRANSACTION');
        } else {
            $this->line('🧪 MODE: TEST - SAFE SIMULATION');
        }
        
        $this->line("💰 Amount: " . number_format($amount) . " " . strtoupper($currency));
        
        if ($description) {
            $this->line("📝 Description: {$description}");
        }
        
        $this->line("✅ Success URL: {$successUrl}");
        $this->line("❌ Failure URL: {$failureUrl}");
        $this->line('');
    }

    protected function createPayment(array $data): int
    {
        try {
            $currentApp = $this->config->getCurrentApplication();
            $currentMode = $this->config->getCurrentMode();
            
            $this->line('⏳ Creating payment...');
            
            $result = $this->api
                ->setApplication($currentApp)
                ->setMode($currentMode)
                ->createCheckout($data);
            
            $this->line('');
            $this->info('✅ Payment created successfully!');
            $this->line('');
            
            $this->displayPaymentResult($result);
            
            return 0;
            
        } catch (ChargilyApiException $e) {
            $this->error('❌ Payment creation failed: ' . $e->getUserMessage());
            $this->line('💡 ' . $e->getSuggestedAction());
            return 1;
        }
    }

    protected function displayPaymentResult(array $payment): void
    {
        $this->line('╔═══════════════════════════════════════════════════════════════╗');
        $this->line('║                     Payment Details                          ║');
        $this->line('╚═══════════════════════════════════════════════════════════════╗');
        $this->line('');
        
        $this->line("🆔 Checkout ID: {$payment['id']}");
        $this->line("💰 Amount: " . number_format($payment['amount']) . " " . strtoupper($payment['currency']));
        $this->line("📊 Status: " . ucfirst($payment['status']));
        $this->line("🔗 Payment URL: {$payment['checkout_url']}");
        
        if (isset($payment['description']) && $payment['description']) {
            $this->line("📝 Description: {$payment['description']}");
        }
        
        $this->line('');
        $this->line('🔗 Share this URL with your customer to complete the payment:');
        $this->line("<fg=yellow>{$payment['checkout_url']}</>");
        $this->line('');
        $this->line('💡 Payment expires automatically after 30 minutes');
        $this->line('💡 Use: chargily payment:status ' . $payment['id'] . ' to check status');
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
            if (!empty($testKey) || !empty($liveKey)) {
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