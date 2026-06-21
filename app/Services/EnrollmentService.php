<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\EnrollmentItem;
use App\Models\Package;
use App\Models\Program;
use App\Models\Schedule;
use Illuminate\Support\Collection;

class EnrollmentService
{
    /**
     * Get all active programs grouped by category for the frontend.
     */
    public function getGroupedActivePrograms(): Collection
    {
        return Program::query()
            ->where('is_active', true)
            ->with([
                'categoryModel:id,name',
                'schedules' => fn ($q) => $q->where('is_active', true)->orderBy('created_at', 'desc'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (Program $program) => $program->category_label);
    }

    /**
     * Create a new enrollment record from validated form data.
     */
    public function createEnrollment(array $data): Enrollment
    {
        $slug = (string) ($data['program'] ?? '');
        $purchasable = Package::query()->where('slug', $slug)->first()
            ?? Program::query()->where('slug', $slug)->firstOrFail();

        $scheduleId = $this->normalizeOptionalInt($data['schedule_id'] ?? null);
        if ($purchasable instanceof Program) {
            if (empty($scheduleId)) {
                $activeSchedules = $purchasable->schedules()
                    ->where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->limit(2)
                    ->get(['id']);

                if ($activeSchedules->count() === 1) {
                    $scheduleId = $activeSchedules->first()->id;
                }
            }
        } else {
            $scheduleId = null;
        }

        $baseAmount = ($data['payment_type'] === 'full')
            ? (int) $purchasable->active_price
            : (int) $purchasable->downpayment_amount;

        $convenienceFee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;
        $totalAmount = $baseAmount + $convenienceFee;

        $list = (int) $purchasable->price_full;
        $early = $purchasable->price_early !== null ? (int) $purchasable->price_early : null;
        $deadline = $purchasable->early_deadline;
        $early2 = $purchasable->price_early_2 !== null ? (int) $purchasable->price_early_2 : null;
        $deadline2 = $purchasable->early_deadline_2;
        $dpSnapshot = (int) $purchasable->downpayment_amount;

        $activePrice = (int) $purchasable->active_price;
        $discountAmount = max(0, $list - $activePrice);
        $discountLabel = null;
        if ($discountAmount > 0) {
            $discountLabel = $purchasable->isFirstEarlyBirdActive() ? 'Early bird' : 'Early bird (2nd)';
        }

        $facebookMessengerName = $data['facebook_messenger_name'] ?? $data['facebook'] ?? null;
        $facebookMessengerUrl = $data['facebook_messenger_url'] ?? null;

        $enrollment = Enrollment::create([
            'reference_number' => Enrollment::generateReference(),
            'status' => 'pending',

            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'surname' => $data['surname'],
            'birthday' => $data['birthday'],
            'sex' => $data['sex'],

            'phone' => $data['phone'],
            'email' => $data['email'],
            'facebook_messenger_name' => $facebookMessengerName,
            'facebook_messenger_url' => $facebookMessengerUrl,

            'addr_street' => $data['addr_street'],
            'addr_city' => $data['addr_city'],
            'addr_province' => $data['addr_province'],
            'addr_zip' => $data['addr_zip'],
            'deliv_street' => $data['deliv_street'] ?? null,
            'deliv_city' => $data['deliv_city'] ?? null,
            'deliv_province' => $data['deliv_province'] ?? null,
            'deliv_zip' => $data['deliv_zip'] ?? null,

            'school' => $data['school'],
            'year_level' => $data['year_level'],
            'year_graduated' => $data['year_graduated'] ?? null,
            'taker_status' => $data['taker_status'],

            'purchasable_type' => $purchasable::class,
            'purchasable_id' => $purchasable->getKey(),
            'purchasable_name_snapshot' => (string) $purchasable->name,
            'purchasable_slug_snapshot' => (string) $purchasable->slug,

            'payment_type' => $data['payment_type'],
            'base_amount' => $baseAmount,
            'convenience_fee' => $convenienceFee,
            'total_amount' => $totalAmount,

            'tuition_list_amount' => $list,
            'tuition_price_early' => $early,
            'tuition_early_deadline' => $deadline,
            'tuition_price_early_2' => $early2,
            'tuition_early_deadline_2' => $deadline2,
            'tuition_price_dp' => $dpSnapshot,
            'tuition_discount_amount' => $discountAmount,
            'tuition_discount_label' => $discountLabel,
            'amount_paid_tuition' => 0,
            'balance_tuition_due' => 0,
        ]);

        $enrollment->balance_tuition_due = EnrollmentPricingService::balanceTuitionDue($enrollment);
        $enrollment->saveQuietly();

        $this->createEnrollmentItems($enrollment, $purchasable, $scheduleId);

        return $enrollment;
    }

    private function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    public function createEnrollmentItemsPublic(Enrollment $enrollment, Program|Package $purchased, ?int $scheduleId): void
    {
        $this->createEnrollmentItems($enrollment, $purchased, $scheduleId);
    }

    private function createEnrollmentItems(Enrollment $enrollment, Program|Package $purchased, ?int $scheduleId): void
    {
        if ($purchased instanceof Package) {
            $includedPrograms = $purchased->programs()->where('is_active', true)->get();

            $rows = $includedPrograms->map(fn (Program $included) => [
                'enrollment_id' => $enrollment->getKey(),
                'program_id' => $included->getKey(),
                'schedule_id' => null,
                'status' => (string) $enrollment->status->value,
                'program_name_snapshot' => (string) $included->name,
                'program_slug_snapshot' => (string) $included->slug,
                'schedule_label_snapshot' => null,
                'schedule_mode_snapshot' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if (! empty($rows)) {
                EnrollmentItem::insert($rows);
            }

            return;
        }

        $schedule = $scheduleId ? Schedule::query()->find($scheduleId) : null;

        EnrollmentItem::query()->create([
            'enrollment_id' => $enrollment->getKey(),
            'program_id' => $purchased->getKey(),
            'schedule_id' => $scheduleId,
            'status' => (string) $enrollment->status->value,
            'program_name_snapshot' => (string) $purchased->name,
            'program_slug_snapshot' => (string) $purchased->slug,
            'schedule_label_snapshot' => $schedule?->label,
            'schedule_mode_snapshot' => $schedule?->mode,
        ]);
    }

    public function getScheduleForEnrollmentData(array $data): ?Schedule
    {
        $scheduleId = $this->normalizeOptionalInt($data['schedule_id'] ?? null);
        if (empty($scheduleId)) {
            return null;
        }

        return Schedule::query()->with('program')->find($scheduleId);
    }
}
