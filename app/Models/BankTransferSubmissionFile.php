<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransferSubmissionFile extends Model
{
    public const SLOT_PHOTO_1 = 'photo_1';

    public const SLOT_PHOTO_2 = 'photo_2';

    protected $fillable = [
        'bank_transfer_submission_id',
        'slot',
        'path',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(BankTransferSubmission::class, 'bank_transfer_submission_id');
    }
}
