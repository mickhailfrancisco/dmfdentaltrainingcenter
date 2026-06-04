<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PackageResource\Concerns\SyncsPackageProgramSortOrderFromForm;
use App\Filament\Resources\Pages\CreateRecord;

class CreatePackage extends CreateRecord
{
    use SyncsPackageProgramSortOrderFromForm;

    protected static string $resource = PackageResource::class;

    protected function afterCreate(): void
    {
        $this->syncPackageProgramSortOrder();
    }
}
