<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccountLimit extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'daily_debit_limit',
        'daily_credit_limit',
        'monthly_debit_limit',
        'monthly_credit_limit',
        'single_transaction_limit',
    ];

    protected $casts = [
        'daily_debit_limit' => 'decimal:2',
        'daily_credit_limit' => 'decimal:2',
        'monthly_debit_limit' => 'decimal:2',
        'monthly_credit_limit' => 'decimal:2',
        'single_transaction_limit' => 'decimal:2',
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
     * Get the account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}