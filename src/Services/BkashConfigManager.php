<?php

namespace Webkul\BkashPayment\Services;

use Illuminate\Support\Facades\Config;
use Webkul\BkashPayment\Exceptions\ConfigurationException;

class BkashConfigManager
{
    /**
     * Set bKash configuration from admin settings
     */
    public function setConfiguration(): void
    {
        $sandbox = (bool) core()->getConfigData('sales.payment_methods.bkash_payment.sandbox');

        // Set the configuration
        Config::set('bkash.sandbox', $sandbox);
        Config::set('bkash.credentials.app_key', core()->getConfigData('sales.payment_methods.bkash_payment.app_key'));
        Config::set('bkash.credentials.app_secret', core()->getConfigData('sales.payment_methods.bkash_payment.app_secret'));
        Config::set('bkash.credentials.username', core()->getConfigData('sales.payment_methods.bkash_payment.username'));
        Config::set('bkash.credentials.password', core()->getConfigData('sales.payment_methods.bkash_payment.password'));

        // Get the base URLs from config or use default ones
        $sandboxBaseUrl = core()->getConfigData('sales.payment_methods.bkash_payment.sandbox_base_url') ?: 'https://tokenized.sandbox.bka.sh';
        $liveBaseUrl = core()->getConfigData('sales.payment_methods.bkash_payment.live_base_url') ?: 'https://tokenized.pay.bka.sh';
        
        Config::set('bkash.sandbox_base_url', $sandboxBaseUrl);
        Config::set('bkash.live_base_url', $liveBaseUrl);
        Config::set('bkash.version', 'v1.2.0-beta');
        
        // Set callback URLs
        Config::set('bkash.success_url', route('bkash.payment.success'));
        Config::set('bkash.fail_url', route('bkash.payment.fail'));
        Config::set('bkash.cancel_url', route('bkash.payment.cancel'));

        // Set custom callback URL if configured
        $customSuccessUrl = core()->getConfigData('sales.payment_methods.bkash_payment.success_url');
        $customFailUrl = core()->getConfigData('sales.payment_methods.bkash_payment.fail_url');

        if ($customSuccessUrl) {
            Config::set('bkash.success_url', $customSuccessUrl);
        }

        if ($customFailUrl) {
            Config::set('bkash.fail_url', $customFailUrl);
        }
        
        // Set other bKash configuration
        Config::set('bkash.cache.token_lifetime', 3600); // 1 hour in seconds
        Config::set('bkash.default_currency', 'BDT');
        Config::set('bkash.default_intent', 'sale');
    }

    /**
     * Validate bKash configuration
     *
     * @throws ConfigurationException
     */
    public function validateConfiguration(): bool
    {
        $requiredConfigFields = [
            'sales.payment_methods.bkash_payment.app_key'    => 'App Key',
            'sales.payment_methods.bkash_payment.app_secret' => 'App Secret',
            'sales.payment_methods.bkash_payment.username'   => 'Username',
            'sales.payment_methods.bkash_payment.password'   => 'Password',
        ];

        $missingFields = [];

        foreach ($requiredConfigFields as $field => $label) {
            if (! core()->getConfigData($field)) {
                $missingFields[] = $label;
            }
        }

        if (count($missingFields) > 0) {
            throw new ConfigurationException('Missing bKash configuration: '.implode(', ', $missingFields));
        }

        return true;
    }
}
