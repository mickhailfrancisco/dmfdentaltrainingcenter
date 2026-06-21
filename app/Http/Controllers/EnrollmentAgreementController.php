<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EnrollmentAgreementService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnrollmentAgreementController extends Controller
{
    /**
     * Download the static enrollment agreement PDF for a valid enrollment.
     *
     * @param  string  $reference_number  From the enrollment success page route.
     *
     * @author CKD
     *
     * @created 2026-06-19
     *
     * @modified 2026-06-19 CKD
     */
    public function download(string $reference_number, EnrollmentAgreementService $service): BinaryFileResponse
    {
        return $service->download($reference_number);
    }
}
