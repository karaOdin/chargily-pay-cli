<?php

namespace App\Services;

use App\Exceptions\ChargilyApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChargilyApiService
{
    protected ConfigurationService $config;

    protected ?string $currentApplication = null;

    protected ?string $currentMode = null;

    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
        $this->currentApplication = $config->getCurrentApplication();

        // Only set mode if we have a valid application
        if (! empty($this->currentApplication)) {
            $this->currentMode = $config->getCurrentMode();
        } else {
            $this->currentMode = 'test'; // Default to test mode
        }
    }

    /**
     * Set the current application context
     */
    public function setApplication(string $application): self
    {
        $this->currentApplication = $application;
        $this->currentMode = $this->config->getCurrentMode($application);

        return $this;
    }

    /**
     * Set the current mode context
     */
    public function setMode(string $mode): self
    {
        $this->currentMode = $mode;

        return $this;
    }

    /**
     * Get current application and mode context
     */
    public function getContext(): array
    {
        return [
            'application' => $this->currentApplication,
            'mode' => $this->currentMode,
        ];
    }

    /**
     * Make an authenticated API request
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $apiKey = $this->config->getApiKey($this->currentApplication, $this->currentMode);
        $baseUrl = $this->config->getBaseUrl($this->currentMode);

        if (! $apiKey) {
            throw new ChargilyApiException("No API key configured for {$this->currentApplication} in {$this->currentMode} mode");
        }

        $url = rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');

        if (config('chargily.logging.log_api_requests')) {
            Log::info('Chargily API Request', [
                'method' => $method,
                'url' => $url,
                'application' => $this->currentApplication,
                'mode' => $this->currentMode,
                'data' => config('chargily.logging.log_sensitive_data') ? $data : '***',
            ]);
        }

        $response = Http::withToken($apiKey)
            ->timeout(config('chargily.api.timeout'))
            ->retry(
                config('chargily.api.retry_attempts'),
                config('chargily.api.retry_delay')
            )
            ->$method($url, $data);

        if (config('chargily.logging.log_api_requests')) {
            Log::info('Chargily API Response', [
                'status' => $response->status(),
                'application' => $this->currentApplication,
                'mode' => $this->currentMode,
            ]);
        }

        if (! $response->successful()) {
            $this->handleApiError($response);
        }

        return $response;
    }

    /**
     * Handle API errors
     */
    protected function handleApiError(Response $response): void
    {
        $status = $response->status();
        $body = $response->json();
        $message = $body['message'] ?? $body['error'] ?? 'Unknown API error';

        Log::error('Chargily API Error', [
            'status' => $status,
            'message' => $message,
            'application' => $this->currentApplication,
            'mode' => $this->currentMode,
            'body' => $body,
        ]);

        throw new ChargilyApiException($message, $status, $body);
    }

    /**
     * Get account balance with caching
     */
    public function getBalance(bool $fresh = false): array
    {
        $cacheKey = "chargily.balance.{$this->currentApplication}.{$this->currentMode}";

        if (! $fresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = $this->makeRequest('GET', '/balance');
        $balance = $response->json();

        // Cache the balance
        Cache::put($cacheKey, $balance, config('chargily.cache.balance_ttl'));

        // Update configuration with cached balance
        $this->config->updateBalanceCache($this->currentApplication, $this->currentMode, $balance);

        return $balance;
    }

    /**
     * Create a customer
     */
    public function createCustomer(array $data): array
    {
        $response = $this->makeRequest('POST', '/customers', $data);

        return $response->json();
    }

    /**
     * Update a customer
     */
    public function updateCustomer(string $customerId, array $data): array
    {
        $response = $this->makeRequest('POST', "/customers/{$customerId}", $data);

        return $response->json();
    }

    /**
     * Get a customer by ID
     */
    public function getCustomer(string $customerId): array
    {
        $response = $this->makeRequest('GET', "/customers/{$customerId}");

        return $response->json();
    }

    /**
     * List customers with pagination
     */
    public function listCustomers(int $perPage = 10): array
    {
        $response = $this->makeRequest('GET', "/customers?per_page={$perPage}");

        return $response->json();
    }

    /**
     * Delete a customer
     */
    public function deleteCustomer(string $customerId): array
    {
        $response = $this->makeRequest('DELETE', "/customers/{$customerId}");

        return $response->json();
    }

    /**
     * Create a product
     */
    public function createProduct(array $data): array
    {
        $response = $this->makeRequest('POST', '/products', $data);

        return $response->json();
    }

    /**
     * Update a product
     */
    public function updateProduct(string $productId, array $data): array
    {
        $response = $this->makeRequest('POST', "/products/{$productId}", $data);

        return $response->json();
    }

    /**
     * Get a product by ID
     */
    public function getProduct(string $productId): array
    {
        $response = $this->makeRequest('GET', "/products/{$productId}");

        return $response->json();
    }

    /**
     * List products with pagination
     */
    public function listProducts(int $perPage = 10): array
    {
        $response = $this->makeRequest('GET', "/products?per_page={$perPage}");

        return $response->json();
    }

    /**
     * Delete a product
     */
    public function deleteProduct(string $productId): array
    {
        $response = $this->makeRequest('DELETE', "/products/{$productId}");

        return $response->json();
    }

    /**
     * Get product prices
     */
    public function getProductPrices(string $productId, int $perPage = 10): array
    {
        $response = $this->makeRequest('GET', "/products/{$productId}/prices?per_page={$perPage}");

        return $response->json();
    }

    /**
     * Create a price
     */
    public function createPrice(array $data): array
    {
        $response = $this->makeRequest('POST', '/prices', $data);

        return $response->json();
    }

    /**
     * Update a price
     */
    public function updatePrice(string $priceId, array $data): array
    {
        $response = $this->makeRequest('POST', "/prices/{$priceId}", $data);

        return $response->json();
    }

    /**
     * Get a price by ID
     */
    public function getPrice(string $priceId): array
    {
        $response = $this->makeRequest('GET', "/prices/{$priceId}");

        return $response->json();
    }

    /**
     * List prices with pagination
     */
    public function listPrices(int $perPage = 10): array
    {
        $response = $this->makeRequest('GET', "/prices?per_page={$perPage}");

        return $response->json();
    }

    /**
     * Create a checkout
     */
    public function createCheckout(array $data): array
    {
        $response = $this->makeRequest('POST', '/checkouts', $data);

        return $response->json();
    }

    /**
     * Get a checkout by ID
     */
    public function getCheckout(string $checkoutId): array
    {
        $response = $this->makeRequest('GET', "/checkouts/{$checkoutId}");

        return $response->json();
    }

    /**
     * List checkouts with pagination
     */
    public function listCheckouts(int $perPage = 10): array
    {
        $response = $this->makeRequest('GET', "/checkouts?per_page={$perPage}");

        return $response->json();
    }

    /**
     * Get checkout items
     */
    public function getCheckoutItems(string $checkoutId, int $perPage = 10): array
    {
        $response = $this->makeRequest('GET', "/checkouts/{$checkoutId}/items?per_page={$perPage}");

        return $response->json();
    }

    /**
     * Expire a checkout
     */
    public function expireCheckout(string $checkoutId): array
    {
        $response = $this->makeRequest('POST', "/checkouts/{$checkoutId}/expire");

        return $response->json();
    }

    /**
     * Create a payment link
     */
    public function createPaymentLink(array $data): array
    {
        $response = $this->makeRequest('POST', '/payment-links', $data);

        return $response->json();
    }

    /**
     * Update a payment link
     */
    public function updatePaymentLink(string $paymentLinkId, array $data): array
    {
        $response = $this->makeRequest('POST', "/payment-links/{$paymentLinkId}", $data);

        return $response->json();
    }

    /**
     * Get a payment link by ID
     */
    public function getPaymentLink(string $paymentLinkId): array
    {
        $response = $this->makeRequest('GET', "/payment-links/{$paymentLinkId}");

        return $response->json();
    }

    /**
     * List payment links with pagination
     */
    public function listPaymentLinks(int $perPage = 10): array
    {
        $response = $this->makeRequest('GET', "/payment-links?per_page={$perPage}");

        return $response->json();
    }

    /**
     * Get payment link items
     */
    public function getPaymentLinkItems(string $paymentLinkId, int $perPage = 10): array
    {
        $response = $this->makeRequest('GET', "/payment-links/{$paymentLinkId}/items?per_page={$perPage}");

        return $response->json();
    }

    /**
     * Get checkouts with filtering (alias for listCheckouts with parameters)
     */
    public function getCheckouts(array $filters = []): array
    {
        $queryParams = [];

        if (isset($filters['per_page'])) {
            $queryParams['per_page'] = $filters['per_page'];
        }

        if (isset($filters['status'])) {
            $queryParams['status'] = $filters['status'];
        }

        if (isset($filters['customer_id'])) {
            $queryParams['customer_id'] = $filters['customer_id'];
        }

        if (isset($filters['created_at_from'])) {
            $queryParams['created_at_from'] = $filters['created_at_from'];
        }

        if (isset($filters['created_at_to'])) {
            $queryParams['created_at_to'] = $filters['created_at_to'];
        }

        $query = empty($queryParams) ? '' : '?'.http_build_query($queryParams);

        $response = $this->makeRequest('GET', "/checkouts{$query}");

        return $response->json();
    }

    /**
     * Get customers with filtering (alias for listCustomers with parameters)
     */
    public function getCustomers(array $filters = []): array
    {
        $queryParams = [];

        if (isset($filters['per_page'])) {
            $queryParams['per_page'] = $filters['per_page'];
        }

        if (isset($filters['search'])) {
            $queryParams['search'] = $filters['search'];
        }

        $query = empty($queryParams) ? '' : '?'.http_build_query($queryParams);

        $response = $this->makeRequest('GET', "/customers{$query}");

        return $response->json();
    }

    /**
     * Get products with filtering (alias for listProducts with parameters)
     */
    public function getProducts(array $filters = []): array
    {
        $queryParams = [];

        if (isset($filters['per_page'])) {
            $queryParams['per_page'] = $filters['per_page'];
        }

        if (isset($filters['active'])) {
            $queryParams['active'] = $filters['active'] ? 'true' : 'false';
        }

        $query = empty($queryParams) ? '' : '?'.http_build_query($queryParams);

        $response = $this->makeRequest('GET', "/products{$query}");

        return $response->json();
    }

    /**
     * Get prices with filtering (alias for listPrices with parameters)
     */
    public function getPrices(array $filters = []): array
    {
        $queryParams = [];

        if (isset($filters['per_page'])) {
            $queryParams['per_page'] = $filters['per_page'];
        }

        if (isset($filters['active'])) {
            $queryParams['active'] = $filters['active'] ? 'true' : 'false';
        }

        if (isset($filters['product_id'])) {
            $queryParams['product_id'] = $filters['product_id'];
        }

        $query = empty($queryParams) ? '' : '?'.http_build_query($queryParams);

        $response = $this->makeRequest('GET', "/prices{$query}");

        return $response->json();
    }

    /**
     * Get payment links with filtering (alias for listPaymentLinks with parameters)
     */
    public function getPaymentLinks(array $filters = []): array
    {
        $queryParams = [];

        if (isset($filters['per_page'])) {
            $queryParams['per_page'] = $filters['per_page'];
        }

        if (isset($filters['active'])) {
            $queryParams['active'] = $filters['active'] ? 'true' : 'false';
        }

        $query = empty($queryParams) ? '' : '?'.http_build_query($queryParams);

        $response = $this->makeRequest('GET', "/payment-links{$query}");

        return $response->json();
    }

    /**
     * Delete a price
     */
    public function deletePrice(string $priceId): array
    {
        $response = $this->makeRequest('DELETE', "/prices/{$priceId}");

        return $response->json();
    }

    /**
     * Delete a payment link
     */
    public function deletePaymentLink(string $paymentLinkId): array
    {
        $response = $this->makeRequest('DELETE', "/payment-links/{$paymentLinkId}");

        return $response->json();
    }

    /**
     * Get payment link analytics
     */
    public function getPaymentLinkAnalytics(string $paymentLinkId): array
    {
        $response = $this->makeRequest('GET', "/payment-links/{$paymentLinkId}/analytics");

        return $response->json();
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $balance = $this->getBalance(true);

            return [
                'success' => true,
                'message' => 'API connection successful',
                'data' => $balance,
            ];
        } catch (ChargilyApiException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }
}
