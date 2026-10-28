<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsLedger extends Model
{
    protected $table = 'points_ledger';

    protected $fillable = ['user_id', 'type', 'points', 'redemption_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'occurred_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemption::class);
    }
}
