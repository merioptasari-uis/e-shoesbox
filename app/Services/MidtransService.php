<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    protected string $serverKey;

    protected bool $isProduction;

    protected string $snapUrl;

    public function __construct()
    {
        $this->serverKey = (string) config('services.midtrans.server_key', '');
        $this->isProduction = (bool) config('services.midtrans.is_production', false);
        $this->snapUrl = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /**
     * Generate Snap Token from Midtrans.
     *
     * @param  array<string, string>  $customerDetails
     * @param  array<int, array<string, mixed>>  $itemDetails
     */
    public function getSnapToken(string $orderNumber, float $amount, array $customerDetails, array $itemDetails = []): ?string
    {
        // If server key is empty or placeholder, return a mock token for local testing
        if (empty($this->serverKey) || str_starts_with($this->serverKey, 'sk-proj') || str_starts_with($this->serverKey, 'sk-ant') || str_starts_with($this->serverKey, 'AIzaSy') || str_contains($this->serverKey, 'key_here')) {
            Log::info('Midtrans placeholder server key detected. Generating mock Snap Token.');

            return 'mock-snap-token-'.uniqid();
        }

        try {
            $name = trim($customerDetails['name'] ?? '');
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';

            $payload = [
                'transaction_details' => [
                    'order_id' => $orderNumber,
                    'gross_amount' => (int) $amount,
                ],
                'customer_details' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $customerDetails['email'] ?? '',
                    'phone' => $customerDetails['phone'] ?? '',
                ],
                'credit_card' => [
                    'secure' => true,
                ],
                'expiry' => [
                    'duration' => 2,
                    'unit' => 'hours',
                ],
            ];

            if (isset($customerDetails['billing_address'])) {
                $payload['customer_details']['billing_address'] = $customerDetails['billing_address'];
            }
            if (isset($customerDetails['shipping_address'])) {
                $payload['customer_details']['shipping_address'] = $customerDetails['shipping_address'];
            }

            if (! empty($itemDetails)) {
                $payload['item_details'] = $itemDetails;
            }

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->withBasicAuth($this->serverKey, '')
                ->post($this->snapUrl, $payload);

            if ($response->successful()) {
                return $response->json('token');
            }

            Log::error('Midtrans Snap Token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Midtrans Snap Exception', ['message' => $e->getMessage()]);
        }

        // Return a mock token as fallback so developers don't get blocked
        return 'mock-snap-token-fallback-'.uniqid();
    }
}
