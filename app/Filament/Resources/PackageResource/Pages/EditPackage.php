<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PackageResource\Concerns\SyncsPackageProgramSortOrderFromForm;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPackage extends EditRecord
{
    use SyncsPackageProgramSortOrderFromForm;

    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncPackageProgramSortOrder();
    }
}
