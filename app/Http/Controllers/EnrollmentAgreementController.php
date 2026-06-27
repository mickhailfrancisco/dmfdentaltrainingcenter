<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EnrollmentAgreementService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentAgreementController extends Controller
{
    /**
     * Download the enrollment agreement for a valid enrollment reference.
     */
    public function download(string $reference_number, EnrollmentAgreementService $service): StreamedResponse|BinaryFileResponse
    {
        return $service->download($reference_number);
    }
}
