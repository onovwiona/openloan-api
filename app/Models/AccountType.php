<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AccountType extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'currency',
        'account_category',
        'normal_balance',
        'min_balance',
        'max_balance',
        'allow_overdraft',
        'overdraft_limit',
        'accrues_interest',
        'interest_rate',
        'description',
        'is_customer_visible',
        'supports_deposit',
        'supports_withdrawal',
        'supports_transfer',
        'requires_kyc',
        'active',
    ];

    protected $casts = [
        'min_balance' => 'decimal:2',
        'max_balance' => 'decimal:2',
        'overdraft_limit' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'allow_overdraft' => 'boolean',
        'accrues_interest' => 'boolean',
        'is_customer_visible' => 'boolean',
        'supports_deposit' => 'boolean',
        'supports_withdrawal' => 'boolean',
        'supports_transfer' => 'boolean',
        'requires_kyc' => 'boolean',
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
     * Get accounts of this type
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'account_type_id');
    }

    /**
     * Scope to get only active account types
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Check if this type allows overdraft
     */
    public function allowsOverdraft(): bool
    {
        return $this->allow_overdraft;
    }

    public function isCustomerVisible(): bool
    {
        return $this->is_customer_visible;
    }

    public function canDeposit(): bool
    {
        return $this->supports_deposit;
    }

    public function canWithdraw(): bool
    {
        return $this->supports_withdrawal;
    }

    public function canTransfer(): bool
    {
        return $this->supports_transfer;
    }

    public function isLoanAccount(): bool
    {
        return $this->account_category === 'LOAN' || $this->code === 'LOAN';
    }
}