<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraudFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_user_id',
        'related_user_id',
        'flag_type',
        'severity',
        'status',
        'details',
        'detected_by',
        'reviewed_by',
        'detected_at',
        'reviewed_at',
        'resolved_at'
    ];

    protected $casts = [
        'details' => 'array',
        'detected_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'resolved_at' => 'datetime'
    ];
}
