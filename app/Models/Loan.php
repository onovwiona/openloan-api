<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'loan_application_id',
        'customer_id',
        'account_id',
        'loan_no',
        'principal',
        'interest_rate',
        'tenure_months',
        'repayment_plan',
        'total_interest',
        'total_repayment',
        'disbursed_amount',
        'outstanding_principal',
        'outstanding_interest',
        'outstanding_total',
        'status',
        'disbursed_at',
        'maturity_date',
        'first_payment_date',
        'approved_by',
        'approved_at',
        'disbursed_by',
    ];

    protected $casts = [
        'principal' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'total_repayment' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'outstanding_principal' => 'decimal:2',
        'outstanding_interest' => 'decimal:2',
        'outstanding_total' => 'decimal:2',
        'disbursed_at' => 'date',
        'maturity_date' => 'date',
        'first_payment_date' => 'date',
        'approved_at' => 'datetime',
        'repayment_plan' => 'string',
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
     * Get the loan application
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    /**
     * Get the customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the disbursement account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the approver
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the disburser
     */
    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    /**
     * Get repayment schedules
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('installment_no');
    }

    /**
     * Get repayments
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class)->orderBy('paid_at', 'desc');
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
     * Get monthly installment amount
     */
    public function getMonthlyInstallmentAttribute(): float
    {
        return round($this->total_repayment / $this->tenure_months, 2);
    }

    /**
     * Get amount paid so far
     */
    public function getAmountPaidAttribute(): float
    {
        return $this->repayments()->sum('amount');
    }

    /**
     * Get remaining balance
     */
    public function getRemainingBalanceAttribute(): float
    {
        return $this->outstanding_total;
    }

    /**
     * Check if loan is in default
     */
    public function isInDefault(): bool
    {
        $overdueSchedule = $this->schedules()
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->toDateString())
            ->first();

        return $overdueSchedule !== null;
    }

    /**
     * Generate loan number
     */
    public static function generateLoanNumber(): string
    {
        $prefix = 'LN';
        $date = now()->format('ymd');
        $lastLoan = self::where('loan_no', 'like', "{$prefix}{$date}%")
            ->orderBy('loan_no', 'desc')
            ->first();

        $sequence = 1;
        if ($lastLoan) {
            $lastSequence = (int) substr($lastLoan->loan_no, -6);
            $sequence = $lastSequence + 1;
        }

        return sprintf('%s%s%06d', $prefix, $date, $sequence);
    }
}