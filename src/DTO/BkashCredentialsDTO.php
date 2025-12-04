<?php

namespace Ihasan\Bkash\DTO;

readonly class BkashCredentialsDTO extends BaseDTO
{
    public function __construct(
        public string $username,
        public string $password,
        public string $appKey,
        public string $appSecret,
        public string $baseUrl,
        public bool $sandbox
    ) {}

    public static function fromConfig(): static
    {
        // Support both Laravel config() and Bagisto core() functions
        $getConfig = function (string $key) {
            if (function_exists('core')) {
                return core()->getConfigData($key);
            }
            return config($key);
        };
        
        $sandbox = $getConfig('sales.payment_methods.bkash.bkash_sandbox') === '1';
        
        return new static(
            username: $getConfig('sales.payment_methods.bkash.bkash_username') ?? '',
            password: $getConfig('sales.payment_methods.bkash.bkash_password') ?? '',
            appKey: $getConfig('sales.payment_methods.bkash.bkash_app_key') ?? '',
            appSecret: $getConfig('sales.payment_methods.bkash.bkash_app_secret') ?? '',
            baseUrl: $sandbox
                ? $getConfig('sales.payment_methods.bkash.sandbox_base_url') ?? ''
                : $getConfig('sales.payment_methods.bkash.live_base_url') ?? '',
            sandbox: $sandbox
        );
    }

    public function validate(): array
    {
        $missing = [];
        
        if (empty($this->username)) $missing[] = 'username';
        if (empty($this->password)) $missing[] = 'password';
        if (empty($this->appKey)) $missing[] = 'app_key';
        if (empty($this->appSecret)) $missing[] = 'app_secret';
        if (empty($this->baseUrl)) $missing[] = 'base_url';
        
        return $missing;
    }

    public function isValid(): bool
    {
        return empty($this->validate());
    }
}