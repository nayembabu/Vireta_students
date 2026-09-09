<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $table = 'assignments';

    protected $fillable = [
        'course_id', 'title', 'description', 'due_date', 'max_score',
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class, 'assignment_id');
    }

    public function latestSubmission(int $userId): ?AssignmentSubmission
    {
        return $this->submissions()
            ->where('user_id', $userId)
            ->orderByDesc('version')
            ->first();
    }
}