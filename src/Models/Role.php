<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 1;
    public const STUDENT = 2;
    public const TRAINER = 3;
    public const CASHIER = 4;

    protected $table = 'roles';

    protected $fillable = [
        'name', 'slug', 'description',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}