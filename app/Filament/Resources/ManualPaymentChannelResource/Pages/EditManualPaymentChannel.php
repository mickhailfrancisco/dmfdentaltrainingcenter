<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManualPaymentChannelResource\Pages;

use App\Filament\Resources\ManualPaymentChannelResource;
use App\Models\ManualPaymentChannel;
use App\Services\ManualPaymentChannelService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditManualPaymentChannel extends EditRecord
{
    protected static string $resource = ManualPaymentChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ManualPaymentChannel $record */
        $record = $this->getRecord();

        if (ManualPaymentChannelResource::shouldDeferQrPathToUploadWidget($record)) {
            $data['qr_path'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var ManualPaymentChannel $record */
        $record = $this->getRecord();

        if (! array_key_exists('qr_path', $data) || filled($data['qr_path'])) {
            return $data;
        }

        if (ManualPaymentChannelResource::shouldDeferQrPathToUploadWidget($record)) {
            unset($data['qr_path']);

            return $data;
        }

        $data['qr_path'] = null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ManualPaymentChannel $record */
        return app(ManualPaymentChannelService::class)->updateChannel($record, $data);
    }
}
