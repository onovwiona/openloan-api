<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepaymentAllocation extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'repayment_id',
        'schedule_id',
        'principal_amount',
        'interest_amount',
        'fees_amount',
        'penalty_amount',
        'status',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'fees_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
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
     * Get the repayment associated with this allocation
     */
    public function repayment(): BelongsTo
    {
        return $this->belongsTo(LoanRepayment::class);
    }

    /**
     * Get the loan schedule associated with this allocation
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LoanSchedule::class);
    }

    /**
     * Get the total allocated amount (sum of all allocation types)
     */
    public function getTotalAllocatedAttribute(): float
    {
        return $this->principal_amount + $this->interest_amount + $this->fees_amount + $this->penalty_amount;
    }
}
