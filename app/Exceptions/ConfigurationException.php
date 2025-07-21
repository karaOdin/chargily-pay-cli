<?php

namespace App\Exceptions;

use Exception;

class ConfigurationException extends Exception
{
    /**
     * Get a user-friendly error message
     */
    public function getUserMessage(): string
    {
        return $this->message;
    }

    /**
     * Get suggested action for the user
     */
    public function getSuggestedAction(): string
    {
        if (str_contains($this->message, 'does not exist')) {
            return 'Check the application name or create the application first.';
        }

        if (str_contains($this->message, 'already exists')) {
            return 'Choose a different name or update the existing application.';
        }

        if (str_contains($this->message, 'API key')) {
            return 'Run "chargily configure" to set up your API credentials.';
        }

        return 'Check your configuration and try again.';
    }
}