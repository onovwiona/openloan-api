<?php

namespace App\Models;

use App\Models\CustomerEmploymentProfile;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'monthly_income',
        'kyc_documents',
        'kyc_status',
        'kyc_verified_at',
        'kyc_tier',
        'kyc_reviewed_by',
        'profile_update_note',
        'profile_updated_by',
    ];

    protected $casts = [
        'dob' => 'date',
        'kyc_verified_at' => 'datetime',
        'monthly_income' => 'decimal:2',
        'kyc_documents' => 'array',
        'kyc_tier' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function employmentProfile(): HasOne
    {
        return $this->hasOne(CustomerEmploymentProfile::class);
    }
}
