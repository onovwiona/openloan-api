<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Account extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'account_type_id',
        'account_no',
        'name',
        'currency',
        'status',
        'opened_at',
        'closed_at',
        'freeze_reason',
        'frozen_by',
        'frozen_at',
    ];

    protected $casts = [
        'opened_at' => 'date',
        'closed_at' => 'date',
        'frozen_at' => 'datetime',
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
     * Get the account type
     */
    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    /**
     * Get the customer (user)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the user who froze this account
     */
    public function frozenByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    /**
     * Get the current balance
     */
    public function balance(): HasOne
    {
        return $this->hasOne(AccountBalance::class)->latest();
    }

    /**
     * Get all balances history
     */
    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class)->orderBy('as_at', 'desc');
    }

    /**
     * Get account limits
     */
    public function limits(): HasOne
    {
        return $this->hasOne(AccountLimit::class);
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
     * Scope to get active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get available balance
     */
    public function getAvailableBalanceAttribute(): float
    {
        return $this->balance?->available_balance ?? 0;
    }

    /**
     * Get ledger balance
     */
    public function getLedgerBalanceAttribute(): float
    {
        return $this->balance?->ledger_balance ?? 0;
    }

    /**
     * Get hold balance
     */
    public function getHoldBalanceAttribute(): float
    {
        return $this->balance?->hold_balance ?? 0;
    }

    /**
     * Freeze the account
     */
    public function freeze(string $reason): void
    {
        $this->update([
            'status' => 'frozen',
            'freeze_reason' => $reason,
            'frozen_by' => auth()->id(),
            'frozen_at' => now(),
        ]);
    }

    /**
     * Unfreeze the account
     */
    public function unfreeze(): void
    {
        $this->update([
            'status' => 'active',
            'freeze_reason' => null,
            'frozen_by' => null,
            'frozen_at' => null,
        ]);
    }

    /**
     * Close the account
     */
    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now()->toDateString(),
        ]);
    }

    /**
     * Generate account number
     */
    public static function generateAccountNumber(string $prefix = 'ACC'): string
    {
        $date = now()->format('ymd');
        $lastAccount = self::where('account_no', 'like', "{$prefix}{$date}%")
            ->orderBy('account_no', 'desc')
            ->first();

        $sequence = 1;
        if ($lastAccount) {
            $lastSequence = (int) substr($lastAccount->account_no, -6);
            $sequence = $lastSequence + 1;
        }

        return sprintf('%s%s%06d', $prefix, $date, $sequence);
    }
}