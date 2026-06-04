<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackageResource\Concerns;

trait SyncsPackageProgramSortOrderFromForm
{
    protected function syncPackageProgramSortOrder(): void
    {
        $programIds = $this->form->getState()['programs'] ?? null;

        if (! is_array($programIds)) {
            return;
        }

        $this->record->syncProgramsSortOrder($programIds);
    }
}
