<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanSchedule extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'loan_id',
        'installment_no',
        'due_date',
        'principal_due',
        'interest_due',
        'fees_due',
        'penalty_due',
        'total_due',
        'amount_paid',
        'principal_paid',
        'interest_paid',
        'fees_paid',
        'penalty_paid',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'principal_due' => 'decimal:2',
        'interest_due' => 'decimal:2',
        'fees_due' => 'decimal:2',
        'penalty_due' => 'decimal:2',
        'total_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'principal_paid' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'fees_paid' => 'decimal:2',
        'penalty_paid' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    protected $appends = [
        'principal_outstanding',
        'interest_outstanding',
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
     * Get the loan
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Check if overdue
     */
    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date < now()->toDateString();
    }

    /**
     * Get remaining amount due
     */
    public function getRemainingAttribute(): float
    {
        return $this->total_due - $this->amount_paid;
    }

    /**
     * Get outstanding principal for this installment
     */
    public function getPrincipalOutstandingAttribute(): float
    {
        return max(0, $this->principal_due - $this->principal_paid);
    }

    /**
     * Get outstanding interest for this installment
     */
    public function getInterestOutstandingAttribute(): float
    {
        return max(0, $this->interest_due - $this->interest_paid);
    }

    /**
     * Get repayment allocations for this schedule
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class, 'schedule_id');
    }
}