<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }
}
