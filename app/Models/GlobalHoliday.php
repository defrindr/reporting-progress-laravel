<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_date',
        'name',
        'country_code',
        'year',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'year' => 'integer',
        ];
    }
}