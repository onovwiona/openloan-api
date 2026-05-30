<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class LoanDocumentType extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'required',
        'active',
    ];

    protected $casts = [
        'required' => 'boolean',
        'active' => 'boolean',
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
     * Get loan products that require this document type
     */
    public function loanProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            LoanProduct::class,
            'loan_product_document_types',
            'document_type_id',
            'loan_product_id'
        );
    }
}
