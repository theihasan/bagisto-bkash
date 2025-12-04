# bKash Payment Gateway for Bagisto

Latest Version on Packagist 🖼 https://img.shields.io/packagist/v/theihasan/bagisto-bkash.svg?style=flat-square
https://packagist.org/packages/theihasan/bagisto-bkash GitHub Tests Action Status 🖼
https://img.shields.io/github/actions/workflow/status/theihasan/bagisto-bkash/run-tests.
yml?branch=main&label=tests&style=flat-square
https://github.com/theihasan/bagisto-bkash/actions?query=workflow%3Arun-tests+branch%3Amain Total Downloads 🖼
https://img.shields.io/packagist/dt/theihasan/bagisto-bkash.svg?style=flat-square
https://packagist.org/packages/theihasan/bagisto-bkash

Simple bKash payment integration for Bagisto e-commerce stores.

## Installation

# Install package
composer require theihasan/bagisto-bkash

# Run installation command
php artisan bagisto-bkash:install

# Run migrations
php artisan migrate

## Configuration

1. Go to Admin Panel
 • Navigate to: Configuration → Sales → Payment Methods → bKash
2. Configure Settings:
 • Status: Enable
 • Live Base URL: https://tokenized.pay.bka.sh/v1.2.0-beta
 • Sandbox Base URL: https://tokenized.sandbox.bka.sh/v1.2.0-beta
 • Username: Your bKash merchant number
 • Password: Your bKash password
 • App Key: Your bKash app key
 • App Secret: Your bKash app secret
 • Logo: Upload bKash logo (optional)
 • Environment: Select Sandbox/Live
3. Test Credentials (Sandbox):
 • Username: 01770618567
 • Password: D7DaC<*E*eG
 • App Key: 0vWQuCRGiUX7EPVjQDr0EUAYtc
 • App Secret: jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QIxxwAMEx


## Verification

• Go to your store's checkout page
• Select bKash as payment method
• Complete test transaction to verify integration

## Features

• Secure tokenized payment processing
• Sandbox and Live environment support
• Automatic token refresh and caching
• Complete payment lifecycle management
• Payment status tracking
• Comprehensive error handling

## Requirements

• Bagisto 2.x
• PHP 8.2+
• Valid bKash merchant account

## Testing

composer test

## License

The MIT License (MIT). Please see License File /LICENSE.md for more information.

---

That's it! bKash payments are now ready. 

---