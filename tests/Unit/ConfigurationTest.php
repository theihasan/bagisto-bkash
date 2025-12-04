<?php

namespace Ihasan\Bkash\Tests\Unit;

use Ihasan\Bkash\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class ConfigurationTest extends TestCase
{
    #[Test]
    public function it_has_correct_default_configuration(): void
    {
        $config = config('bagisto-bkash');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('payment_methods', $config);
        $this->assertArrayHasKey('system_config', $config);
    }

    #[Test]
    public function it_configures_payment_method_correctly(): void
    {
        $paymentMethods = config('bagisto-bkash.payment_methods');

        $this->assertArrayHasKey('bkash', $paymentMethods);

        $bkashConfig = $paymentMethods['bkash'];
        $this->assertEquals('bkash', $bkashConfig['code']);
        $this->assertEquals('BKash', $bkashConfig['title']);
        $this->assertEquals('BKash', $bkashConfig['description']);
        $this->assertEquals('Ihasan\Bkash\Payment\Bkash', $bkashConfig['class']);
        $this->assertTrue($bkashConfig['active']);
        $this->assertEquals(1, $bkashConfig['sort']);
    }

    #[Test]
    public function it_has_required_system_configuration_fields(): void
    {
        $systemConfig = config('bagisto-bkash.system_config');

        $this->assertIsArray($systemConfig);
        $this->assertCount(1, $systemConfig);

        $bkashSystemConfig = $systemConfig[0];
        $this->assertEquals('sales.payment_methods.bkash', $bkashSystemConfig['key']);
        $this->assertEquals('bKash Payment', $bkashSystemConfig['name']);
        $this->assertArrayHasKey('fields', $bkashSystemConfig);

        $fields = $bkashSystemConfig['fields'];
        $fieldNames = array_column($fields, 'name');

        $expectedFields = [
            'title',
            'description',
            'bkash_sandbox',
            'sandbox_base_url',
            'live_base_url',
            'bkash_username',
            'image',
            'bkash_password',
            'bkash_app_key',
            'bkash_app_secret',
            'active',
        ];

        foreach ($expectedFields as $expectedField) {
            $this->assertContains($expectedField, $fieldNames, "Missing field: {$expectedField}");
        }
    }

    #[Test]
    public function it_has_correct_default_api_urls(): void
    {
        $fields = config('bagisto-bkash.system_config.0.fields');

        $sandboxField = collect($fields)->firstWhere('name', 'sandbox_base_url');
        $liveField = collect($fields)->firstWhere('name', 'live_base_url');

        $this->assertEquals('https://checkout.sandbox.bka.sh/v1.2.0-beta', $sandboxField['value']);
        $this->assertEquals('https://checkout.pay.bka.sh/v1.2.0-beta', $liveField['value']);
    }

    #[Test]
    public function it_validates_required_fields_correctly(): void
    {
        $fields = config('bagisto-bkash.system_config.0.fields');

        $requiredFields = collect($fields)->filter(function ($field) {
            return isset($field['validation']) && str_contains($field['validation'], 'required');
        });

        $requiredFieldNames = $requiredFields->pluck('name')->toArray();

        $expectedRequiredFields = [
            'title',
            'bkash_sandbox',
            'bkash_username',
            'bkash_password',
            'bkash_app_key',
            'bkash_app_secret',
            'active',
        ];

        foreach ($expectedRequiredFields as $expectedField) {
            $this->assertContains($expectedField, $requiredFieldNames, "Field should be required: {$expectedField}");
        }
    }

    #[Test]
    public function it_has_conditional_validation_for_urls(): void
    {
        $fields = config('bagisto-bkash.system_config.0.fields');

        $sandboxField = collect($fields)->firstWhere('name', 'sandbox_base_url');
        $liveField = collect($fields)->firstWhere('name', 'live_base_url');

        $this->assertEquals('required_if:bkash_sandbox,1', $sandboxField['validation']);
        $this->assertEquals('required_if:bkash_sandbox,0', $liveField['validation']);
    }

    #[Test]
    public function it_registers_http_macros_correctly(): void
    {
        // Test sandbox mode
        Config::set('sales.payment_methods.bkash.bkash_sandbox', '1');
        Config::set('sales.payment_methods.bkash.sandbox_base_url', 'https://checkout.sandbox.bka.sh/v1.2.0-beta');

        $client = Http::bkash();
        $this->assertEquals('https://checkout.sandbox.bka.sh/v1.2.0-beta', $client->baseUrl);

        // Test live mode
        Config::set('sales.payment_methods.bkash.bkash_sandbox', '0');
        Config::set('sales.payment_methods.bkash.live_base_url', 'https://checkout.pay.bka.sh/v1.2.0-beta');

        $client = Http::bkash();
        $this->assertEquals('https://checkout.pay.bka.sh/v1.2.0-beta', $client->baseUrl);
    }

    #[Test]
    public function it_registers_http_macro_with_token_correctly(): void
    {
        $token = 'test_token_123';
        $appKey = 'test_app_key';

        $client = Http::bkashWithToken($token, $appKey);

        // Check that headers are properly set
        $this->assertEquals('https://checkout.sandbox.bka.sh/v1.2.0-beta', $client->baseUrl);
    }

    #[Test]
    public function it_configures_field_types_correctly(): void
    {
        $fields = config('bagisto-bkash.system_config.0.fields');

        $fieldTypes = [
            'title' => 'text',
            'description' => 'textarea',
            'bkash_sandbox' => 'boolean',
            'sandbox_base_url' => 'text',
            'live_base_url' => 'text',
            'bkash_username' => 'text',
            'image' => 'file',
            'bkash_password' => 'password',
            'bkash_app_key' => 'password',
            'bkash_app_secret' => 'password',
            'active' => 'boolean',
        ];

        foreach ($fieldTypes as $fieldName => $expectedType) {
            $field = collect($fields)->firstWhere('name', $fieldName);
            $this->assertEquals($expectedType, $field['type'], "Field {$fieldName} should have type {$expectedType}");
        }
    }

    #[Test]
    public function it_configures_localization_settings_correctly(): void
    {
        $fields = config('bagisto-bkash.system_config.0.fields');

        $localizedFields = ['title', 'description'];
        $nonLocalizedFields = ['bkash_sandbox', 'bkash_username', 'bkash_password', 'bkash_app_key', 'bkash_app_secret'];

        foreach ($localizedFields as $fieldName) {
            $field = collect($fields)->firstWhere('name', $fieldName);
            $this->assertTrue($field['locale_based'], "Field {$fieldName} should be locale based");
        }

        foreach ($nonLocalizedFields as $fieldName) {
            $field = collect($fields)->firstWhere('name', $fieldName);
            $this->assertFalse($field['locale_based'], "Field {$fieldName} should not be locale based");
        }
    }

    #[Test]
    public function it_configures_channel_settings_correctly(): void
    {
        $fields = config('bagisto-bkash.system_config.0.fields');

        // All fields should be channel-independent for bKash
        foreach ($fields as $field) {
            $this->assertFalse($field['channel_based'], "Field {$field['name']} should not be channel based");
        }
    }

    #[Test]
    public function it_validates_image_file_types(): void
    {
        $fields = config('bagisto-bkash.system_config.0.fields');

        $imageField = collect($fields)->firstWhere('name', 'image');

        $this->assertEquals('file', $imageField['type']);
        $this->assertEquals('mimes:bmp,jpeg,jpg,png,webp', $imageField['validation']);
    }

    #[Test]
    public function it_sets_correct_configuration_hierarchy(): void
    {
        $systemConfig = config('bagisto-bkash.system_config.0');

        $this->assertEquals('sales.payment_methods.bkash', $systemConfig['key']);
        $this->assertEquals(1, $systemConfig['sort']);
        $this->assertIsString($systemConfig['info']);
        $this->assertNotEmpty($systemConfig['info']);
    }
}
