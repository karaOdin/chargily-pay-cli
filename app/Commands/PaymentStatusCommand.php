<?php

namespace App\Commands;

use App\Exceptions\ChargilyApiException;
use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\text;

class PaymentStatusCommand extends Command
{
    protected $signature = 'payment:status {checkout_id? : The checkout ID to check}';

    protected $description = 'Check the status of a payment';

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
        $checkoutId = $this->argument('checkout_id') ?: $this->getCheckoutId();

        if (! $checkoutId) {
            $this->error('Checkout ID is required');

            return 1;
        }

        return $this->checkPaymentStatus($checkoutId);
    }

    protected function getCheckoutId(): ?string
    {
        try {
            return text(
                'Enter checkout ID - Press Ctrl+C to cancel',
                placeholder: '01hj5n7cqpaf0mt2d0xx85tgz8',
                required: true,
                validate: fn ($value) => strlen($value) > 10
                    ? null
                    : 'Please enter a valid checkout ID'
            );
        } catch (\Exception $e) {
            $this->info('Operation cancelled.');

            return null;
        }
    }

    protected function checkPaymentStatus(string $checkoutId): int
    {
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $app = $this->config->getApplication($currentApp);

        $this->displayHeader($app['name'], $currentMode);

        try {
            $this->line('⏳ Retrieving payment status...');

            $payment = $this->api
                ->setApplication($currentApp)
                ->setMode($currentMode)
                ->getCheckout($checkoutId);

            $this->displayPaymentStatus($payment);

            return 0;

        } catch (ChargilyApiException $e) {
            $this->error('❌ Failed to retrieve payment: '.$e->getUserMessage());
            $this->line('💡 '.$e->getSuggestedAction());

            return 1;
        }
    }

    protected function displayHeader(string $appName, string $mode): void
    {
        $modeDisplay = $mode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE';

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                    Payment Status                            ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("Application: {$appName}");
        $this->line("Mode: {$modeDisplay}");
        $this->line('');
    }

    protected function displayPaymentStatus(array $payment): void
    {
        $this->line('');
        $this->line('╔═══════════════════════════════════════════════════════════════╗');
        $this->line('║                     Payment Details                           ║');
        $this->line('╚═══════════════════════════════════════════════════════════════╝');
        $this->line('');

        // Status with color coding
        $status = strtoupper($payment['status']);
        $statusDisplay = match ($payment['status']) {
            'paid' => '<fg=green>✅ PAID</>',
            'pending' => '<fg=yellow>🟡 PENDING</>',
            'processing' => '<fg=blue>🔄 PROCESSING</>',
            'failed' => '<fg=red>❌ FAILED</>',
            'canceled' => '<fg=gray>❌ CANCELED</>',
            default => "📊 {$status}",
        };

        $this->line("Status: {$statusDisplay}");
        $this->line("Checkout ID: {$payment['id']}");
        $this->line('Amount: '.number_format($payment['amount']).' '.strtoupper($payment['currency']));

        if (isset($payment['description']) && $payment['description']) {
            $this->line("Description: {$payment['description']}");
        }

        if (isset($payment['checkout_url'])) {
            $this->line("Payment URL: {$payment['checkout_url']}");
        }

        if (isset($payment['success_url'])) {
            $this->line("Success URL: {$payment['success_url']}");
        }

        // Show creation time if available
        if (isset($payment['created_at'])) {
            $createdAt = date('Y-m-d H:i:s', $payment['created_at']);
            $this->line("Created: {$createdAt}");
        }

        // Status-specific information
        $this->line('');
        match ($payment['status']) {
            'paid' => $this->info('✅ Payment completed successfully!'),
            'pending' => $this->warn('⏳ Payment is waiting for customer action'),
            'processing' => $this->info('🔄 Payment is being processed'),
            'failed' => $this->error('❌ Payment failed - customer can try again'),
            'canceled' => $this->line('❌ Payment was canceled'),
            default => null,
        };

        // Show helpful next steps
        if ($payment['status'] === 'pending') {
            $this->line('');
            $this->line('💡 Next steps:');
            $this->line('   • Share the payment URL with your customer');
            $this->line('   • Payment will expire automatically after 30 minutes');
            $this->line('   • Check status again later: chargily payment:status '.$payment['id']);
        }
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
