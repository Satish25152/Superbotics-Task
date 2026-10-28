<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryLimit extends Model
{
    protected $fillable = ['category_id', 'monthly_limit'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
