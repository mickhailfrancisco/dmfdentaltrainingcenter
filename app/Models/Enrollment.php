<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Services\EnrollmentPricingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Enrollment extends Model
{
    protected $fillable = [
        // Reference & status
        'reference_number', 'status',

        // Personal info
        'first_name', 'middle_name', 'surname', 'birthday', 'sex',

        // Contact & address
        'phone', 'email',
        'facebook_messenger_name', 'facebook_messenger_url',
        'addr_street', 'addr_city', 'addr_province', 'addr_zip',
        'deliv_street', 'deliv_city', 'deliv_province', 'deliv_zip',

        // Academic
        'school', 'year_level', 'year_graduated', 'taker_status',

        // Purchased item (Program or Package)
        'purchasable_type', 'purchasable_id',
        'purchasable_name_snapshot', 'purchasable_slug_snapshot',

        // Payment
        'payment_type', 'base_amount', 'convenience_fee', 'total_amount',

        // Tuition snapshot & ledger
        'tuition_list_amount',
        'tuition_price_early',
        'tuition_price_early_2',
        'tuition_early_deadline_2',
        'tuition_early_deadline',
        'tuition_price_dp',
        'tuition_discount_amount',
        'tuition_discount_label',
        'amount_paid_tuition',
        'balance_tuition_due',
    ];

    protected $casts = [
        'birthday' => 'date',
        'status' => EnrollmentStatus::class,
        'tuition_early_deadline' => 'date',
        'tuition_early_deadline_2' => 'date',
    ];

    /* ── Relationships ─────────────────────────────────────────── */

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EnrollmentItem::class);
    }

    /* ── Helpers ───────────────────────────────────────────────── */

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->surname}");
    }

    /**
     * Backward compatibility for legacy attribute access (`facebook`).
     */
    public function getFacebookAttribute(): ?string
    {
        return $this->facebook_messenger_name;
    }

    /**
     * Backward compatibility for legacy attribute writes (`facebook`).
     */
    public function setFacebookAttribute(mixed $value): void
    {
        $this->attributes['facebook_messenger_name'] = $value === '' ? null : $value;
    }

    /**
     * Live remaining tuition (respects early-bird cutoff vs full list price).
     */
    public function getComputedBalanceTuitionDueAttribute(): int
    {
        return EnrollmentPricingService::balanceTuitionDue($this);
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'DMF-'.strtoupper(substr(md5(uniqid('', true)), 0, 8));
        } while (static::where('reference_number', $ref)->exists());

        return $ref;
    }
}
