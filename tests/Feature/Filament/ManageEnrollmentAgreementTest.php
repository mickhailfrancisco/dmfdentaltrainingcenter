<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManageEnrollmentAgreement;
use App\Models\EnrollmentAgreementSetting;
use App\Models\User;
use App\Services\EnrollmentAgreementSettingService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManageEnrollmentAgreementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Storage::fake('dmf_s3');

        config([
            'enrollment.agreement.disk' => 'dmf_s3',
            'enrollment.agreement.storage_directory' => 'enrollment-agreements',
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_access_enrollment_agreement_page(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        Livewire::test(ManageEnrollmentAgreement::class)
            ->assertSuccessful()
            ->assertSee('Enrollment agreement')
            ->assertSee('Download name');
    }

    public function test_assistant_cannot_access_enrollment_agreement_page(): void
    {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant);

        Livewire::test(ManageEnrollmentAgreement::class)
            ->assertForbidden();
    }

    public function test_admin_can_upload_agreement_to_storage(): void
    {
        $admin = $this->makeAdmin();
        $upload = UploadedFile::fake()->create('DMF-Agreement.pdf', 100, 'application/pdf');

        $this->actingAs($admin);

        Livewire::test(ManageEnrollmentAgreement::class)
            ->fillForm([
                'download_filename' => 'DMF-Enrollment-Agreement.pdf',
                'submission_email' => 'enrollment@dmfdental.com',
                'file_path' => $upload,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $setting = EnrollmentAgreementSetting::query()->first();

        $this->assertNotNull($setting);
        $this->assertSame('DMF-Enrollment-Agreement', $setting->download_filename);
        $this->assertSame('enrollment@dmfdental.com', $setting->submission_email);
        $this->assertSame('DMF-Enrollment-Agreement.pdf', app(EnrollmentAgreementSettingService::class)->effectiveDownloadFilename());
        $this->assertNotNull($setting->file_path);
        $this->assertStringStartsWith('enrollment-agreements/', (string) $setting->file_path);
        $this->assertSame($admin->getKey(), $setting->updated_by_user_id);
        Storage::disk('dmf_s3')->assertExists((string) $setting->file_path);
    }

    public function test_replacing_agreement_deletes_previous_storage_object(): void
    {
        $admin = $this->makeAdmin();
        $service = app(EnrollmentAgreementSettingService::class);

        $firstPath = 'enrollment-agreements/first-agreement.pdf';
        $secondPath = 'enrollment-agreements/second-agreement.pdf';

        Storage::disk('dmf_s3')->put($firstPath, 'first-file');
        Storage::disk('dmf_s3')->put($secondPath, 'second-file');

        $service->update([
            'file_path' => $firstPath,
            'download_filename' => 'First.pdf',
            'submission_email' => 'first@example.com',
        ], $admin);

        $service->update([
            'file_path' => $secondPath,
            'download_filename' => 'Second.pdf',
            'submission_email' => 'second@example.com',
        ], $admin);

        Storage::disk('dmf_s3')->assertMissing($firstPath);
        Storage::disk('dmf_s3')->assertExists($secondPath);
        $this->assertSame($secondPath, EnrollmentAgreementSetting::query()->first()?->file_path);
    }

    public function test_saving_without_new_upload_preserves_existing_agreement_path(): void
    {
        $admin = $this->makeAdmin();
        $service = app(EnrollmentAgreementSettingService::class);
        $path = 'enrollment-agreements/existing.pdf';

        Storage::disk('dmf_s3')->put($path, 'existing-file');

        $service->update([
            'file_path' => $path,
            'download_filename' => 'Existing.pdf',
            'submission_email' => 'agreements@example.com',
        ], $admin);

        $service->update([
            'download_filename' => 'Existing-Renamed.pdf',
            'submission_email' => 'updated@example.com',
        ], $admin);

        $this->assertSame($path, EnrollmentAgreementSetting::query()->first()?->file_path);
        $this->assertSame('Existing-Renamed', EnrollmentAgreementSetting::query()->first()?->download_filename);
        $this->assertSame('Existing-Renamed.pdf', app(EnrollmentAgreementSettingService::class)->effectiveDownloadFilename());
        $this->assertSame('updated@example.com', EnrollmentAgreementSetting::query()->first()?->submission_email);
        Storage::disk('dmf_s3')->assertExists($path);
    }

    public function test_admin_can_update_submission_email_without_reuploading_agreement(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        Livewire::test(ManageEnrollmentAgreement::class)
            ->fillForm([
                'download_filename' => 'DMF-Enrollment-Agreement.pdf',
                'submission_email' => 'custom@dmfdental.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('custom@dmfdental.com', EnrollmentAgreementSetting::query()->first()?->submission_email);
    }

    public function test_replacing_file_type_updates_download_extension(): void
    {
        $admin = $this->makeAdmin();
        $service = app(EnrollmentAgreementSettingService::class);

        $pdfPath = 'enrollment-agreements/agreement.pdf';
        $docxPath = 'enrollment-agreements/agreement.docx';

        Storage::disk('dmf_s3')->put($pdfPath, 'pdf-file');
        Storage::disk('dmf_s3')->put($docxPath, 'docx-file');

        $service->update([
            'file_path' => $pdfPath,
            'download_filename' => 'DMF-Enrollment-Agreement',
            'submission_email' => 'enrollment@dmfdental.com',
        ], $admin);

        $this->assertSame('DMF-Enrollment-Agreement.pdf', $service->effectiveDownloadFilename());

        $service->update([
            'file_path' => $docxPath,
            'download_filename' => 'DMF-Enrollment-Agreement',
        ], $admin);

        $this->assertSame('DMF-Enrollment-Agreement.docx', $service->effectiveDownloadFilename());
        Storage::disk('dmf_s3')->assertMissing($pdfPath);
    }
}
