<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccountBalance extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'available_balance',
        'ledger_balance',
        'hold_balance',
        'uncleared_balance',
        'as_at',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'ledger_balance' => 'decimal:2',
        'hold_balance' => 'decimal:2',
        'uncleared_balance' => 'decimal:2',
        'as_at' => 'datetime',
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