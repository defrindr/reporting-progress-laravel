<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use HasFactory;

    public const TYPE_INTERNSHIP = 'internship';

    public const TYPE_SPRINT = 'sprint';

    protected $fillable = [
        'institution_id',
        'type',
        'name',
        'start_date',
        'end_date',
        'holidays',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'holidays' => 'array',
        ];
    }

    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function interns(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'period_user')
            ->withTimestamps();
    }
}
