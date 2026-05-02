<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'reference',
        'source_type',
        'source_id',
        'description',
        'entry_date',
        'posted_by',
        'status',
        'reversed_by',
        'reversal_of',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'status' => 'string',
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
     * Get the user who posted this entry
     */
    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Get the user who reversed this entry (if any)
     */
    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * Get the journal lines
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Get the original journal entry if this is a reversal
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of');
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('entry_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by source
     */
    public function scopeBySource($query, string $sourceType, ?string $sourceId = null)
    {
        $query->where('source_type', $sourceType);
        if ($sourceId) {
            $query->where('source_id', $sourceId);
        }
        return $query;
    }

    /**
     * Check if entry is balanced (debits = credits)
     */
    public function isBalanced(): bool
    {
        $totals = $this->lines()->selectRaw(
            'SUM(debit) as total_debit, SUM(credit) as total_credit'
        )->first();

        return $totals->total_debit == $totals->total_credit;
    }

    /**
     * Get total debits
     */
    public function getTotalDebitsAttribute(): float
    {
        return $this->lines()->sum('debit');
    }

    /**
     * Get total credits
     */
    public function getTotalCreditsAttribute(): float
    {
        return $this->lines()->sum('credit');
    }

    /**
     * Generate a unique journal reference
     */
    public static function generateReference(string $prefix = 'JE'): string
    {
        $date = now()->format('Ymd');
        $lastEntry = self::where('reference', 'like', "{$prefix}-{$date}%")
            ->orderBy('reference', 'desc')
            ->first();

        $sequence = 1;
        if ($lastEntry) {
            $lastSequence = (int) substr($lastEntry->reference, -6);
            $sequence = $lastSequence + 1;
        }

        return sprintf('%s-%s-%06d', $prefix, $date, $sequence);
    }
}