<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;

class EnrollmentNotesService
{
    public function updateNotes(Enrollment $enrollment, ?string $notes): void
    {
        $enrollment->update([
            'notes' => filled($notes) ? $notes : null,
        ]);
    }
}
