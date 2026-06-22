<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'size',
        'color',
        'hex_color',
        'stock',
    ];

    /**
     * Get the product that this variant belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get hex color code for a color name.
     */
    public static function getHexColor(?string $color): ?string
    {
        if (! $color) {
            return null;
        }

        return match (strtolower(trim($color))) {
            'hitam', 'black' => '#1a1a1a',
            'putih', 'white' => '#ffffff',
            'merah', 'red' => '#ef4444',
            'biru', 'blue' => '#3b82f6',
            'hijau', 'green' => '#10b981',
            'kuning', 'yellow' => '#f59e0b',
            'abu-abu', 'abu', 'gray', 'grey' => '#9ca3af',
            'cokelat', 'coklat', 'brown' => '#78350f',
            'navy' => '#1e3a8a',
            'pink', 'merah muda' => '#ec4899',
            'orange', 'jingga' => '#f97316',
            'purple', 'ungu' => '#a855f7',
            'gold', 'emas' => '#d97706',
            'silver', 'perak' => '#cbd5e1',
            'maroon' => '#800000',
            'tosca' => '#0d9488',
            'cream' => '#fef3c7',
            default => null
        };
    }
}
