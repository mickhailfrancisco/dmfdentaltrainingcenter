<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankTransferSubmission extends Model
{
    protected $fillable = [
        'payment_id',
        'reference_number',
        'proof_path',
        'submitted_at',
        'manual_method',
        'channel_code',
        'verified_at',
        'verified_by',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BankTransferSubmissionFile::class, 'bank_transfer_submission_id');
    }
}
