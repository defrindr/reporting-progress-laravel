<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_date',
        'name',
        'country_code',
        'year',
        'source',
        'is_company_holiday',
        'description',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'year' => 'integer',
            'is_company_holiday' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }
}
