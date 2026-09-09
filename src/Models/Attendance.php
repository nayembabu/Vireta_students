<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'attendance';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'course_id', 'routine_id', 'session_date',
        'status', 'check_in_time', 'marked_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class, 'routine_id');
    }
}