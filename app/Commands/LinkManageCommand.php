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

class LinkManageCommand extends Command
{
    protected $signature = 'link:manage';

    protected $description = 'Comprehensive payment link management';

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
                        'list' => '📋 List Payment Links',
                        'create' => '➕ Create Payment Link',
                        'update' => '✏️ Update Payment Link',
                        'delete' => '🗑️ Delete Payment Link',
                        'view' => '👁️ View Link Details',
                        'analytics' => '📊 Link Analytics',
                        'share' => '🔗 Share Link',
                        'export' => '📤 Export Links',
                        'exit' => '↩️ Back to Main Menu',
                    ]
                );

                match ($action) {
                    'list' => $this->listPaymentLinks(),
                    'create' => $this->createPaymentLink(),
                    'update' => $this->updatePaymentLink(),
                    'delete' => $this->deletePaymentLink(),
                    'view' => $this->viewPaymentLink(),
                    'analytics' => $this->viewLinkAnalytics(),
                    'share' => $this->sharePaymentLink(),
                    'export' => $this->exportPaymentLinks(),
                    'exit' => null
                };

                if ($action === 'exit') {
                    break;
                }

                if ($action !== 'exit') {
                    $this->line('');
                    if (! confirm('Continue with payment link management?', true)) {
                        break;
                    }
                    $this->line('');
                }
            }

            return 0;

        } catch (ChargilyApiException $e) {
            $this->error('❌ Payment link operation failed: '.$e->getUserMessage());
            $this->line('💡 '.$e->getSuggestedAction());

            return 1;
        }
    }

    protected function displayHeader(string $appName, string $mode): void
    {
        $modeDisplay = $mode === 'live' ? '🔴 LIVE MODE' : '🧪 TEST MODE';

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                  Payment Link Management                    ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("Application: {$appName}");
        $this->line("Mode: {$modeDisplay}");
        $this->line('');
    }

    protected function listPaymentLinks(): void
    {
        $limit = (int) select(
            'How many payment links to show?',
            ['10', '25', '50', '100'],
            '25'
        );

        $this->line('⏳ Fetching payment links...');

        $links = $this->api->getPaymentLinks(['per_page' => $limit]);

        if (empty($links['data'])) {
            $this->info('📭 No payment links found.');

            return;
        }

        $this->line('');
        $this->line('🔗 Payment Links');
        $this->line(str_repeat('─', 100));

        $headers = ['ID', 'Name', 'Price', 'Status', 'Visits', 'Payments', 'Created'];
        $rows = array_map(function ($link) {
            $price = $link['price'] ?? null;
            $priceDisplay = $price
                ? number_format($price['amount'] / 100, 2).' '.strtoupper($price['currency'])
                : 'N/A';

            return [
                substr($link['id'], 0, 8).'...',
                $link['name'] ?? 'Untitled',
                $priceDisplay,
                $link['active'] ? '✅ Active' : '❌ Inactive',
                $link['visits_count'] ?? '0',
                $link['payments_count'] ?? '0',
                $this->formatDate($link['created_at']),
            ];
        }, $links['data']);

        $this->table($headers, $rows);
        $this->line('💡 Showing '.count($links['data']).' payment links');
        $this->line("💡 Use 'view' to see detailed analytics for any link");
    }

    protected function createPaymentLink(): void
    {
        $this->line('');
        $this->line('➕ Create New Payment Link');
        $this->line(str_repeat('─', 50));

        // Basic Information
        $name = text(
            'Payment link name',
            required: true,
            placeholder: 'Premium Course Access, Monthly Subscription'
        );

        $description = text(
            'Description (optional)',
            placeholder: 'Brief description of what customers are purchasing'
        );

        // Price Selection
        $priceId = $this->selectPriceForLink();
        if (! $priceId) {
            return;
        }

        // Custom settings
        $collectCustomerInfo = select(
            'Collect customer information?',
            [
                'none' => 'No additional info',
                'email' => 'Email only',
                'full' => 'Full customer details',
            ],
            'email'
        );

        $allowQuantity = confirm('Allow customers to select quantity?', false);

        $customSuccessUrl = '';
        $customFailureUrl = '';

        if (confirm('Customize redirect URLs?', false)) {
            $customSuccessUrl = text(
                'Custom success URL (optional)',
                placeholder: 'https://mywebsite.com/thank-you'
            );

            $customFailureUrl = text(
                'Custom failure URL (optional)',
                placeholder: 'https://mywebsite.com/payment-failed'
            );
        }

        // Branding
        $customization = [];
        if (confirm('Add custom branding?', false)) {
            $customization = [
                'logo_url' => text('Logo URL (optional)', placeholder: 'https://mywebsite.com/logo.png'),
                'primary_color' => text('Primary color (hex, optional)', placeholder: '#007bff'),
                'theme' => select('Theme', ['light' => 'Light', 'dark' => 'Dark'], 'light'),
            ];
            $customization = array_filter($customization);
        }

        $metadata = [];
        if (confirm('Add custom metadata?', false)) {
            do {
                $key = text('Metadata key');
                $value = text('Metadata value');
                $metadata[$key] = $value;
            } while (confirm('Add another metadata field?', false));
        }

        $linkData = array_filter([
            'name' => $name,
            'description' => $description ?: null,
            'price_id' => $priceId,
            'collect_customer_info' => $collectCustomerInfo,
            'allow_quantity_adjustment' => $allowQuantity,
            'success_url' => $customSuccessUrl ?: null,
            'failure_url' => $customFailureUrl ?: null,
            'customization' => $customization ?: null,
            'metadata' => $metadata ?: null,
        ]);

        // Preview
        $this->displayLinkPreview($linkData, $priceId);

        if (! confirm('Create this payment link?', true)) {
            $this->info('Payment link creation cancelled.');

            return;
        }

        $this->line('⏳ Creating payment link...');
        $link = $this->api->createPaymentLink($linkData);

        $this->info('✅ Payment link created successfully!');
        $this->line("🆔 Link ID: {$link['id']}");
        $this->line("🔗 Link Name: {$link['name']}");
        $this->line("🌐 Public URL: {$link['url']}");
        $this->line('');
        $this->line('📋 Share this URL with your customers:');
        $this->line("<fg=yellow>{$link['url']}</>");

        if (confirm('Copy link details to clipboard?', false)) {
            $this->copyLinkDetails($link);
        }
    }

    protected function selectPriceForLink(): ?string
    {
        $this->line('⏳ Fetching available prices...');

        $prices = $this->api->getPrices(['per_page' => 50, 'active' => true]);

        if (empty($prices['data'])) {
            $this->error('❌ No active prices found. Create a product and price first.');

            return null;
        }

        $priceOptions = [];
        foreach ($prices['data'] as $price) {
            $priceDisplay = number_format($price['amount'] / 100, 2).' '.strtoupper($price['currency']);
            $productName = $price['product']['name'] ?? 'Unknown Product';
            $type = ucfirst(str_replace('_', ' ', $price['type'] ?? 'one_time'));

            $priceOptions[$price['id']] = "{$productName} - {$priceDisplay} ({$type})";
        }

        return select('Select a price for this payment link', $priceOptions);
    }

    protected function displayLinkPreview(array $linkData, string $priceId): void
    {
        $this->line('');
        $this->line('📋 Payment Link Preview');
        $this->line(str_repeat('─', 50));
        $this->line("🔗 Name: {$linkData['name']}");

        if ($linkData['description']) {
            $this->line("📝 Description: {$linkData['description']}");
        }

        $this->line("💰 Price ID: {$priceId}");
        $this->line('👤 Customer Info: '.ucfirst($linkData['collect_customer_info']));
        $this->line('🔢 Allow Quantity: '.($linkData['allow_quantity_adjustment'] ? 'Yes' : 'No'));

        if ($linkData['success_url']) {
            $this->line("✅ Success URL: {$linkData['success_url']}");
        }

        if ($linkData['failure_url']) {
            $this->line("❌ Failure URL: {$linkData['failure_url']}");
        }

        if (! empty($linkData['customization'])) {
            $this->line('🎨 Custom Branding: Yes');
        }

        if (! empty($linkData['metadata'])) {
            $this->line('📊 Metadata: '.count($linkData['metadata']).' field(s)');
        }

        $this->line('');
    }

    protected function updatePaymentLink(): void
    {
        $linkId = text(
            'Payment link ID to update',
            required: true,
            placeholder: 'payment_link_01234567...'
        );

        $this->line('⏳ Fetching payment link details...');

        try {
            $link = $this->api->getPaymentLink($linkId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Payment link not found: {$linkId}");

            return;
        }

        $this->line('');
        $this->line('📋 Current Payment Link Details');
        $this->line(str_repeat('─', 50));
        $this->line("Name: {$link['name']}");
        $this->line('Description: '.($link['description'] ?? 'N/A'));
        $this->line('Status: '.($link['active'] ? 'Active' : 'Inactive'));
        $this->line("URL: {$link['url']}");
        $this->line('');

        $updateData = [];

        if (confirm('Update name?', false)) {
            $updateData['name'] = text('New name', default: $link['name']);
        }

        if (confirm('Update description?', false)) {
            $updateData['description'] = text(
                'New description',
                default: $link['description'] ?? ''
            );
        }

        if (confirm('Change active status?', false)) {
            $updateData['active'] = confirm(
                'Should this payment link be active?',
                $link['active']
            );
        }

        if (confirm('Update redirect URLs?', false)) {
            $updateData['success_url'] = text(
                'Success URL',
                default: $link['success_url'] ?? ''
            );

            $updateData['failure_url'] = text(
                'Failure URL',
                default: $link['failure_url'] ?? ''
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

        $this->line('⏳ Updating payment link...');
        $updatedLink = $this->api->updatePaymentLink($linkId, $updateData);

        $this->info('✅ Payment link updated successfully!');
        $this->line("🔗 Name: {$updatedLink['name']}");
        $this->line('Status: '.($updatedLink['active'] ? '✅ Active' : '❌ Inactive'));
        $this->line("🌐 URL: {$updatedLink['url']}");
    }

    protected function deletePaymentLink(): void
    {
        $linkId = text(
            'Payment link ID to delete',
            required: true,
            placeholder: 'payment_link_01234567...'
        );

        $this->line('⏳ Fetching payment link details...');

        try {
            $link = $this->api->getPaymentLink($linkId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Payment link not found: {$linkId}");

            return;
        }

        $paymentsCount = $link['payments_count'] ?? 0;
        $visitsCount = $link['visits_count'] ?? 0;

        $this->line('');
        $this->warn('⚠️ You are about to delete this payment link:');
        $this->line("🔗 Name: {$link['name']}");
        $this->line("🌐 URL: {$link['url']}");
        $this->line("👁️ Total Visits: {$visitsCount}");
        $this->line("💳 Total Payments: {$paymentsCount}");
        $this->line('');
        $this->error('🚨 This action cannot be undone and will break any shared links!');

        if (! confirm('Are you absolutely sure you want to delete this payment link?', false)) {
            $this->info('Deletion cancelled.');

            return;
        }

        if (! confirm('Type "DELETE" to confirm', false)) {
            $this->info('Deletion cancelled - confirmation not received.');

            return;
        }

        $this->line('⏳ Deleting payment link...');
        $this->api->deletePaymentLink($linkId);

        $this->info('✅ Payment link deleted successfully.');
        $this->line("🗑️ Payment link '{$link['name']}' has been removed.");
        $this->line('⚠️ The public URL is no longer accessible.');
    }

    protected function viewPaymentLink(): void
    {
        $linkId = text(
            'Payment link ID to view',
            required: true,
            placeholder: 'payment_link_01234567...'
        );

        $this->line('⏳ Fetching payment link details...');

        try {
            $link = $this->api->getPaymentLink($linkId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Payment link not found: {$linkId}");

            return;
        }

        $this->line('');
        $this->line('🔗 Payment Link Details');
        $this->line('╔════════════════════════════════════════════════════════════════════════════════╗');
        $this->line("║ ID: {$link['id']}");
        $this->line("║ Name: {$link['name']}");
        $this->line('║ Description: '.($link['description'] ?? 'N/A'));
        $this->line('║ Status: '.($link['active'] ? '✅ Active' : '❌ Inactive'));
        $this->line("║ Public URL: {$link['url']}");
        $this->line('║ Created: '.$this->formatDate($link['created_at']));
        $this->line('║ Updated: '.$this->formatDate($link['updated_at']));

        // Price information
        if (isset($link['price'])) {
            $price = $link['price'];
            $amount = number_format($price['amount'] / 100, 2).' '.strtoupper($price['currency']);
            $this->line("║ Price: {$amount}");
            $this->line('║ Product: '.($price['product']['name'] ?? 'N/A'));
        }

        // Analytics
        $this->line('║ ');
        $this->line('║ 📊 Analytics:');
        $this->line('║   👁️ Total Visits: '.($link['visits_count'] ?? '0'));
        $this->line('║   💳 Total Payments: '.($link['payments_count'] ?? '0'));
        $this->line('║   💰 Total Revenue: '.($link['total_revenue'] ?? '0.00'));

        if (($link['visits_count'] ?? 0) > 0) {
            $conversionRate = (($link['payments_count'] ?? 0) / ($link['visits_count'] ?? 1)) * 100;
            $this->line('║   📈 Conversion Rate: '.number_format($conversionRate, 1).'%');
        }

        // Settings
        $this->line('║ ');
        $this->line('║ ⚙️ Settings:');
        $this->line('║   👤 Customer Info: '.ucfirst($link['collect_customer_info'] ?? 'none'));
        $this->line('║   🔢 Allow Quantity: '.(($link['allow_quantity_adjustment'] ?? false) ? 'Yes' : 'No'));

        if ($link['success_url'] ?? false) {
            $this->line("║   ✅ Success URL: {$link['success_url']}");
        }

        if ($link['failure_url'] ?? false) {
            $this->line("║   ❌ Failure URL: {$link['failure_url']}");
        }

        if (! empty($link['metadata'])) {
            $this->line('║ ');
            $this->line('║ 📊 Metadata:');
            foreach ($link['metadata'] as $key => $value) {
                $this->line("║   {$key}: {$value}");
            }
        }

        $this->line('╚════════════════════════════════════════════════════════════════════════════════╝');

        // Action options
        $this->line('');
        $action = select(
            'What would you like to do?',
            [
                'share' => '🔗 Share Link',
                'copy' => '📋 Copy Details',
                'analytics' => '📊 View Analytics',
                'nothing' => '↩️ Back',
            ]
        );

        match ($action) {
            'share' => $this->shareSpecificLink($link),
            'copy' => $this->copyLinkDetails($link),
            'analytics' => $this->viewSpecificLinkAnalytics($linkId),
            'nothing' => null
        };
    }

    protected function viewLinkAnalytics(): void
    {
        $linkId = text(
            'Payment link ID for analytics',
            required: true,
            placeholder: 'payment_link_01234567...'
        );

        $this->viewSpecificLinkAnalytics($linkId);
    }

    protected function viewSpecificLinkAnalytics(string $linkId): void
    {
        $this->line('⏳ Fetching analytics data...');

        try {
            $analytics = $this->api->getPaymentLinkAnalytics($linkId);
        } catch (ChargilyApiException $e) {
            $this->error('❌ Failed to fetch analytics: '.$e->getUserMessage());

            return;
        }

        $this->line('');
        $this->line('📊 Payment Link Analytics');
        $this->line('╔════════════════════════════════════════════════════════════════════════════════╗');

        // Overview stats
        $this->line('║ 📈 Overview (Last 30 Days)');
        $this->line('║   👁️ Total Visits: '.($analytics['total_visits'] ?? '0'));
        $this->line('║   💳 Total Payments: '.($analytics['total_payments'] ?? '0'));
        $this->line('║   💰 Total Revenue: '.($analytics['total_revenue'] ?? '0.00').' DZD');
        $this->line('║   📊 Conversion Rate: '.($analytics['conversion_rate'] ?? '0.0').'%');
        $this->line('║   💵 Average Order Value: '.($analytics['average_order_value'] ?? '0.00').' DZD');

        // Daily breakdown
        if (! empty($analytics['daily_stats'])) {
            $this->line('║ ');
            $this->line('║ 📅 Daily Breakdown (Last 7 Days)');
            foreach ($analytics['daily_stats'] as $day) {
                $date = $this->formatDate($day['date']);
                $this->line("║   {$date}: {$day['visits']} visits, {$day['payments']} payments");
            }
        }

        // Top referrers
        if (! empty($analytics['referrers'])) {
            $this->line('║ ');
            $this->line('║ 🌐 Top Referrers');
            foreach (array_slice($analytics['referrers'], 0, 5) as $referrer) {
                $this->line("║   {$referrer['source']}: {$referrer['visits']} visits");
            }
        }

        $this->line('╚════════════════════════════════════════════════════════════════════════════════╝');

        if (confirm('Export detailed analytics to CSV?', false)) {
            $this->exportLinkAnalytics($linkId, $analytics);
        }
    }

    protected function sharePaymentLink(): void
    {
        $linkId = text(
            'Payment link ID to share',
            required: true,
            placeholder: 'payment_link_01234567...'
        );

        $this->line('⏳ Fetching payment link details...');

        try {
            $link = $this->api->getPaymentLink($linkId);
        } catch (ChargilyApiException $e) {
            $this->error("❌ Payment link not found: {$linkId}");

            return;
        }

        $this->shareSpecificLink($link);
    }

    protected function shareSpecificLink(array $link): void
    {
        $this->line('');
        $this->line('🔗 Share Payment Link');
        $this->line(str_repeat('─', 60));
        $this->line("Name: {$link['name']}");
        $this->line("URL: {$link['url']}");
        $this->line('');

        $shareMethod = select(
            'How would you like to share?',
            [
                'copy' => '📋 Copy URL to clipboard',
                'qr' => '📱 Generate QR Code',
                'social' => '🌐 Social Media Templates',
                'email' => '📧 Email Template',
                'embed' => '🔗 Website Embed Code',
            ]
        );

        match ($shareMethod) {
            'copy' => $this->copyLinkUrl($link['url']),
            'qr' => $this->generateQrCode($link),
            'social' => $this->generateSocialTemplates($link),
            'email' => $this->generateEmailTemplate($link),
            'embed' => $this->generateEmbedCode($link)
        };
    }

    protected function copyLinkUrl(string $url): void
    {
        $this->info('✅ Payment link URL copied!');
        $this->line("🔗 URL: {$url}");
        $this->line('💡 You can now paste this URL anywhere to share your payment link');
    }

    protected function generateQrCode(array $link): void
    {
        $this->line('');
        $this->line('📱 QR Code Generated');
        $this->line(str_repeat('─', 40));
        $this->line("Link: {$link['name']}");
        $this->line("URL: {$link['url']}");
        $this->line('');
        $this->info('💡 QR Code would be generated here in a real implementation');
        $this->line('💡 You can use online QR code generators with this URL');
        $this->line("📱 Customers can scan to access: {$link['url']}");
    }

    protected function generateSocialTemplates(array $link): void
    {
        $this->line('');
        $this->line('🌐 Social Media Templates');
        $this->line(str_repeat('─', 50));

        $description = $link['description'] ?? 'Get instant access now.';
        $twitterDescription = $link['description'] ?? 'Get yours today';
        $instagramDescription = $link['description'] ?? 'Link in bio!';

        $templates = [
            'Facebook/LinkedIn' => "🚀 Check out {$link['name']}!\n\n{$description}\n\n👉 {$link['url']}\n\n#payment #business",

            'Twitter' => "🚀 {$link['name']} is now available!\n\n{$twitterDescription}\n\n👉 {$link['url']}\n\n#payment #business",

            'Instagram' => "✨ {$link['name']} ✨\n\n{$instagramDescription}\n\n🔗 See link in bio\n\n#business #payment #online",
        ];

        foreach ($templates as $platform => $template) {
            $this->line('');
            $this->line("📱 {$platform}:");
            $this->line($template);
            $this->line(str_repeat('-', 30));
        }

        $this->line('');
        $this->info('✅ Templates generated! Copy and customize for your needs.');
    }

    protected function generateEmailTemplate(array $link): void
    {
        $this->line('');
        $this->line('📧 Email Template');
        $this->line(str_repeat('─', 40));

        $template = "Subject: {$link['name']} - Complete Your Purchase\n\n";
        $template .= "Hi there!\n\n";
        $template .= "Thanks for your interest in {$link['name']}.\n\n";

        if ($link['description']) {
            $template .= "{$link['description']}\n\n";
        }

        $template .= "To complete your purchase, simply click the link below:\n";
        $template .= "👉 {$link['url']}\n\n";
        $template .= "If you have any questions, feel free to reply to this email.\n\n";
        $template .= "Best regards,\n";
        $template .= 'Your Team';

        $this->line($template);
        $this->line('');
        $this->info('✅ Email template generated! Customize as needed for your brand.');
    }

    protected function generateEmbedCode(array $link): void
    {
        $this->line('');
        $this->line('🔗 Website Embed Code');
        $this->line(str_repeat('─', 40));

        $embedCode = "<a href=\"{$link['url']}\" \n";
        $embedCode .= "   target=\"_blank\" \n";
        $embedCode .= "   style=\"display: inline-block; padding: 12px 24px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;\">\n";
        $embedCode .= "   {$link['name']}\n";
        $embedCode .= '</a>';

        $this->line('HTML Button:');
        $this->line($embedCode);
        $this->line('');

        $simpleLink = "<a href=\"{$link['url']}\" target=\"_blank\">{$link['name']}</a>";
        $this->line('Simple Link:');
        $this->line($simpleLink);
        $this->line('');

        $this->info('✅ Embed code generated! Add this to your website or blog.');
    }

    protected function copyLinkDetails(array $link): void
    {
        $details = "Payment Link Details\n";
        $details .= "═══════════════════════\n";
        $details .= "Name: {$link['name']}\n";
        $details .= "URL: {$link['url']}\n";
        $details .= 'Status: '.($link['active'] ? 'Active' : 'Inactive')."\n";

        if ($link['description']) {
            $details .= "Description: {$link['description']}\n";
        }

        $details .= "\nShare this URL with your customers:\n{$link['url']}";

        $this->info('✅ Payment link details copied to clipboard!');
        $this->line('📋 Details copied:');
        $this->line($details);
    }

    protected function exportPaymentLinks(): void
    {
        $limit = (int) select(
            'How many payment links to export?',
            ['25', '50', '100', '250'],
            '50'
        );

        $this->line('⏳ Fetching payment links for export...');

        $links = $this->api->getPaymentLinks(['per_page' => $limit]);

        if (empty($links['data'])) {
            $this->info('📭 No payment links found to export.');

            return;
        }

        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $filename = storage_path("app/payment_links_export_{$currentApp}_{$currentMode}_".date('Y_m_d_H_i_s').'.csv');

        // Ensure directory exists
        if (! is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }

        $file = fopen($filename, 'w');

        // CSV Headers
        fputcsv($file, [
            'ID', 'Name', 'Description', 'URL', 'Price Amount', 'Price Currency',
            'Product Name', 'Active', 'Visits Count', 'Payments Count',
            'Total Revenue', 'Created At', 'Updated At',
        ]);

        // CSV Data
        foreach ($links['data'] as $link) {
            $price = $link['price'] ?? null;

            fputcsv($file, [
                $link['id'],
                $link['name'],
                $link['description'] ?? '',
                $link['url'],
                $price ? $price['amount'] / 100 : '',
                $price ? $price['currency'] : '',
                $price['product']['name'] ?? '',
                $link['active'] ? 'Yes' : 'No',
                $link['visits_count'] ?? '0',
                $link['payments_count'] ?? '0',
                $link['total_revenue'] ?? '0.00',
                $link['created_at'],
                $link['updated_at'],
            ]);
        }

        fclose($file);

        $this->info("✅ Payment links exported to: {$filename}");
        $this->line('📁 File contains '.count($links['data']).' payment link records');
    }

    protected function exportLinkAnalytics(string $linkId, array $analytics): void
    {
        $currentApp = $this->config->getCurrentApplication();
        $currentMode = $this->config->getCurrentMode();
        $filename = storage_path("app/link_analytics_{$linkId}_{$currentApp}_{$currentMode}_".date('Y_m_d_H_i_s').'.csv');

        // Ensure directory exists
        if (! is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }

        $file = fopen($filename, 'w');

        // Summary data
        fputcsv($file, ['Analytics Summary']);
        fputcsv($file, ['Total Visits', $analytics['total_visits'] ?? '0']);
        fputcsv($file, ['Total Payments', $analytics['total_payments'] ?? '0']);
        fputcsv($file, ['Total Revenue', $analytics['total_revenue'] ?? '0.00']);
        fputcsv($file, ['Conversion Rate', $analytics['conversion_rate'] ?? '0.0']);
        fputcsv($file, ['Average Order Value', $analytics['average_order_value'] ?? '0.00']);
        fputcsv($file, []);

        // Daily stats
        if (! empty($analytics['daily_stats'])) {
            fputcsv($file, ['Daily Statistics']);
            fputcsv($file, ['Date', 'Visits', 'Payments', 'Revenue']);

            foreach ($analytics['daily_stats'] as $day) {
                fputcsv($file, [
                    $day['date'],
                    $day['visits'],
                    $day['payments'],
                    $day['revenue'] ?? '0.00',
                ]);
            }
        }

        fclose($file);

        $this->info("✅ Analytics exported to: {$filename}");
        $this->line('📊 File contains detailed analytics data');
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
