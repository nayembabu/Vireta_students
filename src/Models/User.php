<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'phone', 'password_hash', 'role',
        'status', 'batch_id', 'student_id',
    ];

    protected $hidden = ['password_hash'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'user_courses')
            ->withPivot('status');
    }

    public function enrolledCourses(): BelongsToMany
    {
        return $this->courses()
            ->wherePivotIn('status', ['enrolled', 'in_progress', 'completed']);
    }
}