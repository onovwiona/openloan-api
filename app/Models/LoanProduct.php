<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LoanProduct extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'requires_account',
        'repayment_account_type_id',
        'min_amount',
        'max_amount',
        'interest_type',
        'interest_rate',
        'tenure_min_months',
        'tenure_max_months',
        'processing_fee',
        'penalty_rate',
        'insurance_fee',
        'legal_fee',
        'allow_early_repayment',
        'early_repayment_penalty',
        'requires_guarantor',
        'min_guarantors',
        'requires_collateral',
        'active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'penalty_rate' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'legal_fee' => 'decimal:2',
        'early_repayment_penalty' => 'decimal:2',
        'requires_account' => 'boolean',
        'allow_early_repayment' => 'boolean',
        'requires_guarantor' => 'boolean',
        'requires_collateral' => 'boolean',
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get applications for this product
     */
    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class, 'loan_product_id');
    }

    /**
     * Get repayment account type
     */
    public function repaymentAccountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'repayment_account_type_id');
    }

    /**
     * Scope to get only active products
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Calculate processing fee
     */
    public function calculateProcessingFee(float $amount): float
    {
        return round($amount * ($this->processing_fee / 100), 2);
    }

    /**
     * Calculate total fees
     */
    public function calculateTotalFees(float $amount): float
    {
        return $this->calculateProcessingFee($amount) +
            ($amount * ($this->insurance_fee / 100)) +
            ($amount * ($this->legal_fee / 100));
    }
}