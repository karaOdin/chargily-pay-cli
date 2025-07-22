<?php

namespace App\Exceptions;

use Exception;

class ChargilyApiException extends Exception
{
    protected array $apiResponse;

    public function __construct(string $message, int $code = 0, array $apiResponse = [], ?Exception $previous = null)
    {
        $this->apiResponse = $apiResponse;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the API response data
     */
    public function getApiResponse(): array
    {
        return $this->apiResponse;
    }

    /**
     * Check if this is a client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    /**
     * Check if this is a server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->code >= 500;
    }

    /**
     * Check if this is an authentication error
     */
    public function isAuthenticationError(): bool
    {
        return $this->code === 401;
    }

    /**
     * Check if this is a validation error
     */
    public function isValidationError(): bool
    {
        return $this->code === 422;
    }

    /**
     * Get validation errors if available
     */
    public function getValidationErrors(): array
    {
        return $this->apiResponse['errors'] ?? [];
    }

    /**
     * Get a user-friendly error message
     */
    public function getUserMessage(): string
    {
        switch ($this->code) {
            case 401:
                return 'Invalid API credentials. Please check your API key.';
            case 403:
                return 'Access forbidden. Please verify your account permissions.';
            case 404:
                return 'The requested resource was not found.';
            case 422:
                return 'Validation error: '.$this->formatValidationErrors();
            case 429:
                return 'Rate limit exceeded. Please wait and try again.';
            case 500:
                return 'Server error occurred. Please try again later.';
            case 503:
                return 'Service temporarily unavailable. Please try again later.';
            default:
                return $this->message ?: 'An unexpected error occurred.';
        }
    }

    /**
     * Format validation errors for display
     */
    protected function formatValidationErrors(): string
    {
        $errors = $this->getValidationErrors();

        if (empty($errors)) {
            return $this->message;
        }

        $formatted = [];
        foreach ($errors as $field => $messages) {
            if (is_array($messages)) {
                $formatted[] = $field.': '.implode(', ', $messages);
            } else {
                $formatted[] = $field.': '.$messages;
            }
        }

        return implode('; ', $formatted);
    }

    /**
     * Get suggested action for the user
     */
    public function getSuggestedAction(): string
    {
        switch ($this->code) {
            case 401:
                return 'Run "chargily configure" to update your API credentials.';
            case 403:
                return 'Check your account status and permissions in the Chargily dashboard.';
            case 404:
                return 'Verify the ID or URL you are trying to access.';
            case 422:
                return 'Check your input data and fix the validation errors.';
            case 429:
                return 'Wait a few minutes before making more requests.';
            case 500:
            case 503:
                return 'Try again in a few minutes. If the problem persists, contact Chargily support.';
            default:
                return 'Check the error details and try again.';
        }
    }
}
