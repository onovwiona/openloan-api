<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'kyc_documents',
        'kyc_status',
        'kyc_verified_at',
        'kyc_reviewed_by'
    ];

    protected $casts = [
        'dob' => 'date',
        'kyc_verified_at' => 'datetime',
        'monthly_income' => 'decimal:2',
        'kyc_documents' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
