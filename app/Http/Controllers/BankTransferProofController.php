<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BankTransferSubmission;
use App\Models\BankTransferSubmissionFile;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankTransferProofController extends Controller
{
    public function show(BankTransferSubmission $submission, ?string $slot = null): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (! $user->isAdmin() && ! $user->hasPermission(PermissionCodes::ENROLLMENT_RELATION_PAYMENTS)) {
            abort(403);
        }

        $slot = $slot ?: BankTransferSubmissionFile::SLOT_PHOTO_1;
        abort_unless(in_array($slot, [BankTransferSubmissionFile::SLOT_PHOTO_1, BankTransferSubmissionFile::SLOT_PHOTO_2], true), 404);

        $file = $submission->files()->where('slot', $slot)->first();
        $path = (string) ($file?->path ?: $submission->proof_path);

        abort_unless(filled($path), 404);

        return Storage::disk('local')->response(
            $path,
            headers: [
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
