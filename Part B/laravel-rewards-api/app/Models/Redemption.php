<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Redemption extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'product_id', 'category_id',
        'points_spent', 'redeemed_at',
    ];

    protected function casts(): array
    {
        return ['points_spent' => 'integer', 'redeemed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(PointsLedger::class);
    }
}
