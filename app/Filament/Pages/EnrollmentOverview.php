<?php

namespace App\Filament\Pages;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Support\PermissionCodes;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class EnrollmentOverview extends Page
{
    protected static ?string $slug = 'enrollment-overview';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Operations';

    protected static ?string $navigationGroup = 'Enrollment';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Operations overview';

    protected static string $view = 'filament.pages.enrollment-overview';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        foreach (PermissionCodes::enrollmentListAccessPermissionCodes() as $code) {
            if ($user->hasPermission($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    public function getStatCounts(): array
    {
        $purposeInitial = Payment::PURPOSE_INITIAL;
        $pending = EnrollmentStatus::PENDING->value;
        $partiallyPaid = EnrollmentStatus::PARTIALLY_PAID->value;

        $row = Enrollment::query()
            ->toBase()
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND amount_paid_tuition <= 0 AND NOT EXISTS (
                    SELECT 1 FROM payments p
                    WHERE p.enrollment_id = enrollments.id
                    AND p.purpose = ?
                    AND p.payment_method = ?
                    AND p.status = ?
                ) THEN 1 ELSE 0 END) as awaiting_payment,
                SUM(CASE WHEN status = ? AND EXISTS (
                    SELECT 1 FROM payments p
                    WHERE p.enrollment_id = enrollments.id
                    AND p.purpose = ?
                    AND p.payment_method = ?
                    AND p.status = ?
                ) THEN 1 ELSE 0 END) as pending_verification,
                SUM(CASE WHEN status = ? OR (
                    payment_type = ?
                    AND amount_paid_tuition > 0
                    AND balance_tuition_due > 0
                ) THEN 1 ELSE 0 END) as balance_due',
                [
                    $pending,
                    $purposeInitial,
                    'bank_transfer',
                    'submitted',
                    $pending,
                    $purposeInitial,
                    'bank_transfer',
                    'submitted',
                    $partiallyPaid,
                    'downpayment',
                ],
            )
            ->first();

        return [
            'awaiting_payment' => (int) ($row->awaiting_payment ?? 0),
            'pending_verification' => (int) ($row->pending_verification ?? 0),
            'balance_due' => (int) ($row->balance_due ?? 0),
        ];
    }

    public function getFilteredListUrl(string $tab): string
    {
        return EnrollmentResource::getUrl('index', [
            'activeTab' => $tab,
        ]);
    }
}
