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
                'message' => 'Kode voucher tidak valid.',
                'voucher' => null,
            ];
        }

        if (! $voucher->is_active) {
            return [
                'isValid' => false,
                'message' => 'Voucher ini sudah tidak aktif.',
                'voucher' => $voucher,
            ];
        }

        if ($voucher->expires_at && Carbon::now()->greaterThanOrEqualTo($voucher->expires_at)) {
            return [
                'isValid' => false,
                'message' => 'Voucher ini telah kedaluwarsa.',
                'voucher' => $voucher,
            ];
        }

        if ($subtotal < $voucher->min_spend) {
            return [
                'isValid' => false,
                'message' => 'Minimal belanja Rp '.number_format($voucher->min_spend, 0, ',', '.').' diperlukan untuk menggunakan voucher ini.',
                'voucher' => $voucher,
            ];
        }

        if ($voucher->limit_total !== null && $voucher->used_count >= $voucher->limit_total) {
            return [
                'isValid' => false,
                'message' => 'Batas penggunaan voucher ini telah tercapai.',
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
                    'message' => 'Anda telah mencapai batas penggunaan untuk voucher ini.',
                    'voucher' => $voucher,
                ];
            }
        }

        return [
            'isValid' => true,
            'message' => 'Voucher berhasil digunakan.',
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
