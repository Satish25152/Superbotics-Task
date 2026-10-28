<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name'];

    public function limit(): HasOne
    {
        return $this->hasOne(CategoryLimit::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
