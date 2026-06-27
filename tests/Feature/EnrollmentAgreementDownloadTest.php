<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Program;
use App\Models\User;
use App\Services\EnrollmentAgreementSettingService;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnrollmentAgreementDownloadTest extends TestCase
{
    private string $agreementPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agreementPath = storage_path('app/enrollment-agreements/enrollment-agreement-test.pdf');
        File::ensureDirectoryExists(dirname($this->agreementPath));
        File::put($this->agreementPath, '%PDF-1.4 test agreement');

        config([
            'enrollment.agreement.path' => $this->agreementPath,
            'enrollment.agreement.default_submission_email' => 'agreements@example.com',
            'enrollment.agreement.download_filename' => 'DMF-Enrollment-Agreement.pdf',
        ]);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->agreementPath)) {
            File::delete($this->agreementPath);
        }

        parent::tearDown();
    }

    public function test_download_returns_pdf_attachment_for_valid_reference(): void
    {
        $enrollment = $this->createEnrollment();

        $response = $this->get(route('enroll.agreement.download', [
            'reference_number' => $enrollment->reference_number,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=DMF-Enrollment-Agreement.pdf');
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_download_returns_docx_attachment_for_valid_reference(): void
    {
        $docxPath = storage_path('app/enrollment-agreements/enrollment-agreement-test.docx');
        File::ensureDirectoryExists(dirname($docxPath));
        File::put($docxPath, 'PK docx test');

        config([
            'enrollment.agreement.path' => $docxPath,
            'enrollment.agreement.download_filename' => 'DMF-Undertaking-December-2025-Lecture.docx',
        ]);

        $enrollment = $this->createEnrollment();

        $response = $this->get(route('enroll.agreement.download', [
            'reference_number' => $enrollment->reference_number,
        ]));

        $response->assertOk();
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=DMF-Undertaking-December-2025-Lecture.docx'
        );
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        File::delete($docxPath);
    }

    public function test_download_returns_not_found_for_invalid_reference(): void
    {
        $response = $this->get(route('enroll.agreement.download', [
            'reference_number' => 'DMF-NOT-REAL',
        ]));

        $response->assertNotFound();
    }

    public function test_download_returns_service_unavailable_when_agreement_file_is_missing(): void
    {
        File::delete($this->agreementPath);

        $enrollment = $this->createEnrollment();

        $response = $this->get(route('enroll.agreement.download', [
            'reference_number' => $enrollment->reference_number,
        ]));

        $response->assertStatus(503);
    }

    public function test_download_streams_agreement_from_admin_storage_disk(): void
    {
        Storage::fake('dmf_s3');

        config([
            'enrollment.agreement.disk' => 'dmf_s3',
            'enrollment.agreement.storage_directory' => 'enrollment-agreements',
        ]);

        $admin = User::factory()->admin()->create();
        $path = 'enrollment-agreements/stored-agreement.pdf';
        Storage::disk('dmf_s3')->put($path, '%PDF-1.4 stored agreement');

        app(EnrollmentAgreementSettingService::class)->update([
            'file_path' => $path,
            'download_filename' => 'DMF-Stored-Agreement',
            'submission_email' => 'stored@example.com',
        ], $admin);

        $enrollment = $this->createEnrollment();

        $response = $this->get(route('enroll.agreement.download', [
            'reference_number' => $enrollment->reference_number,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=DMF-Stored-Agreement.pdf');
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_success_page_shows_agreement_download_and_submission_instructions(): void
    {
        $enrollment = $this->createEnrollment();

        $response = $this->get(route('enroll.success', [
            'ref' => $enrollment->reference_number,
        ]));

        $response->assertOk();
        $response->assertSee('Download Agreement');
        $response->assertDontSee('Enroll another person');
        $response->assertSee('agreements@example.com');
        $response->assertSee($enrollment->reference_number);
        $response->assertSee('Download the enrollment agreement using the button below.');
        $response->assertSee('Sign the form (print and scan, or sign digitally)');
        $response->assertSee(route('enroll.agreement.download', [
            'reference_number' => $enrollment->reference_number,
        ]), false);
    }

    private function createEnrollment(): Enrollment
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01', 'Asia/Manila')->startOfDay());

        Program::create([
            'name' => 'Agreement Program',
            'slug' => 'agreement-program',
            'category' => 'Individual Programs (Theoretical)',
            'tag' => null,
            'price_full' => 30_000,
            'price_early' => 24_000,
            'early_deadline' => '2026-07-15',
            'early_bird_label' => 'Early',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return app(EnrollmentService::class)->createEnrollment([
            'program' => 'agreement-program',
            'schedule_id' => null,
            'first_name' => 'Agreement',
            'middle_name' => null,
            'surname' => 'Student',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'student@example.com',
            'facebook_messenger_name' => 'Agreement Student',
            'facebook_messenger_url' => null,
            'addr_street' => '1 Main',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'deliv_street' => null,
            'deliv_city' => null,
            'deliv_province' => null,
            'deliv_zip' => null,
            'school' => 'U',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',
            'payment_type' => 'full',
        ]);
    }
}
