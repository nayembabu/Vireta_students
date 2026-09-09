<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'courses';

    protected $fillable = [
        'title', 'slug', 'description', 'duration_weeks', 'status', 'fee',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'course_id');
    }
}