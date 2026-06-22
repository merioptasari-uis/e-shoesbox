<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * Handle Midtrans notifications.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('Midtrans Webhook Received', $payload);

        $orderNumber = $payload['order_id'] ?? null;
        if ($orderNumber) {
            $orderNumber = str_replace('-', '/', $orderNumber);
        }
        $transactionStatus = $payload['transaction_status'] ?? null;
        $paymentType = $payload['payment_type'] ?? null;
        $transactionId = $payload['transaction_id'] ?? null;

        if (! $orderNumber) {
            return response()->json(['message' => 'Invalid payload: missing order_id'], 400);
        }

        $order = Order::with(['payment', 'items.product', 'items.productVariant'])->where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        try {
            DB::transaction(function () use ($order, $transactionStatus, $paymentType, $transactionId, $payload) {
                $payment = $order->payment;
                if (! $payment) {
                    return;
                }

                $mappedStatus = match ($transactionStatus) {
                    'settlement', 'capture' => 'settlement',
                    'pending' => 'pending',
                    'deny', 'cancel' => 'cancel',
                    'expire' => 'expire',
                    default => $payment->status,
                };

                // Check if transitioning to cancelled/expire from pending
                if (in_array($mappedStatus, ['cancel', 'expire']) && $order->status === 'pending') {
                    // Restore stock
                    foreach ($order->items as $item) {
                        if ($item->product_variant_id && $item->productVariant) {
                            $item->productVariant->increment('stock', $item->quantity);
                        } elseif ($item->product) {
                            $item->product->increment('stock', $item->quantity);
                        }
                    }
                    // Restore voucher usage
                    foreach ($order->vouchers as $voucher) {
                        $voucher->decrement('used_count');
                    }
                    $order->update(['status' => 'cancelled']);
                }

                if ($mappedStatus === 'settlement' && $order->status === 'pending') {
                    $order->update(['status' => 'processing']);
                }

                $payment->update([
                    'status' => $mappedStatus,
                    'payment_type' => $paymentType,
                    'transaction_id' => $transactionId,
                    'payment_payload' => $payload,
                ]);
            });

            return response()->json(['message' => 'Webhook handled successfully']);
        } catch (\Exception $e) {
            Log::error('Midtrans webhook processing failed: '.$e->getMessage());

            return response()->json(['message' => 'Processing failed'], 500);
        }
    }
}
