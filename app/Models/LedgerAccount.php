<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LedgerAccount extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'currency',
        'active',
        'allow_manual_entry',
        'description',
        'level',
    ];

    protected $casts = [
        'active' => 'boolean',
        'allow_manual_entry' => 'boolean',
        'level' => 'integer',
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
     * Get the parent ledger account
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'parent_id');
    }

    /**
     * Get child ledger accounts
     */
    public function children(): HasMany
    {
        return $this->hasMany(LedgerAccount::class, 'parent_id');
    }

    /**
     * Get journal lines for this account
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Scope to filter by account type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get only active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Get the account type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'income' => 'Income',
            'expense' => 'Expense',
            default => 'Unknown',
        };
    }

    /**
     * Check if this is a control account (has children)
     */
    public function isControlAccount(): bool
    {
        return $this->children()->exists();
    }
}