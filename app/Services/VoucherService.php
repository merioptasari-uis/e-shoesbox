<?php

namespace App\Services;

use App\Models\Voucher;
use Carbon\Carbon;

class VoucherService
{
    /**
     * Validate a voucher code against constraints.
     *
     * @return array{isValid: bool, message: string, voucher: ?Voucher}
     */
    public function validate(string $code, float $subtotal, ?int $userId = null): array
    {
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            return [
                'isValid' => false,
                'message' => 'Voucher code is invalid.',
                'voucher' => null,
            ];
        }

        if (! $voucher->is_active) {
            return [
                'isValid' => false,
                'message' => 'This voucher is no longer active.',
                'voucher' => $voucher,
            ];
        }

        if ($voucher->expires_at && Carbon::now()->greaterThanOrEqualTo($voucher->expires_at)) {
            return [
                'isValid' => false,
                'message' => 'This voucher has expired.',
                'voucher' => $voucher,
            ];
        }

        if ($subtotal < $voucher->min_spend) {
            return [
                'isValid' => false,
                'message' => 'Minimum spend of Rp '.number_format($voucher->min_spend, 0, ',', '.').' is required to use this voucher.',
                'voucher' => $voucher,
            ];
        }

        if ($voucher->limit_total !== null && $voucher->used_count >= $voucher->limit_total) {
            return [
                'isValid' => false,
                'message' => 'This voucher usage limit has been reached.',
                'voucher' => $voucher,
            ];
        }

        if ($userId !== null) {
            $userUsage = $voucher->orders()
                ->where('user_id', $userId)
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($userUsage >= $voucher->limit_per_user) {
                return [
                    'isValid' => false,
                    'message' => 'You have reached the usage limit for this voucher.',
                    'voucher' => $voucher,
                ];
            }
        }

        return [
            'isValid' => true,
            'message' => 'Voucher applied successfully.',
            'voucher' => $voucher,
        ];
    }

    /**
     * Calculate discount for a product/shop discount voucher.
     */
    public function calculateProductDiscount(Voucher $voucher, float $subtotal): float
    {
        if ($voucher->type === 'fixed') {
            return min((float) $voucher->value, $subtotal);
        }

        if ($voucher->type === 'percentage') {
            $discount = $subtotal * ($voucher->value / 100);
            if ($voucher->max_discount !== null) {
                return min($discount, (float) $voucher->max_discount);
            }

            return $discount;
        }

        return 0.00;
    }

    /**
     * Calculate discount for a shipping voucher.
     */
    public function calculateShippingDiscount(Voucher $voucher, float $shippingCost): float
    {
        if ($voucher->type === 'shipping') {
            return min((float) $voucher->value, $shippingCost);
        }

        return 0.00;
    }
}
