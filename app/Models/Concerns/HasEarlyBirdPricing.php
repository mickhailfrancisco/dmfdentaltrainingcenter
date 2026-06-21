<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Shared early bird pricing methods for Program and Package models.
 */
trait HasEarlyBirdPricing
{
    public function isFirstEarlyBirdActive(): bool
    {
        return $this->price_early !== null
            && $this->early_deadline !== null
            && now()->timezone('Asia/Manila')->startOfDay()->lte($this->early_deadline);
    }

    public function isSecondEarlyBirdActive(): bool
    {
        if ($this->isFirstEarlyBirdActive()) {
            return false;
        }

        return $this->price_early_2 !== null
            && $this->early_deadline_2 !== null
            && now()->timezone('Asia/Manila')->startOfDay()->lte($this->early_deadline_2);
    }

    public function isEarlyBirdActive(): bool
    {
        return $this->isFirstEarlyBirdActive() || $this->isSecondEarlyBirdActive();
    }

    public function getActivePriceAttribute(): int
    {
        if ($this->isFirstEarlyBirdActive()) {
            return (int) $this->price_early;
        }

        if ($this->isSecondEarlyBirdActive()) {
            return (int) $this->price_early_2;
        }

        return (int) $this->price_full;
    }

    public function getDownpaymentAmountAttribute(): int
    {
        return (int) round(((int) $this->price_full) * 0.5);
    }
}
