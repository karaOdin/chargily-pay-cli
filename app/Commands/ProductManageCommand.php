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

class ProductManageCommand extends Command
{
    protected $signature = 'product:manage';

    protected $description = 'Comprehensive product and price management';

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
                $category = select(
                    'What would you like to manage?',
                    [
                        'products' => '📦 Products',
                        'prices' => '💰 Prices',
                        'exit' => '↩️ Back to Main Menu',
                    ]
                );

                match ($category) {
                    'products' => $this->manageProducts(),
                    'prices' => $this->managePrices(),
                    'exit' => null
                };

                if ($category === 'exit') {
                    break;
                }

                if ($category !== 'exit') {
                    $this->line('');
                    if (! confirm('Continue with product management?', true)) {
                        break;
                    }
                    $this->line('');
                }
            }

            return 0;

        } catch (ChargilyApiException $e) {
            $this->error('❌ Product operation failed: '.$e->getUserMessage());
            $this->line('💡 '.$e->getSuggestedAction());

            return 1;
        }
    }

    protected function displayHeader(string $appName, string $mode): void
    {
        $modeDisplay = $mode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE';

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                Product & Price Management                   ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("Application: {$appName}");
        $this->line("Mode: {$modeDisplay}");
        $this->line('');
    }

    protected function manageProducts(): void
    {
        while (true) {
            $action = select(
                'Product Management',
                [
                    'list' => '📋 List Products',
                    'create' => '➕ Create Product',
                    'update' => '✏️ Update Product',
                    'delete' => '🗑️ Delete Product',
                    'view' => '👁️ View Product Details',
                    'export' => '📤 Export Products',
                    'back' => '↩️ Back',
                ]
            );

            match ($action) {
                'list' => $this->listProducts(),
                'create' => $this->createProduct(),
                'update' => $this->updateProduct(),
                'delete' => $this->deleteProduct(),
                'view' => $this->viewProduct(),
                'export' => $this->exportProducts(),
                'back' => null
            };

            if ($action === 'back') {
                break;
            }

            if ($action !== 'back') {
                $this->line('');
                if (! confirm('Continue with product operations?', true)) {
                    break;
                }
                $this->line('');
            }
        }
    }

    protected function managePrices(): void
    {
        while (true) {
            $action = select(
                'Price Management',
                [
                    'list' => '📋 List Prices',
                    'create' => '➕ Create Price',
                    'update' => '✏️ Update Price',
                    'delete' => '🗑️ Delete Price',
                    'view' => '👁️ View Price Details',
                    'bulk' => '📊 Bulk Price Operations',
                    'back' => '↩️ Back',
                ]
            );

            match ($action) {
                'list' => $this->listPrices(),
                'create' => $this->createPrice(),
                'update' => $this->updatePrice(),
                'delete' => $this->deletePrice(),
                'view' => $this->viewPrice(),
                'bulk' => $this->bulkPriceOperations(),
                'back' => null
            };

            if ($action === 'back') {
                break;
            }

            if ($action !== 'back') {
                $this->line('');
                if (! confirm('Continue with price operations?', true)) {
                    break;
                }
                $this->line('');
            }
        }
    }

    // Product Management Methods

    protected function listProducts(): void
    {
        $limit = (int) select(
            'How many products to show?',
            ['10', '25', '50', '100'],
            '25'
        );

        $this->line('⏳ Fetching products...');

        $products = $this->api->getProducts(['per_page' => $limit]);

        if (empty($products['data'])) {
            $this->info('📭 No products found.');

            return;
        }

        $this->line('');
        $this->line('📦 Product Catalog');
        $this->line(str_repeat('─', 80));

        $headers = ['ID', 'Name', 'Description', 'Status', 'Prices', 'Created'];
        $rows = array_map(function ($product) {
            return [
                substr($product['id'], 0, 8).'...',
                $product['name'],
                substr($product['description'] ?? 'N/A', 0, 30),
                $product['active'] ? '✅ Active' : '❌ Inactive',
                count($product['prices'] ?? []),
                $this->formatDate($product['created_at']),
            ];
        }, $products['data']);

        $this->table($headers, $rows);
        $this->line('💡 Showing '.count($products['data']).' products');
    }

    protected function createProduct(): void
    {
        $this->line('');
        $this->line('➕ Create New Product');
        $this->line(str_repeat('─', 40));

        $name = text(
            'Product name',
            required: true,
            placeholder: 'Premium Subscription, eBook, Course'
        );

        $description = text(
            'Product description (optional)',
            placeholder: 'Detailed description of your product'
        );

        $images = [];
        if (confirm('Add product images?', false)) {
            do {
                $imageUrl = text(
                    'Image URL',
                    placeholder: 'https://example.com/product-image.jpg',
                    validate: fn ($value) => filter_var($value, FILTER_VALIDATE_URL)
                        ? null
                        : 'Please enter a valid URL'
                );
                $images[] = $imageUrl;
            } while (confirm('Add another image?', false));
        }

        $metadata = [];
        if (confirm('Add custom metadata?', false)) {
            do {
                $key = text('Metadata key');
                $value = text('Metadata value');
                $metadata[$key] = $value;
            } while (confirm('Add another metadata field?', false));
        }

        $productData = array_filter([
            'name' => $name,
            'description' => $description ?: null,
            'images' => $images ?: null,
            'metadata' => $metadata ?: null,
        ]);

        // Preview
        $this->line('');
        $this->line('📋 Product Preview');
        $this->line(str_repeat('─', 40));
        $this->line("Name: {$name}");
        if ($description) {
            $this->line("Description: {$description}");
        }
        if (! empty($images)) {
            $this->line('Images: '.count($images).' image(s)');
            foreach ($images as $i => $image) {
                $this->line('  '.($i + 1).". {$image}");
            }
        }
        if (! empty($metadata)) {
            $this->line('Metadata: '.json_encode($metadata, JSON_PRETTY_PRINT));
        }

        if (! confirm('Create this product?', true)) {
            $this->info('Product creation cancelled.');

            return;
        }

        $this->line('⏳ Creating product...');
        $product = $this->api->createProduct($productData);

        $this->info('✅ Product created successfully!');
        $this->line("🆔 Product ID: {$product['id']}");
        $this->line("📦 Name: {$product['name']}");
        $this->line('📝 Description: '.($product['description'] ?? 'N/A'));

        if (confirm('Create a price for this product now?', true)) {
            $this->createPriceForProduct($product['id']);
        }
    }

    protected function updateProduct(): void
    {
        $productId = text(
            'Product ID to update',
            required: true,
            placeholder: 'product_01234567...'
        );

        $this->line('⏳ Fetching product details...');

        try {
            $product = $this->api->getProduct($productId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Product not found: {$productId}");

            return;
        }

        $this->line('');
        $this->line('📋 Current Product Details');
        $this->line(str_repeat('─', 40));
        $this->line("Name: {$product['name']}");
        $this->line('Description: '.($product['description'] ?? 'N/A'));
        $this->line('Status: '.($product['active'] ? 'Active' : 'Inactive'));
        $this->line('');

        $updateData = [];

        if (confirm('Update name?', false)) {
            $updateData['name'] = text('New name', default: $product['name']);
        }

        if (confirm('Update description?', false)) {
            $updateData['description'] = text(
                'New description',
                default: $product['description'] ?? ''
            );
        }

        if (confirm('Change active status?', false)) {
            $updateData['active'] = confirm(
                'Should this product be active?',
                $product['active']
            );
        }

        if (empty($updateData)) {
            $this->info('No changes made.');

            return;
        }

        if (! confirm('Save these changes?', true)) {
            $this->info('Update cancelled.');

            return;
        }

        $this->line('⏳ Updating product...');
        $updatedProduct = $this->api->updateProduct($productId, $updateData);

        $this->info('✅ Product updated successfully!');
        $this->line("📦 Name: {$updatedProduct['name']}");
        $this->line('📝 Description: '.($updatedProduct['description'] ?? 'N/A'));
        $this->line('Status: '.($updatedProduct['active'] ? '✅ Active' : '❌ Inactive'));
    }

    protected function deleteProduct(): void
    {
        $productId = text(
            'Product ID to delete',
            required: true,
            placeholder: 'product_01234567...'
        );

        $this->line('⏳ Fetching product details...');

        try {
            $product = $this->api->getProduct($productId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Product not found: {$productId}");

            return;
        }

        $priceCount = count($product['prices'] ?? []);

        $this->line('');
        $this->warn('⚠️ You are about to delete this product:');
        $this->line("📦 Name: {$product['name']}");
        $this->line('📝 Description: '.($product['description'] ?? 'N/A'));
        $this->line("💰 Associated Prices: {$priceCount}");
        $this->line('');
        $this->error('🚨 This will also delete all associated prices and cannot be undone!');

        if (! confirm('Are you absolutely sure you want to delete this product?', false)) {
            $this->info('Deletion cancelled.');

            return;
        }

        if (! confirm('Type "DELETE" to confirm', false)) {
            $this->info('Deletion cancelled - confirmation not received.');

            return;
        }

        $this->line('⏳ Deleting product...');
        $this->api->deleteProduct($productId);

        $this->info('✅ Product deleted successfully.');
        $this->line("🗑️ Product '{$product['name']}' and {$priceCount} associated prices have been removed.");
    }

    protected function viewProduct(): void
    {
        $productId = text(
            'Product ID to view',
            required: true,
            placeholder: 'product_01234567...'
        );

        $this->line('⏳ Fetching product details...');

        try {
            $product = $this->api->getProduct($productId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Product not found: {$productId}");

            return;
        }

        $this->line('');
        $this->line('📦 Product Details');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line("║ ID: {$product['id']}");
        $this->line("║ Name: {$product['name']}");
        $this->line('║ Description: '.($product['description'] ?? 'N/A'));
        $this->line('║ Status: '.($product['active'] ? '✅ Active' : '❌ Inactive'));
        $this->line('║ Created: '.$this->formatDate($product['created_at']));
        $this->line('║ Updated: '.$this->formatDate($product['updated_at']));

        if (! empty($product['images'])) {
            $this->line('║ Images: '.count($product['images']));
            foreach ($product['images'] as $i => $image) {
                $this->line('║   '.($i + 1).". {$image}");
            }
        }

        if (! empty($product['metadata'])) {
            $this->line('║ Metadata:');
            foreach ($product['metadata'] as $key => $value) {
                $this->line("║   {$key}: {$value}");
            }
        }

        $this->line('╚══════════════════════════════════════════════════════════════╝');

        // Show associated prices
        if (! empty($product['prices'])) {
            $this->line('');
            $this->line('💰 Associated Prices');
            $this->line(str_repeat('─', 80));

            $headers = ['Price ID', 'Amount', 'Currency', 'Type', 'Status'];
            $rows = array_map(function ($price) {
                return [
                    substr($price['id'], 0, 8).'...',
                    number_format($price['amount'] / 100, 2),
                    strtoupper($price['currency']),
                    ucfirst($price['type'] ?? 'one_time'),
                    $price['active'] ? '✅ Active' : '❌ Inactive',
                ];
            }, $product['prices']);

            $this->table($headers, $rows);
        }
    }

    protected function exportProducts(): void
    {
        $limit = (int) select(
            'How many products to export?',
            ['50', '100', '250', '500'],
            '100'
        );

        $this->line('⏳ Fetching products for export...');

        $products = $this->api->getProducts(['per_page' => $limit]);

        if (empty($products['data'])) {
            $this->info('📭 No products found to export.');

            return;
        }

        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $filename = storage_path("app/products_export_{$currentApp}_{$currentMode}_".date('Y_m_d_H_i_s').'.csv');

        // Ensure directory exists
        if (! is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }

        $file = fopen($filename, 'w');

        // CSV Headers
        fputcsv($file, [
            'ID', 'Name', 'Description', 'Active', 'Images Count', 'Prices Count',
            'Created At', 'Updated At',
        ]);

        // CSV Data
        foreach ($products['data'] as $product) {
            fputcsv($file, [
                $product['id'],
                $product['name'],
                $product['description'] ?? '',
                $product['active'] ? 'Yes' : 'No',
                count($product['images'] ?? []),
                count($product['prices'] ?? []),
                $product['created_at'],
                $product['updated_at'],
            ]);
        }

        fclose($file);

        $this->info("✅ Products exported to: {$filename}");
        $this->line('📁 File contains '.count($products['data']).' product records');
    }

    // Price Management Methods

    protected function listPrices(): void
    {
        $limit = (int) select(
            'How many prices to show?',
            ['10', '25', '50', '100'],
            '25'
        );

        $this->line('⏳ Fetching prices...');

        $prices = $this->api->getPrices(['per_page' => $limit]);

        if (empty($prices['data'])) {
            $this->info('📭 No prices found.');

            return;
        }

        $this->line('');
        $this->line('💰 Price List');
        $this->line(str_repeat('─', 80));

        $headers = ['ID', 'Product', 'Amount', 'Currency', 'Type', 'Status', 'Created'];
        $rows = array_map(function ($price) {
            return [
                substr($price['id'], 0, 8).'...',
                $price['product']['name'] ?? 'N/A',
                number_format($price['amount'] / 100, 2),
                strtoupper($price['currency']),
                ucfirst($price['type'] ?? 'one_time'),
                $price['active'] ? '✅ Active' : '❌ Inactive',
                $this->formatDate($price['created_at']),
            ];
        }, $prices['data']);

        $this->table($headers, $rows);
        $this->line('💡 Showing '.count($prices['data']).' prices');
    }

    protected function createPrice(): void
    {
        $productId = $this->selectProductForPrice();
        if (! $productId) {
            return;
        }

        $this->createPriceForProduct($productId);
    }

    protected function createPriceForProduct(string $productId): void
    {
        $this->line('');
        $this->line('💰 Create New Price');
        $this->line(str_repeat('─', 40));

        $amount = (int) text(
            'Price amount (in centimes, e.g., 2500 for 25.00 DZD)',
            required: true,
            placeholder: '2500',
            validate: fn ($value) => is_numeric($value) && $value > 0
                ? null
                : 'Amount must be a positive number'
        );

        $currency = select(
            'Currency',
            ['dzd' => 'DZD (Algerian Dinar)', 'usd' => 'USD (US Dollar)', 'eur' => 'EUR (Euro)'],
            'dzd'
        );

        $type = select(
            'Price type',
            [
                'one_time' => 'One-time payment',
                'recurring' => 'Recurring subscription',
            ],
            'one_time'
        );

        $intervalData = [];
        if ($type === 'recurring') {
            $interval = select(
                'Billing interval',
                [
                    'month' => 'Monthly',
                    'year' => 'Yearly',
                    'week' => 'Weekly',
                    'day' => 'Daily',
                ],
                'month'
            );

            $intervalCount = (int) text(
                'Interval count (e.g., 1 for every month, 3 for every 3 months)',
                default: '1',
                validate: fn ($value) => is_numeric($value) && $value > 0
                    ? null
                    : 'Interval count must be a positive number'
            );

            $intervalData = [
                'interval' => $interval,
                'interval_count' => $intervalCount,
            ];
        }

        $metadata = [];
        if (confirm('Add custom metadata?', false)) {
            do {
                $key = text('Metadata key');
                $value = text('Metadata value');
                $metadata[$key] = $value;
            } while (confirm('Add another metadata field?', false));
        }

        $priceData = array_filter([
            'product_id' => $productId,
            'amount' => $amount,
            'currency' => $currency,
            'type' => $type,
            'metadata' => $metadata ?: null,
            ...$intervalData,
        ]);

        // Preview
        $this->line('');
        $this->line('💰 Price Preview');
        $this->line(str_repeat('─', 40));
        $this->line('Amount: '.number_format($amount / 100, 2).' '.strtoupper($currency));
        $this->line('Type: '.ucfirst(str_replace('_', ' ', $type)));
        if ($type === 'recurring') {
            $intervalText = $intervalData['interval_count'] > 1
                ? "Every {$intervalData['interval_count']} {$intervalData['interval']}s"
                : "Every {$intervalData['interval']}";
            $this->line("Billing: {$intervalText}");
        }
        if (! empty($metadata)) {
            $this->line('Metadata: '.json_encode($metadata, JSON_PRETTY_PRINT));
        }

        if (! confirm('Create this price?', true)) {
            $this->info('Price creation cancelled.');

            return;
        }

        $this->line('⏳ Creating price...');
        $price = $this->api->createPrice($priceData);

        $this->info('✅ Price created successfully!');
        $this->line("🆔 Price ID: {$price['id']}");
        $this->line('💰 Amount: '.number_format($price['amount'] / 100, 2).' '.strtoupper($price['currency']));
        $this->line("📦 Product: {$productId}");
    }

    protected function selectProductForPrice(): ?string
    {
        $this->line('⏳ Fetching available products...');

        $products = $this->api->getProducts(['per_page' => 50, 'active' => true]);

        if (empty($products['data'])) {
            $this->error('❌ No active products found. Create a product first.');

            return null;
        }

        $productOptions = [];
        foreach ($products['data'] as $product) {
            $productOptions[$product['id']] = $product['name'].' (ID: '.substr($product['id'], 0, 8).'...)';
        }

        return select('Select a product for this price', $productOptions);
    }

    protected function updatePrice(): void
    {
        $priceId = text(
            'Price ID to update',
            required: true,
            placeholder: 'price_01234567...'
        );

        $this->line('⏳ Fetching price details...');

        try {
            $price = $this->api->getPrice($priceId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Price not found: {$priceId}");

            return;
        }

        $this->line('');
        $this->line('💰 Current Price Details');
        $this->line(str_repeat('─', 40));
        $this->line('Amount: '.number_format($price['amount'] / 100, 2).' '.strtoupper($price['currency']));
        $this->line('Type: '.ucfirst(str_replace('_', ' ', $price['type'] ?? 'one_time')));
        $this->line('Status: '.($price['active'] ? 'Active' : 'Inactive'));
        $this->line('');

        $updateData = [];

        if (confirm('Change active status?', false)) {
            $updateData['active'] = confirm(
                'Should this price be active?',
                $price['active']
            );
        }

        if (confirm('Update metadata?', false)) {
            $metadata = [];
            do {
                $key = text('Metadata key');
                $value = text('Metadata value');
                $metadata[$key] = $value;
            } while (confirm('Add another metadata field?', false));
            $updateData['metadata'] = $metadata;
        }

        if (empty($updateData)) {
            $this->info('No changes made.');

            return;
        }

        if (! confirm('Save these changes?', true)) {
            $this->info('Update cancelled.');

            return;
        }

        $this->line('⏳ Updating price...');
        $updatedPrice = $this->api->updatePrice($priceId, $updateData);

        $this->info('✅ Price updated successfully!');
        $this->line('💰 Amount: '.number_format($updatedPrice['amount'] / 100, 2).' '.strtoupper($updatedPrice['currency']));
        $this->line('Status: '.($updatedPrice['active'] ? '✅ Active' : '❌ Inactive'));
    }

    protected function deletePrice(): void
    {
        $priceId = text(
            'Price ID to delete',
            required: true,
            placeholder: 'price_01234567...'
        );

        $this->line('⏳ Fetching price details...');

        try {
            $price = $this->api->getPrice($priceId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Price not found: {$priceId}");

            return;
        }

        $this->line('');
        $this->warn('⚠️ You are about to delete this price:');
        $this->line('💰 Amount: '.number_format($price['amount'] / 100, 2).' '.strtoupper($price['currency']));
        $this->line('📦 Product: '.($price['product']['name'] ?? 'N/A'));
        $this->line('Type: '.ucfirst(str_replace('_', ' ', $price['type'] ?? 'one_time')));
        $this->line('');
        $this->error('🚨 This action cannot be undone!');

        if (! confirm('Are you absolutely sure you want to delete this price?', false)) {
            $this->info('Deletion cancelled.');

            return;
        }

        $this->line('⏳ Deleting price...');
        $this->api->deletePrice($priceId);

        $this->info('✅ Price deleted successfully.');
        $this->line('🗑️ Price has been removed from the product.');
    }

    protected function viewPrice(): void
    {
        $priceId = text(
            'Price ID to view',
            required: true,
            placeholder: 'price_01234567...'
        );

        $this->line('⏳ Fetching price details...');

        try {
            $price = $this->api->getPrice($priceId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Price not found: {$priceId}");

            return;
        }

        $this->line('');
        $this->line('💰 Price Details');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line("║ ID: {$price['id']}");
        $this->line('║ Amount: '.number_format($price['amount'] / 100, 2).' '.strtoupper($price['currency']));
        $this->line('║ Type: '.ucfirst(str_replace('_', ' ', $price['type'] ?? 'one_time')));
        $this->line('║ Status: '.($price['active'] ? '✅ Active' : '❌ Inactive'));
        $this->line('║ Product: '.($price['product']['name'] ?? 'N/A'));
        $this->line('║ Created: '.$this->formatDate($price['created_at']));
        $this->line('║ Updated: '.$this->formatDate($price['updated_at']));

        if (isset($price['interval'])) {
            $intervalText = ($price['interval_count'] ?? 1) > 1
                ? "Every {$price['interval_count']} {$price['interval']}s"
                : "Every {$price['interval']}";
            $this->line("║ Billing: {$intervalText}");
        }

        if (! empty($price['metadata'])) {
            $this->line('║ Metadata:');
            foreach ($price['metadata'] as $key => $value) {
                $this->line("║   {$key}: {$value}");
            }
        }

        $this->line('╚══════════════════════════════════════════════════════════════╝');
    }

    protected function bulkPriceOperations(): void
    {
        $operation = select(
            'Bulk Price Operations',
            [
                'activate' => 'Activate multiple prices',
                'deactivate' => 'Deactivate multiple prices',
                'export' => 'Export all prices',
                'back' => 'Back',
            ]
        );

        match ($operation) {
            'activate' => $this->bulkActivatePrices(true),
            'deactivate' => $this->bulkActivatePrices(false),
            'export' => $this->exportPrices(),
            'back' => null
        };
    }

    protected function bulkActivatePrices(bool $active): void
    {
        $action = $active ? 'activate' : 'deactivate';
        $status = $active ? 'active' : 'inactive';

        $this->line("⏳ Fetching prices to {$action}...");

        $prices = $this->api->getPrices(['per_page' => 100, 'active' => ! $active]);

        if (empty($prices['data'])) {
            $this->info("📭 No {$status} prices found.");

            return;
        }

        $this->line('');
        $this->line('💰 Found '.count($prices['data'])." prices to {$action}:");

        foreach ($prices['data'] as $price) {
            $this->line('• '.number_format($price['amount'] / 100, 2).' '.strtoupper($price['currency']).
                       ' - '.($price['product']['name'] ?? 'N/A'));
        }

        if (! confirm("Proceed to {$action} all these prices?", false)) {
            $this->info("Bulk {$action} cancelled.");

            return;
        }

        $this->line("⏳ Bulk {$action} in progress...");

        $updated = 0;
        foreach ($prices['data'] as $price) {
            try {
                $this->api->updatePrice($price['id'], ['active' => $active]);
                $updated++;
            } catch (ChargilyApiException $e) {
                $this->warn("❌ Failed to {$action} price {$price['id']}: ".$e->getUserMessage());
            }
        }

        $this->info('✅ Bulk operation completed!');
        $this->line("📊 {$updated} out of ".count($prices['data']).' prices were successfully '.($active ? 'activated' : 'deactivated'));
    }

    protected function exportPrices(): void
    {
        $limit = (int) select(
            'How many prices to export?',
            ['100', '250', '500', '1000'],
            '250'
        );

        $this->line('⏳ Fetching prices for export...');

        $prices = $this->api->getPrices(['per_page' => $limit]);

        if (empty($prices['data'])) {
            $this->info('📭 No prices found to export.');

            return;
        }

        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $filename = storage_path("app/prices_export_{$currentApp}_{$currentMode}_".date('Y_m_d_H_i_s').'.csv');

        // Ensure directory exists
        if (! is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }

        $file = fopen($filename, 'w');

        // CSV Headers
        fputcsv($file, [
            'ID', 'Product ID', 'Product Name', 'Amount', 'Currency', 'Type',
            'Interval', 'Interval Count', 'Active', 'Created At', 'Updated At',
        ]);

        // CSV Data
        foreach ($prices['data'] as $price) {
            fputcsv($file, [
                $price['id'],
                $price['product']['id'] ?? '',
                $price['product']['name'] ?? '',
                $price['amount'] / 100,
                $price['currency'],
                $price['type'] ?? 'one_time',
                $price['interval'] ?? '',
                $price['interval_count'] ?? '',
                $price['active'] ? 'Yes' : 'No',
                $price['created_at'],
                $price['updated_at'],
            ]);
        }

        fclose($file);

        $this->info("✅ Prices exported to: {$filename}");
        $this->line('📁 File contains '.count($prices['data']).' price records');
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
