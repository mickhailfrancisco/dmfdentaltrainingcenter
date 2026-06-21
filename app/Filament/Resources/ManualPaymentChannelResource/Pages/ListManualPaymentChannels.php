<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManualPaymentChannelResource\Pages;

use App\Filament\Resources\ManualPaymentChannelResource;
use Filament\Resources\Pages\ListRecords;

class ListManualPaymentChannels extends ListRecords
{
    protected static string $resource = ManualPaymentChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
