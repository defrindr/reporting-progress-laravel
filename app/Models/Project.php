<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Project extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_spec_id',
        'period_id',
        'title',
        'description',
        'due_date',
        'priority',
        'assignee_id',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function spec(): BelongsTo
    {
        return $this->belongsTo(ProjectSpec::class, 'project_spec_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('project')
            ->logOnly(['status'])
            ->logOnlyDirty();
    }
}
