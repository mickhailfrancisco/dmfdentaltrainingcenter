<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\EnrollmentAgreementSetting;
use App\Models\User;
use App\Services\EnrollmentAgreementSettingService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnrollmentAgreementSettingServiceTest extends TestCase
{
    public function test_normalize_download_basename_strips_extension(): void
    {
        $service = app(EnrollmentAgreementSettingService::class);

        $this->assertSame('DMF-Enrollment-Agreement', $service->normalizeDownloadBasename('DMF-Enrollment-Agreement.pdf'));
        $this->assertSame('DMF-Enrollment-Agreement', $service->normalizeDownloadBasename('DMF-Enrollment-Agreement'));
    }

    public function test_effective_download_filename_uses_uploaded_file_extension(): void
    {
        Storage::fake('dmf_s3');

        config([
            'enrollment.agreement.disk' => 'dmf_s3',
        ]);

        $admin = User::factory()->admin()->create();
        $path = 'enrollment-agreements/agreement.docx';
        Storage::disk('dmf_s3')->put($path, 'docx-file');

        app(EnrollmentAgreementSettingService::class)->update([
            'file_path' => $path,
            'download_filename' => 'DMF-Undertaking',
        ], $admin);

        $this->assertSame('DMF-Undertaking', EnrollmentAgreementSetting::query()->first()?->download_filename);
        $this->assertSame('DMF-Undertaking.docx', app(EnrollmentAgreementSettingService::class)->effectiveDownloadFilename());
    }
}
