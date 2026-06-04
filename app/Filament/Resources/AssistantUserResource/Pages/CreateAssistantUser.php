<?php

namespace App\Filament\Resources\AssistantUserResource\Pages;

use App\Filament\Resources\AssistantUserResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Support\PermissionCodes;

class CreateAssistantUser extends CreateRecord
{
    protected static string $resource = AssistantUserResource::class;

    /**
     * Permission codes from the form, applied after the user row exists.
     *
     * @var list<string>
     */
    protected array $pendingPermissionCodes = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$this->pendingPermissionCodes, $data] = PermissionCodes::extractPermissionCodesFromFormData($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissionsByCode($this->pendingPermissionCodes);
    }

    /**
     * After create, go to the assistants list — not the edit page (Filament default).
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * Success toast copy — clearer than the generic Filament "Created" title.
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Assistant Created';
    }
}
