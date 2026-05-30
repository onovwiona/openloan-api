<?php

namespace App\Models;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KycDocument extends Model
{
    use HasFactory;

    public const TYPE_NIN = 'NIN';
    public const TYPE_BVN = 'BVN';
    public const TYPE_PASSPORT = 'PASSPORT';
    public const TYPE_PASSPORT_DOCUMENT = 'PASSPORT_DOCUMENT';
    public const TYPE_PASSPORT_PHOTO = 'PASSPORT_PHOTO';
    public const TYPE_SELFIE = 'SELFIE';
    public const TYPE_ID_CARD_FRONT = 'ID_CARD_FRONT';
    public const TYPE_ID_CARD_BACK = 'ID_CARD_BACK';
    public const TYPE_DRIVERS_LICENSE = 'DRIVERS_LICENSE';
    public const TYPE_UTILITY_BILL = 'UTILITY_BILL';
    public const TYPE_PROOF_OF_ADDRESS = 'PROOF_OF_ADDRESS';
    public const TYPE_APPOINTMENT_LETTER = 'APPOINTMENT_LETTER';
    public const TYPE_EMPLOYER_ID_CARD = 'EMPLOYER_ID_CARD';
    public const TYPE_EMPLOYMENT_LETTER = 'EMPLOYMENT_LETTER';
    public const TYPE_EMPLOYMENT_DOCUMENT = 'EMPLOYMENT_DOCUMENT';
    public const TYPE_PAYSLIP_DOCUMENT = 'PAYSLIP_DOCUMENT';

    public const VERIFICATION_PENDING = 'PENDING';
    public const VERIFICATION_APPROVED = 'APPROVED';
    public const VERIFICATION_REJECTED = 'REJECTED';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'customer_profile_id',
        'document_type',
        'file_name',
        'mime_type',
        'file_size',
        'storage_path',
        'verification_status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (KycDocument $document) {
            if (empty($document->id)) {
                $document->id = (string) Str::uuid();
            }
            if (empty($document->verification_status)) {
                $document->verification_status = self::VERIFICATION_PENDING;
            }
        });
    }

    public static function types(): array
    {
        return [
            self::TYPE_NIN,
            self::TYPE_BVN,
            self::TYPE_PASSPORT,
            self::TYPE_PASSPORT_DOCUMENT,
            self::TYPE_PASSPORT_PHOTO,
            self::TYPE_SELFIE,
            self::TYPE_ID_CARD_FRONT,
            self::TYPE_ID_CARD_BACK,
            self::TYPE_DRIVERS_LICENSE,
            self::TYPE_UTILITY_BILL,
            self::TYPE_PROOF_OF_ADDRESS,
            self::TYPE_APPOINTMENT_LETTER,
            self::TYPE_EMPLOYER_ID_CARD,
            self::TYPE_EMPLOYMENT_LETTER,
            self::TYPE_EMPLOYMENT_DOCUMENT,
            self::TYPE_PAYSLIP_DOCUMENT,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::VERIFICATION_PENDING,
            self::VERIFICATION_APPROVED,
            self::VERIFICATION_REJECTED,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }
}
