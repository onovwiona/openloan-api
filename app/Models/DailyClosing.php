<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'closing_date',
        'total_debits',
        'total_credits',
        'balanced',
        'closed_by',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'total_debits' => 'decimal:2',
        'total_credits' => 'decimal:2',
        'balanced' => 'boolean',
    ];

    /**
     * Get the user who closed the day
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Scope to check if a date is already closed
     */
    public function scopeClosedDate($query, $date)
    {
        return $query->where('closing_date', $date);
    }

    /**
     * Check if a specific date is closed
     */
    public static function isDateClosed($date): bool
    {
        return self::where('closing_date', $date)->exists();
    }
}