<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProfile extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address',
        'dob',
        'bvn_encrypted',
        'bvn_hash',
        'nin',
        'employment_status',
        'monthly_income',
        'kyc_status',
        'kyc_verified_at'
    ];

    protected $casts = [
        'dob' => 'date',
        'kyc_verified_at' => 'datetime',
        'monthly_income' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
