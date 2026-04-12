<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'specification',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedInterns(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_spec_user');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Project::class, 'project_spec_id');
    }

    public function backlogs(): HasMany
    {
        return $this->hasMany(Project::class, 'project_spec_id');
    }
}
