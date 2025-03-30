# bKash Payment for Bagisto

This package provides bKash Payment Gateway integration for Bagisto e-commerce platform using the theihasan/laravel-bkash package.

## Features

- Seamless integration with bKash Tokenized Payment Gateway
- Support for sandbox and live environments
- Payment verification and validation
- Order tracking with payment status
- Refund capability through the bKash API
- Custom success and failure URLs
- Detailed transaction logging
- Exception handling with custom exception classes

## Installation

1. Install the package via composer:
   ```bash
   composer require webkul/bkash-payment
   ```

2. Run migrations to create the bkash_transactions table:
   ```bash
   php artisan migrate
   ```

3. Publish assets (optional):
   ```bash
   php artisan vendor:publish --provider="Webkul\BkashPayment\Providers\BkashPaymentServiceProvider" --tag=bkash-payment-assets
   ```

## Configuration

Go to Admin Panel -> Settings -> Payment Methods and configure bKash Payment with your credentials:

- **Username**: Your bKash username
- **Password**: Your bKash password
- **App Key**: Your bKash App Key
- **App Secret**: Your bKash App Secret
- **Sandbox Mode**: Enable for testing
- **Sandbox/Live Base URL**: Only change if bKash provides different URLs
- **Custom Success/Failure URLs**: Optional redirects after payment
- **Logo**: Upload your own custom logo for the payment method

## Test Credentials

For testing in sandbox mode, you can use:

