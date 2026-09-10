<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $table = 'batches';

    protected $fillable = [
        'name', 'course_id', 'start_date', 'end_date', 'status',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'batch_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'batch_id');
    }
}