<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerEmploymentProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'customer_employment_profiles';

    protected $fillable = [
        'customer_profile_id',
        'employer_type',
        'employer_id_number',
        'employment_status',
        'employment_type',
        'retirement_status',
        'employment_year',
        'retirement_year',
        'payroll_gross',
        'payroll_net',
        'employment_documents',
        'employment_profile_status',
        'employment_profile_reviewed_by',
        'employment_profile_reviewed_at',
    ];

    protected $casts = [
        'payroll_gross' => 'decimal:2',
        'payroll_net' => 'decimal:2',
        'employment_year' => 'integer',
        'retirement_year' => 'integer',
        'employment_documents' => 'array',
        'employment_profile_reviewed_at' => 'datetime',
    ];

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employment_profile_reviewed_by');
    }
}
