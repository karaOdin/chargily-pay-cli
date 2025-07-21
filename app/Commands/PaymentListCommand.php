<?php

namespace App\Commands;

use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use App\Exceptions\ChargilyApiException;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Console\Scheduling\Schedule;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;

class PaymentListCommand extends Command
{
    protected $signature = 'payment:list 
                           {--limit=10 : Number of payments to show}
                           {--status= : Filter by status (pending, paid, failed, canceled)}
                           {--from= : Start date (Y-m-d format)}
                           {--to= : End date (Y-m-d format)}
                           {--export : Export results to CSV}';

    protected $description = 'List and filter payments';

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
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $app = $this->config->getApplication($currentApp);

        $this->displayHeader($app['name'], $currentMode);

        try {
            // Get filter options
            $filters = $this->getFilters();
            
            // Fetch payments
            $this->line('⏳ Fetching payments...');
            
            $payments = $this->api
                ->setApplication($currentApp)
                ->setMode($currentMode)
                ->getCheckouts($filters);
            
            if (empty($payments['data'])) {
                $this->info('📭 No payments found matching your criteria.');
                return 0;
            }

            // Apply client-side filtering if server-side filtering didn't work
            $filteredPayments = $this->applyClientSideFilters($payments['data'], $filters);
            
            // Display payments
            $this->displayPayments($filteredPayments, $filters);

            // Export option
            if ($this->option('export') || confirm('Export results to CSV?', false)) {
                $this->exportToCsv($payments['data'], $currentApp, $currentMode);
            }

            return 0;

        } catch (ChargilyApiException $e) {
            $this->error('❌ Failed to fetch payments: ' . $e->getUserMessage());
            $this->line('💡 ' . $e->getSuggestedAction());
            return 1;
        }
    }

    protected function displayHeader(string $appName, string $mode): void
    {
        $modeDisplay = $mode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE';
        
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                     Payment List                            ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("Application: {$appName}");
        $this->line("Mode: {$modeDisplay}");
        $this->line('');
    }

    protected function getFilters(): array
    {
        $filters = [];

        // Limit
        $limit = $this->option('limit') ?: 10;
        if (!$this->option('limit')) {
            try {
                $limit = (int) select(
                    'How many payments to show? (Press Ctrl+C to cancel)',
                    ['5', '10', '25', '50', '100'],
                    10
                );
            } catch (\Exception $e) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        $filters['per_page'] = $limit;

        // Status filter
        $status = $this->option('status');
        if (!$status && confirm('Filter by status? (Press Ctrl+C to cancel)', false)) {
            try {
                $status = select(
                    'Select payment status (Press Ctrl+C to cancel)',
                    [
                        'pending' => 'Pending',
                        'paid' => 'Paid', 
                        'failed' => 'Failed',
                        'canceled' => 'Canceled',
                        'expired' => 'Expired'
                    ]
                );
            } catch (\Exception $e) {
                $this->info('Status filter cancelled.');
                $status = null;
            }
        }
        if ($status) {
            $filters['status'] = $status;
        }

        // Date filters
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        
        if (!$fromDate && !$toDate && confirm('Filter by date range? (Press Ctrl+C to cancel)', false)) {
            try {
                $fromDate = text(
                    'Start date (YYYY-MM-DD format) - Press Ctrl+C to cancel',
                    placeholder: date('Y-m-d', strtotime('-30 days')),
                    validate: fn ($value) => $value && !strtotime($value) 
                        ? 'Invalid date format. Use YYYY-MM-DD'
                        : null
                );
                
                $toDate = text(
                    'End date (YYYY-MM-DD format) - Press Ctrl+C to cancel', 
                    placeholder: date('Y-m-d'),
                    validate: fn ($value) => $value && !strtotime($value)
                        ? 'Invalid date format. Use YYYY-MM-DD' 
                        : null
                );
            } catch (\Exception $e) {
                $this->info('Date filter cancelled.');
                $fromDate = $toDate = null;
            }
        }

        if ($fromDate) $filters['created_at_from'] = $fromDate;
        if ($toDate) $filters['created_at_to'] = $toDate;

        return $filters;
    }

    protected function displayPayments(array $payments, array $filters): void
    {
        $this->line('');
        $this->line('📋 Payment Results');
        $this->line(str_repeat('─', 80));
        
        // Show applied filters
        if (!empty($filters)) {
            $filterText = 'Filters: ';
            $filterParts = [];
            
            if (isset($filters['status'])) {
                $filterParts[] = "Status: " . ucfirst($filters['status']);
            }
            if (isset($filters['created_at_from'])) {
                $filterParts[] = "From: " . $filters['created_at_from'];
            }
            if (isset($filters['created_at_to'])) {
                $filterParts[] = "To: " . $filters['created_at_to'];
            }
            if (isset($filters['per_page'])) {
                $filterParts[] = "Limit: " . $filters['per_page'];
            }
            
            if (!empty($filterParts)) {
                $this->line('🔍 ' . $filterText . implode(' | ', $filterParts));
                $this->line('');
            }
        }

        // Table headers
        $headers = ['ID', 'Amount', 'Status', 'Customer', 'Created', 'Updated'];
        $this->table($headers, array_map(function ($payment) {
            return [
                $payment['id'], // Show full ID
                number_format($payment['amount'] / 100, 2) . ' ' . strtoupper($payment['currency']),
                $this->formatStatus($payment['status']),
                $payment['customer']['name'] ?? 'N/A',
                $this->formatDate($payment['created_at']),
                $this->formatDate($payment['updated_at'])
            ];
        }, $payments));

        $this->line('');
        $this->displaySummary($payments, $filters);
        $this->line("💡 Showing " . count($payments) . " payments");
        $this->line("💡 Use 'chargily payment:status <id>' to view payment details");
        
        // Wait for user input before returning (when called from menu)
        $this->waitForUser();
    }

    protected function applyClientSideFilters(array $payments, array $filters): array
    {
        $filtered = $payments;
        
        // Filter by status if specified
        if (isset($filters['status'])) {
            $filtered = array_filter($filtered, function ($payment) use ($filters) {
                return $payment['status'] === $filters['status'];
            });
        }
        
        // Filter by date range if specified
        if (isset($filters['created_at_from'])) {
            $fromTimestamp = strtotime($filters['created_at_from']);
            $filtered = array_filter($filtered, function ($payment) use ($fromTimestamp) {
                $paymentTimestamp = is_numeric($payment['created_at']) ? 
                    $payment['created_at'] : 
                    strtotime($payment['created_at']);
                return $paymentTimestamp >= $fromTimestamp;
            });
        }
        
        if (isset($filters['created_at_to'])) {
            $toTimestamp = strtotime($filters['created_at_to'] . ' 23:59:59');
            $filtered = array_filter($filtered, function ($payment) use ($toTimestamp) {
                $paymentTimestamp = is_numeric($payment['created_at']) ? 
                    $payment['created_at'] : 
                    strtotime($payment['created_at']);
                return $paymentTimestamp <= $toTimestamp;
            });
        }
        
        // Apply limit if specified (only if we're doing client-side filtering)
        if (isset($filters['per_page']) && count($filtered) > $filters['per_page']) {
            $filtered = array_slice($filtered, 0, $filters['per_page']);
        }
        
        return array_values($filtered); // Re-index array
    }

    protected function displaySummary(array $payments, array $filters): void
    {
        // Calculate totals by status
        $statusTotals = [];
        $amountTotals = [];
        
        foreach ($payments as $payment) {
            $status = $payment['status'];
            $amount = $payment['amount'] / 100; // Convert from cents
            
            if (!isset($statusTotals[$status])) {
                $statusTotals[$status] = 0;
                $amountTotals[$status] = 0;
            }
            
            $statusTotals[$status]++;
            $amountTotals[$status] += $amount;
        }
        
        if (!empty($statusTotals)) {
            $this->line('📊 Summary:');
            
            $totalCount = array_sum($statusTotals);
            $totalAmount = array_sum($amountTotals);
            
            foreach ($statusTotals as $status => $count) {
                $amount = number_format($amountTotals[$status], 2);
                $percentage = round(($count / $totalCount) * 100, 1);
                $statusIcon = $this->getStatusIcon($status);
                $this->line("   {$statusIcon} " . ucfirst($status) . ": {$count} payments ({$percentage}%) - {$amount} DZD");
            }
            
            $this->line("   💰 Total: {$totalCount} payments - " . number_format($totalAmount, 2) . " DZD");
            $this->line('');
        }
    }
    
    protected function getStatusIcon(string $status): string
    {
        return match($status) {
            'paid' => '✅',
            'pending' => '🟡',
            'failed' => '❌',
            'canceled' => '⏹️',
            'expired' => '⏰',
            default => '⚪'
        };
    }

    protected function formatStatus(string $status): string
    {
        $colors = [
            'pending' => 'yellow',
            'paid' => 'green', 
            'failed' => 'red',
            'canceled' => 'gray',
            'expired' => 'gray'
        ];

        $color = $colors[$status] ?? 'white';
        return "<fg={$color}>" . ucfirst($status) . "</>";
    }

    protected function formatDate($date): string
    {
        try {
            // Handle null or empty dates
            if (empty($date)) {
                return 'N/A';
            }
            
            // Handle Unix timestamp (integer)
            if (is_numeric($date)) {
                return date('M j, Y H:i', (int) $date);
            }
            
            // Handle string dates
            if (is_string($date)) {
                $timestamp = strtotime($date);
                if ($timestamp !== false) {
                    return date('M j, Y H:i', $timestamp);
                }
                
                // Try parsing as ISO 8601 format
                $dateObj = \DateTime::createFromFormat('c', $date) ?: 
                          \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $date) ?:
                          \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $date) ?:
                          \DateTime::createFromFormat('Y-m-d H:i:s', $date);
                
                if ($dateObj) {
                    return $dateObj->format('M j, Y H:i');
                }
            }
            
            return 'Invalid date';
        } catch (\Exception $e) {
            return 'Invalid date';
        }
    }

    protected function exportToCsv(array $payments, string $appId, string $mode): void
    {
        $filename = storage_path("app/payments_export_{$appId}_{$mode}_" . date('Y_m_d_H_i_s') . '.csv');
        
        // Ensure directory exists
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }

        $file = fopen($filename, 'w');
        
        // CSV Headers
        fputcsv($file, [
            'ID', 'Amount', 'Currency', 'Status', 'Description',
            'Customer Name', 'Customer Email', 'Customer Phone',
            'Success URL', 'Failure URL', 'Webhook URL',
            'Created At', 'Updated At'
        ]);

        // CSV Data
        foreach ($payments as $payment) {
            fputcsv($file, [
                $payment['id'],
                $payment['amount'] / 100,
                $payment['currency'],
                $payment['status'],
                $payment['description'] ?? '',
                $payment['customer']['name'] ?? '',
                $payment['customer']['email'] ?? '',
                $payment['customer']['phone'] ?? '',
                $payment['success_url'] ?? '',
                $payment['failure_url'] ?? '',
                $payment['webhook_url'] ?? '',
                $payment['created_at'],
                $payment['updated_at']
            ]);
        }

        fclose($file);

        $this->info("✅ Payments exported to: {$filename}");
        $this->line("📁 File contains " . count($payments) . " payment records");
    }

    protected function waitForUser(): void
    {
        // Only wait if we're in an interactive environment and not exporting
        if ($this->input->isInteractive() && !$this->option('export')) {
            $this->line('');
            $this->info('Press any key to continue...');
            $this->line('');
            
            // Simple way to wait for user input
            fgets(STDIN);
        }
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}