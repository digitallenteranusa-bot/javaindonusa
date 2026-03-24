<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'type',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    const TYPE_PACKAGE = 'package';
    const TYPE_TAX = 'tax';
    const TYPE_DISCOUNT = 'discount';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_OTHER = 'other';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->amount < 0 ? '-Rp ' : 'Rp ';
        return $prefix . number_format(abs($this->amount), 0, ',', '.');
    }
}
