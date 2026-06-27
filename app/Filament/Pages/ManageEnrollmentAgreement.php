<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\EnrollmentAgreementSettingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class ManageEnrollmentAgreement extends Page implements HasForms
{
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Enrollment agreement';

    protected static ?string $title = 'Enrollment agreement';

    protected static ?int $navigationSort = 97;

    protected static string $view = 'filament.pages.manage-enrollment-agreement';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user?->isAdmin()) {
            Log::warning('Unauthorized access attempt to ManageEnrollmentAgreement page', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
            ]);

            return false;
        }

        return true;
    }

    public function mount(): void
    {
        $settings = app(EnrollmentAgreementSettingService::class);

        $this->form->fill([
            'download_filename' => $settings->effectiveDownloadBasename(),
            'submission_email' => $settings->effectiveSubmissionEmail(),
            'file_path' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        $settings = app(EnrollmentAgreementSettingService::class);

        return $form
            ->schema([
                Forms\Components\Section::make('Current agreement')
                    ->schema([
                        Forms\Components\Placeholder::make('current_agreement_file')
                            ->label('Stored file')
                            ->content(function () use ($settings): HtmlString|string {
                                if (! $settings->hasStoredFile()) {
                                    return 'No file uploaded yet. Students will receive the legacy file from server storage until you upload one here.';
                                }

                                $path = (string) $settings->current()?->file_path;

                                return new HtmlString(
                                    '<span class="font-mono text-sm text-gray-700">'.e(basename($path)).'</span>'
                                );
                            }),

                        Forms\Components\TextInput::make('download_filename')
                            ->label('Download name')
                            ->required()
                            ->maxLength(255)
                            ->helperText(function () use ($settings): string {
                                $downloadName = $settings->effectiveDownloadFilename();

                                return filled($downloadName)
                                    ? "Students will download this file as {$downloadName}. Enter the name only—the .pdf, .doc, or .docx extension comes from the uploaded file."
                                    : 'Enter the download name only. The file extension comes from the uploaded agreement.';
                            }),

                        Forms\Components\FileUpload::make('file_path')
                            ->label(fn (): string => $settings->hasStoredFile()
                                ? 'Replace agreement file'
                                : 'Agreement file')
                            ->helperText('PDF or Word, max 10 MB. Uploading a new file deletes the previous one from storage.')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->disk($settings->disk())
                            ->directory($settings->directory())
                            ->visibility($settings->uploadVisibility())
                            ->maxSize(10240)
                            ->moveFiles(),
                    ]),

                Forms\Components\Section::make('Where students send the signed form')
                    ->schema([
                        Forms\Components\TextInput::make('submission_email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Shown on the enrollment success page only. Students email their signed agreement to this address from their own inbox—the website does not send emails for them.'),
                    ])
                    ->description('After enrolling, students are told to download the agreement, sign it, and email a copy to this address.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();

        app(EnrollmentAgreementSettingService::class)->update($data, $user);

        $this->form->fill([
            'download_filename' => app(EnrollmentAgreementSettingService::class)->effectiveDownloadBasename(),
            'submission_email' => app(EnrollmentAgreementSettingService::class)->effectiveSubmissionEmail(),
            'file_path' => null,
        ]);

        Notification::make()
            ->title('Enrollment agreement saved')
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }
}
