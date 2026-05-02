<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'beneficiary_role',
        'trigger_type',
        'amount_type',
        'amount_value',
        'minimum_amount',
        'is_active',
        'starts_at',
        'ends_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'date',
        'ends_at' => 'date'
    ];
}
