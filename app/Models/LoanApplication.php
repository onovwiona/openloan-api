<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanApplication extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'loan_product_id',
        'account_id',
        'application_no',
        'requested_amount',
        'requested_tenure',
        'monthly_income',
        'employment_status',
        'purpose',
        'status',
        'rejection_reason',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'monthly_income' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Get the customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the loan product
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    /**
     * Get the repayment account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the reviewer
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the loan (if approved)
     */
    public function loan(): BelongsTo
    {
        return $this->hasOne(Loan::class, 'loan_application_id');
    }

    /**
     * Get guarantors
     */
    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class, 'loan_application_id');
    }

    /**
     * Get collaterals
     */
    public function collaterals(): HasMany
    {
        return $this->hasMany(LoanCollateral::class, 'loan_application_id');
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by customer
     */
    public function scopeForCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Submit the application
     */
    public function submit(): void
    {
        if ($this->status !== 'draft') {
            throw new \Exception('Only draft applications can be submitted');
        }

        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Cancel the application
     */
    public function cancel(): void
    {
        if (!in_array($this->status, ['draft', 'submitted', 'under_review'])) {
            throw new \Exception('Application cannot be cancelled in current status');
        }

        $this->update([
            'status' => 'cancelled',
        ]);
    }

    /**
     * Generate application number
     */
    public static function generateApplicationNumber(): string
    {
        $prefix = 'LNAPP';
        $date = now()->format('ymd');
        $lastApp = self::where('application_no', 'like', "{$prefix}{$date}%")
            ->orderBy('application_no', 'desc')
            ->first();

        $sequence = 1;
        if ($lastApp) {
            $lastSequence = (int) substr($lastApp->application_no, -6);
            $sequence = $lastSequence + 1;
        }

        return sprintf('%s%s%06d', $prefix, $date, $sequence);
    }
}