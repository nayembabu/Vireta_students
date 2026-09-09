<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'educational_registration_no', 'phone_no', 'name', 'father_name',
        'mother_name', 'email', 'address', 'pro_pic', 'ssc_roll',
        'ssc_registration', 'whatsapp_number', 'emergency_phone',
        'date_of_birth', 'gender', 'blood_group', 'nid_birth_no',
        'batch_id', 'status', 'registered_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'registered_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'student_id');
    }
}