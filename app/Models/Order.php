<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'order_number',
    'subtotal_amount',
    'shipping_cost',
    'discount_amount',
    'shipping_discount_amount',
    'total_amount',
    'shipping_courier',
    'shipping_service',
    'tracking_number',
    'status',
    'notes',
    'shipping_recipient_name',
    'shipping_phone_number',
    'shipping_address_line',
    'shipping_province',
    'shipping_city',
    'shipping_postal_code',
])]
class Order extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items in this order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the payment details for this order.
     *
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * The vouchers applied to this order.
     *
     * @return BelongsToMany<Voucher, $this>
     */
    public function vouchers(): BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, 'order_vouchers')
            ->withPivot('applied_discount')
            ->withTimestamps();
    }
}
