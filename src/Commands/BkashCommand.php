<?php

namespace Ihasan\Bkash\Commands;

use Illuminate\Console\Command;

class BkashCommand extends Command
{
    public $signature = 'bkash:status {payment_id? : Check status of a specific payment}';

    public $description = 'Check bKash payment status and configurations';

    public function handle(): int
    {
        $paymentId = $this->argument('payment_id');

        if ($paymentId) {
            return $this->checkPaymentStatus($paymentId);
        }

        return $this->showConfiguration();
    }

    private function checkPaymentStatus(string $paymentId): int
    {
        try {
            $payment = \Ihasan\Bkash\Models\BkashPayment::where('payment_id', $paymentId)->first();

            if (! $payment) {
                $this->error("Payment with ID {$paymentId} not found.");

                return self::FAILURE;
            }

            $this->info("Payment Status: {$payment->status}");
            $this->info("Amount: {$payment->amount}");
            $this->info("Invoice: {$payment->invoice_number}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error checking payment status: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function showConfiguration(): int
    {
        $this->info('bKash Package Configuration:');
        $this->line('');

        $sandbox = core()->getConfigData('sales.payment_methods.bkash.bkash_sandbox');
        $this->info('Mode: '.($sandbox ? 'Sandbox' : 'Live'));

        $baseUrl = $sandbox === '1'
            ? core()->getConfigData('sales.payment_methods.bkash.sandbox_base_url')
            : core()->getConfigData('sales.payment_methods.bkash.live_base_url');

        $this->info('Base URL: '.($baseUrl ?: 'Not configured'));

        $username = core()->getConfigData('sales.payment_methods.bkash.bkash_username');
        $this->info('Username: '.($username ? 'Configured' : 'Not configured'));

        $this->comment('bKash integration is ready!');

        return self::SUCCESS;
    }
}
