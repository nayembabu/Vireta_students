<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Routine extends Model
{
    protected $table = 'routines';

    protected $fillable = [
        'batch_id', 'course_id', 'mentor_id', 'session_date',
        'start_time', 'end_time', 'topic', 'room', 'meeting_link', 'created_by',
    ];

    protected $casts = [
        'session_date' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}