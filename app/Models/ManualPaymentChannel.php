<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ManualPaymentChannelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManualPaymentChannel extends Model
{
    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    public const TYPE_REMITTANCE = 'remittance';

    public const CHANNEL_BDO = 'bdo';

    public const CHANNEL_BPI = 'bpi';

    public const CHANNEL_CHINABANK = 'chinabank';

    public const CHANNEL_PALAWAN_EXPRESS = 'palawan_express';

    /**
     * Fixed bank display order for admin list and future student checkout.
     *
     * @var list<string>
     */
    public const BANK_DISPLAY_ORDER = [
        self::CHANNEL_BDO,
        self::CHANNEL_BPI,
        self::CHANNEL_CHINABANK,
    ];

    protected $fillable = [
        'channel_code',
        'type',
        'display_name',
        'account_name',
        'account_number',
        'receiver_name',
        'contact_number',
        'logo_path',
        'qr_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBankTransfer(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BANK_TRANSFER);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRemittance(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_REMITTANCE);
    }

    public function isBankTransfer(): bool
    {
        return $this->type === self::TYPE_BANK_TRANSFER;
    }

    public function isRemittance(): bool
    {
        return $this->type === self::TYPE_REMITTANCE;
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(ManualPaymentChannelService::class)->forgetCache());
        static::deleted(fn () => app(ManualPaymentChannelService::class)->forgetCache());
    }
}
