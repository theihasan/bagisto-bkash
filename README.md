# bKash Payment Gateway for Bagisto

[![Latest Version on Packagist](https://img.shields.io/packagist/v/theihasan/bagisto-bkash.svg?style=flat-square)](https://packagist.org/packages/theihasan/bagisto-bkash)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/theihasan/bagisto-bkash/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/theihasan/bagisto-bkash/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/theihasan/bagisto-bkash/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/theihasan/bagisto-bkash/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/theihasan/bagisto-bkash.svg?style=flat-square)](https://packagist.org/packages/theihasan/bagisto-bkash)

A comprehensive bKash payment gateway integration for Bagisto eCommerce platform. This package provides seamless integration with bKash's tokenized checkout API, supporting both sandbox and live environments with robust error handling and automatic token management.

## Features

- 🔒 Secure tokenized payment processing
- 🌐 Sandbox and Live environment support
- 🔄 Automatic token refresh and caching
- 💳 Complete payment lifecycle management
- 📊 Payment status tracking
- 🛡️ Comprehensive error handling
- 🧪 Full test coverage
- 📝 Extensive logging

## Installation

You can install the package via composer:

```bash
composer require theihasan/bagisto-bkash
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="bagisto-bkash-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="bagisto-bkash-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="bagisto-bkash-views"
```

## Configuration

After installation, configure your bKash credentials in the Bagisto admin panel:

1. Go to **Admin > Configuration > Sales > Payment Methods > bKash**
2. Enable the payment method
3. Configure your bKash credentials:
   - Username
   - Password  
   - App Key
   - App Secret
   - Base URL (Sandbox/Live)
4. Set sandbox mode (for testing)

## Usage

### Using the Service

```php
use Ihasan\Bkash\Facades\Bkash;

// Create a payment
$payment = Bkash::createPayment($cart);

// Execute a payment  
$result = Bkash::executePayment($paymentId);

// Get credentials
$credentials = Bkash::getCredentials();
```

### Using the Payment Service Directly

```php
use Ihasan\Bkash\Services\BkashPaymentService;

$paymentService = app(BkashPaymentService::class);

// Create payment
$paymentData = $paymentService->createPayment($cart);

// Process callback
$response = $paymentService->processCallback($request);
```

### Console Commands

Check payment status:
```bash
php artisan bkash:status {payment_id}
```

View configuration:
```bash  
php artisan bkash:status
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Abul Hassan](https://github.com/theihasan)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
