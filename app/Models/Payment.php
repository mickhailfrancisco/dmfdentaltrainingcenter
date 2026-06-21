<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\EnrollmentFinancialService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    public const PURPOSE_INITIAL = 'initial';

    public const PURPOSE_BALANCE = 'balance';

    protected $fillable = [
        'enrollment_id',
        'purpose',
        'paymongo_checkout_session_id',
        'paymongo_payment_intent_id',
        'paymongo_payment_id',
        'payment_method',
        'amount',
        'currency',
        'tuition_amount',
        'status',
        'paid_at',
        'paymongo_payload',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'paymongo_payload' => 'array',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function bankTransferSubmission(): HasOne
    {
        return $this->hasOne(BankTransferSubmission::class)->with('files');
    }

    /**
     * Mark paid and sync enrollment ledger (prefer PaymongoService webhook/sync path).
     */
    public function markPaid(string $paymongoPaymentId, array $rawPayload = []): void
    {
        $this->update([
            'status' => 'paid',
            'paymongo_payment_id' => $paymongoPaymentId,
            'paid_at' => now(),
            'paymongo_payload' => $rawPayload,
        ]);

        app(EnrollmentFinancialService::class)->recalculateEnrollmentFinancials($this->enrollment);
    }
}
