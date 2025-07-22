<?php

namespace App\Commands;

use App\Exceptions\ChargilyApiException;
use App\Services\ChargilyApiService;
use App\Services\ConfigurationService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CustomerManageCommand extends Command
{
    protected $signature = 'customer:manage';

    protected $description = 'Comprehensive customer management';

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
            $this->api
                ->setApplication($currentApp)
                ->setMode($currentMode);

            while (true) {
                $action = select(
                    'What would you like to do?',
                    [
                        'list' => '📋 List Customers',
                        'create' => '➕ Create Customer',
                        'search' => '🔍 Search Customers',
                        'update' => '✏️ Update Customer',
                        'delete' => '🗑️ Delete Customer',
                        'view' => '👁️ View Customer Details',
                        'export' => '📤 Export Customer Data',
                        'exit' => '↩️ Back to Main Menu',
                    ]
                );

                match ($action) {
                    'list' => $this->listCustomers(),
                    'create' => $this->createCustomer(),
                    'search' => $this->searchCustomers(),
                    'update' => $this->updateCustomer(),
                    'delete' => $this->deleteCustomer(),
                    'view' => $this->viewCustomer(),
                    'export' => $this->exportCustomers(),
                    'exit' => null
                };

                if ($action === 'exit') {
                    break;
                }

                if ($action !== 'exit') {
                    $this->line('');
                    if (! confirm('Continue with customer management?', true)) {
                        break;
                    }
                    $this->line('');
                }
            }

            return 0;

        } catch (ChargilyApiException $e) {
            $this->error('❌ Customer operation failed: '.$e->getUserMessage());
            $this->line('💡 '.$e->getSuggestedAction());

            return 1;
        }
    }

    protected function displayHeader(string $appName, string $mode): void
    {
        $modeDisplay = $mode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE';

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                   Customer Management                       ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("Application: {$appName}");
        $this->line("Mode: {$modeDisplay}");
        $this->line('');
    }

    protected function listCustomers(): void
    {
        $limit = (int) select(
            'How many customers to show?',
            ['10', '25', '50', '100'],
            '25'
        );

        $this->line('⏳ Fetching customers...');

        $customers = $this->api->getCustomers(['per_page' => $limit]);

        if (empty($customers['data'])) {
            $this->info('📭 No customers found.');

            return;
        }

        $this->line('');
        $this->line('👥 Customer List');
        $this->line(str_repeat('─', 80));

        $headers = ['ID', 'Name', 'Email', 'Phone', 'Created', 'Payments'];
        $rows = array_map(function ($customer) {
            return [
                substr($customer['id'], 0, 8).'...',
                $customer['name'],
                $customer['email'] ?? 'N/A',
                $customer['phone'] ?? 'N/A',
                $this->formatDate($customer['created_at']),
                $customer['checkouts_count'] ?? '0',
            ];
        }, $customers['data']);

        $this->table($headers, $rows);
        $this->line('💡 Showing '.count($customers['data']).' customers');
    }

    protected function createCustomer(): void
    {
        $this->line('');
        $this->line('➕ Create New Customer');
        $this->line(str_repeat('─', 40));

        $name = text(
            'Customer name',
            required: true,
            placeholder: 'Ahmed Ben Ali'
        );

        $email = text(
            'Email address',
            required: true,
            placeholder: 'customer@example.com',
            validate: fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : 'Please enter a valid email address'
        );

        $phone = text(
            'Phone number (optional)',
            placeholder: '+213123456789'
        );

        $address = [];
        if (confirm('Add address information?', false)) {
            $address = [
                'country' => text('Country', default: 'DZ'),
                'state' => text('State/Province (optional)'),
                'address' => text('Street address (optional)'),
            ];
            $address = array_filter($address);
        }

        $metadata = [];
        if (confirm('Add custom metadata?', false)) {
            do {
                $key = text('Metadata key');
                $value = text('Metadata value');
                $metadata[$key] = $value;
            } while (confirm('Add another metadata field?', false));
        }

        $customerData = array_filter([
            'name' => $name,
            'email' => $email,
            'phone' => $phone ?: null,
            'address' => $address ?: null,
            'metadata' => $metadata ?: null,
        ]);

        // Preview
        $this->line('');
        $this->line('📋 Customer Preview');
        $this->line(str_repeat('─', 40));
        $this->line("Name: {$name}");
        $this->line("Email: {$email}");
        if ($phone) {
            $this->line("Phone: {$phone}");
        }
        if (! empty($address)) {
            $this->line('Address: '.implode(', ', array_filter($address)));
        }
        if (! empty($metadata)) {
            $this->line('Metadata: '.json_encode($metadata, JSON_PRETTY_PRINT));
        }

        if (! confirm('Create this customer?', true)) {
            $this->info('Customer creation cancelled.');

            return;
        }

        $this->line('⏳ Creating customer...');
        $customer = $this->api->createCustomer($customerData);

        $this->info('✅ Customer created successfully!');
        $this->line("🆔 Customer ID: {$customer['id']}");
        $this->line("👤 Name: {$customer['name']}");
        $this->line("📧 Email: {$customer['email']}");
    }

    protected function searchCustomers(): void
    {
        $searchTerm = text(
            'Search customers by name or email',
            required: true,
            placeholder: 'ahmed, customer@example.com'
        );

        $this->line('🔍 Searching customers...');

        $customers = $this->api->getCustomers([
            'search' => $searchTerm,
            'per_page' => 50,
        ]);

        if (empty($customers['data'])) {
            $this->info("📭 No customers found matching '{$searchTerm}'.");

            return;
        }

        $this->line('');
        $this->line("🔍 Search Results for: '{$searchTerm}'");
        $this->line(str_repeat('─', 80));

        foreach ($customers['data'] as $customer) {
            $this->line("🆔 ID: {$customer['id']}");
            $this->line("👤 Name: {$customer['name']}");
            $this->line('📧 Email: '.($customer['email'] ?? 'N/A'));
            $this->line('📱 Phone: '.($customer['phone'] ?? 'N/A'));
            $this->line('💳 Payments: '.($customer['checkouts_count'] ?? '0'));
            $this->line('📅 Created: '.$this->formatDate($customer['created_at']));
            $this->line(str_repeat('─', 40));
        }

        $this->line('💡 Found '.count($customers['data']).' customers');
    }

    protected function updateCustomer(): void
    {
        $customerId = text(
            'Customer ID to update',
            required: true,
            placeholder: 'customer_01234567...'
        );

        $this->line('⏳ Fetching customer details...');

        try {
            $customer = $this->api->getCustomer($customerId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Customer not found: {$customerId}");

            return;
        }

        $this->line('');
        $this->line('📋 Current Customer Details');
        $this->line(str_repeat('─', 40));
        $this->line("Name: {$customer['name']}");
        $this->line('Email: '.($customer['email'] ?? 'N/A'));
        $this->line('Phone: '.($customer['phone'] ?? 'N/A'));
        $this->line('');

        $updateData = [];

        if (confirm('Update name?', false)) {
            $updateData['name'] = text('New name', default: $customer['name']);
        }

        if (confirm('Update email?', false)) {
            $updateData['email'] = text(
                'New email',
                default: $customer['email'] ?? '',
                validate: fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                    ? null
                    : 'Please enter a valid email address'
            );
        }

        if (confirm('Update phone?', false)) {
            $updateData['phone'] = text('New phone', default: $customer['phone'] ?? '');
        }

        if (empty($updateData)) {
            $this->info('No changes made.');

            return;
        }

        if (! confirm('Save these changes?', true)) {
            $this->info('Update cancelled.');

            return;
        }

        $this->line('⏳ Updating customer...');
        $updatedCustomer = $this->api->updateCustomer($customerId, $updateData);

        $this->info('✅ Customer updated successfully!');
        $this->line("👤 Name: {$updatedCustomer['name']}");
        $this->line('📧 Email: '.($updatedCustomer['email'] ?? 'N/A'));
        $this->line('📱 Phone: '.($updatedCustomer['phone'] ?? 'N/A'));
    }

    protected function deleteCustomer(): void
    {
        $customerId = text(
            'Customer ID to delete',
            required: true,
            placeholder: 'customer_01234567...'
        );

        $this->line('⏳ Fetching customer details...');

        try {
            $customer = $this->api->getCustomer($customerId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Customer not found: {$customerId}");

            return;
        }

        $this->line('');
        $this->warn('⚠️ You are about to delete this customer:');
        $this->line("👤 Name: {$customer['name']}");
        $this->line('📧 Email: '.($customer['email'] ?? 'N/A'));
        $this->line('💳 Payments: '.($customer['checkouts_count'] ?? '0'));
        $this->line('');
        $this->error('🚨 This action cannot be undone!');

        if (! confirm('Are you absolutely sure you want to delete this customer?', false)) {
            $this->info('Deletion cancelled.');

            return;
        }

        if (! confirm('Type "DELETE" to confirm', false)) {
            $this->info('Deletion cancelled - confirmation not received.');

            return;
        }

        $this->line('⏳ Deleting customer...');
        $this->api->deleteCustomer($customerId);

        $this->info('✅ Customer deleted successfully.');
        $this->line("🗑️ Customer '{$customer['name']}' has been removed.");
    }

    protected function viewCustomer(): void
    {
        $customerId = text(
            'Customer ID to view',
            required: true,
            placeholder: 'customer_01234567...'
        );

        $this->line('⏳ Fetching customer details...');

        try {
            $customer = $this->api->getCustomer($customerId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Customer not found: {$customerId}");

            return;
        }

        $this->line('');
        $this->line('👤 Customer Details');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line("║ ID: {$customer['id']}");
        $this->line("║ Name: {$customer['name']}");
        $this->line('║ Email: '.($customer['email'] ?? 'N/A'));
        $this->line('║ Phone: '.($customer['phone'] ?? 'N/A'));
        $this->line('║ Payments: '.($customer['checkouts_count'] ?? '0'));
        $this->line('║ Created: '.$this->formatDate($customer['created_at']));
        $this->line('║ Updated: '.$this->formatDate($customer['updated_at']));

        if (! empty($customer['address'])) {
            $this->line('║ Address:');
            foreach ($customer['address'] as $key => $value) {
                $this->line("║   {$key}: {$value}");
            }
        }

        if (! empty($customer['metadata'])) {
            $this->line('║ Metadata:');
            foreach ($customer['metadata'] as $key => $value) {
                $this->line("║   {$key}: {$value}");
            }
        }

        $this->line('╚══════════════════════════════════════════════════════════════╝');

        // Show recent payments if any
        if (($customer['checkouts_count'] ?? 0) > 0) {
            if (confirm('View customer payment history?', false)) {
                $this->viewCustomerPayments($customerId);
            }
        }
    }

    protected function viewCustomerPayments(string $customerId): void
    {
        $this->line('⏳ Fetching payment history...');

        $payments = $this->api->getCheckouts(['customer_id' => $customerId, 'per_page' => 20]);

        if (empty($payments['data'])) {
            $this->info('📭 No payments found for this customer.');

            return;
        }

        $this->line('');
        $this->line('💳 Customer Payment History');
        $this->line(str_repeat('─', 80));

        $headers = ['ID', 'Amount', 'Status', 'Description', 'Created'];
        $rows = array_map(function ($payment) {
            return [
                substr($payment['id'], 0, 8).'...',
                number_format($payment['amount'] / 100, 2).' '.strtoupper($payment['currency']),
                $this->formatStatus($payment['status']),
                substr($payment['description'] ?? 'N/A', 0, 30),
                $this->formatDate($payment['created_at']),
            ];
        }, $payments['data']);

        $this->table($headers, $rows);
        $this->line('💡 Showing '.count($payments['data']).' payments');
    }

    protected function exportCustomers(): void
    {
        $limit = (int) select(
            'How many customers to export?',
            ['50', '100', '250', '500', '1000'],
            '100'
        );

        $this->line('⏳ Fetching customers for export...');

        $customers = $this->api->getCustomers(['per_page' => $limit]);

        if (empty($customers['data'])) {
            $this->info('📭 No customers found to export.');

            return;
        }

        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $filename = storage_path("app/customers_export_{$currentApp}_{$currentMode}_".date('Y_m_d_H_i_s').'.csv');

        // Ensure directory exists
        if (! is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }

        $file = fopen($filename, 'w');

        // CSV Headers
        fputcsv($file, [
            'ID', 'Name', 'Email', 'Phone', 'Country', 'State', 'Address',
            'Payments Count', 'Created At', 'Updated At',
        ]);

        // CSV Data
        foreach ($customers['data'] as $customer) {
            fputcsv($file, [
                $customer['id'],
                $customer['name'],
                $customer['email'] ?? '',
                $customer['phone'] ?? '',
                $customer['address']['country'] ?? '',
                $customer['address']['state'] ?? '',
                $customer['address']['address'] ?? '',
                $customer['checkouts_count'] ?? '0',
                $customer['created_at'],
                $customer['updated_at'],
            ]);
        }

        fclose($file);

        $this->info("✅ Customers exported to: {$filename}");
        $this->line('📁 File contains '.count($customers['data']).' customer records');
    }

    protected function formatStatus(string $status): string
    {
        $colors = [
            'pending' => 'yellow',
            'paid' => 'green',
            'failed' => 'red',
            'canceled' => 'gray',
            'expired' => 'gray',
        ];

        $color = $colors[$status] ?? 'white';

        return "<fg={$color}>".ucfirst($status).'</>';
    }

    protected function formatDate(string $date): string
    {
        return date('M j, Y H:i', strtotime($date));
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
