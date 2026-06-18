<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Central registry of permission codes stored in `permissions` and assigned per assistant.
 *
 * @author CKD
 *
 * @created 2026-04-25
 */
final class PermissionCodes
{
    public const ENROLLMENT_DETAIL_APPLICANT_PROFILE = 'enrollment.detail.applicant_profile';

    public const ENROLLMENT_DETAIL_ACADEMIC = 'enrollment.detail.academic';

    public const ENROLLMENT_DETAIL_HOME_ADDRESS = 'enrollment.detail.home_address';

    public const ENROLLMENT_DETAIL_PLAN_CHECKOUT = 'enrollment.detail.plan_checkout';

    public const ENROLLMENT_DETAIL_PLAN_FINANCIAL = 'enrollment.detail.plan_financial';

    public const ENROLLMENT_DETAIL_TUITION_BALANCE = 'enrollment.detail.tuition_balance';

    public const ENROLLMENT_LIST_FIRST_PAYMENT = 'enrollment.list.first_payment';

    public const ENROLLMENT_LIST_EXPORT = 'enrollment.list.export';

    public const ENROLLMENT_ACTION_COPY_PAY_BALANCE_LINK = 'enrollment.action.copy_pay_balance_link';

    public const ENROLLMENT_ACTION_COPY_PAY_INITIAL_LINK = 'enrollment.action.copy_pay_initial_link';

    public const ENROLLMENT_ACTION_COPY_BANK_TRANSFER_LINK = 'enrollment.action.copy_bank_transfer_link';

    public const ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER = 'enrollment.action.verify_bank_transfer';

    public const ENROLLMENT_ACTION_REFRESH_PAYMENT_TOTALS = 'enrollment.action.refresh_payment_totals';

    public const ENROLLMENT_RELATION_PAYMENTS = 'enrollment.relation.payments';

    public const CATALOG_CATEGORIES_VIEW = 'catalog.categories.view';

    public const CATALOG_CATEGORIES_CREATE = 'catalog.categories.create';

    public const CATALOG_CATEGORIES_UPDATE = 'catalog.categories.update';

    public const CATALOG_CATEGORIES_DELETE = 'catalog.categories.delete';

    public const CATALOG_PACKAGES_VIEW = 'catalog.packages.view';

    public const CATALOG_PACKAGES_CREATE = 'catalog.packages.create';

    public const CATALOG_PACKAGES_UPDATE = 'catalog.packages.update';

    public const CATALOG_PACKAGES_DELETE = 'catalog.packages.delete';

    public const CATALOG_PROGRAMS_VIEW = 'catalog.programs.view';

    public const CATALOG_PROGRAMS_CREATE = 'catalog.programs.create';

    public const CATALOG_PROGRAMS_UPDATE = 'catalog.programs.update';

    public const CATALOG_PROGRAMS_DELETE = 'catalog.programs.delete';

    public const CATALOG_SCHEDULES_VIEW = 'catalog.schedules.view';

    public const CATALOG_SCHEDULES_CREATE = 'catalog.schedules.create';

    public const CATALOG_SCHEDULES_UPDATE = 'catalog.schedules.update';

    public const CATALOG_SCHEDULES_DELETE = 'catalog.schedules.delete';

    /**
     * Permissions that allow viewing the enrollment list or operations overview.
     *
     * @return list<string>
     */
    public static function enrollmentListAccessPermissionCodes(): array
    {
        return array_keys(array_filter(
            self::definitions(),
            static fn (string $label, string $code): bool => str_starts_with($code, 'enrollment.'),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Permissions that allow opening a single enrollment record (view page: infolist and/or Payments tab).
     *
     * Assistants without any of these still use the list but get 403 on /enrollments/{id}.
     *
     * @return list<string>
     */
    public static function enrollmentRecordViewPermissionCodes(): array
    {
        return [
            self::ENROLLMENT_DETAIL_APPLICANT_PROFILE,
            self::ENROLLMENT_DETAIL_ACADEMIC,
            self::ENROLLMENT_DETAIL_HOME_ADDRESS,
            self::ENROLLMENT_DETAIL_PLAN_CHECKOUT,
            self::ENROLLMENT_DETAIL_PLAN_FINANCIAL,
            self::ENROLLMENT_DETAIL_TUITION_BALANCE,
            self::ENROLLMENT_RELATION_PAYMENTS,
            self::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER,
        ];
    }

    /**
     * Permissions that show the Payments tab on an enrollment record.
     *
     * Verify bank transfer implies tab access — assistants cannot verify without seeing payments.
     *
     * @return list<string>
     */
    public static function enrollmentPaymentsTabAccessCodes(): array
    {
        return [
            self::ENROLLMENT_RELATION_PAYMENTS,
            self::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER,
        ];
    }

    /**
     * Code => label for seeding and admin UI.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            self::ENROLLMENT_DETAIL_APPLICANT_PROFILE => 'Enrollment — Applicant profile section',
            self::ENROLLMENT_DETAIL_ACADEMIC => 'Enrollment — Academic background section',
            self::ENROLLMENT_DETAIL_HOME_ADDRESS => 'Enrollment — Home address section',
            self::ENROLLMENT_DETAIL_PLAN_CHECKOUT => 'Enrollment — Plan & checkout (status, reference, program, plan type, dates)',
            self::ENROLLMENT_DETAIL_PLAN_FINANCIAL => 'Enrollment — First checkout amounts (base, fee, total)',
            self::ENROLLMENT_DETAIL_TUITION_BALANCE => 'Enrollment — Tuition & balance section',
            self::ENROLLMENT_LIST_FIRST_PAYMENT => 'Enrollment list — First payment column',
            self::ENROLLMENT_LIST_EXPORT => 'Enrollment list — Export CSV',
            self::ENROLLMENT_ACTION_COPY_PAY_BALANCE_LINK => 'Enrollment — Copy payment link (table & record)',
            self::ENROLLMENT_ACTION_COPY_PAY_INITIAL_LINK => 'Enrollment — Copy initial payment link (legacy)',
            self::ENROLLMENT_ACTION_COPY_BANK_TRANSFER_LINK => 'Enrollment — Copy bank transfer link (legacy)',
            self::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER => 'Enrollment — Verify bank transfer payments',
            self::ENROLLMENT_ACTION_REFRESH_PAYMENT_TOTALS => 'Enrollment — Refresh payment totals (enrollment record)',
            self::ENROLLMENT_RELATION_PAYMENTS => 'Enrollment record — Payments tab',
            self::CATALOG_CATEGORIES_VIEW => 'Catalog — Categories (view)',
            self::CATALOG_CATEGORIES_CREATE => 'Catalog — Categories (create)',
            self::CATALOG_CATEGORIES_UPDATE => 'Catalog — Categories (edit)',
            self::CATALOG_CATEGORIES_DELETE => 'Catalog — Categories (delete)',
            self::CATALOG_PACKAGES_VIEW => 'Catalog — Packages (view)',
            self::CATALOG_PACKAGES_CREATE => 'Catalog — Packages (create)',
            self::CATALOG_PACKAGES_UPDATE => 'Catalog — Packages (edit)',
            self::CATALOG_PACKAGES_DELETE => 'Catalog — Packages (delete)',
            self::CATALOG_PROGRAMS_VIEW => 'Catalog — Programs (view)',
            self::CATALOG_PROGRAMS_CREATE => 'Catalog — Programs (create)',
            self::CATALOG_PROGRAMS_UPDATE => 'Catalog — Programs (edit)',
            self::CATALOG_PROGRAMS_DELETE => 'Catalog — Programs (delete)',
            self::CATALOG_SCHEDULES_VIEW => 'Catalog — Schedules (view)',
            self::CATALOG_SCHEDULES_CREATE => 'Catalog — Schedules (create)',
            self::CATALOG_SCHEDULES_UPDATE => 'Catalog — Schedules (edit)',
            self::CATALOG_SCHEDULES_DELETE => 'Catalog — Schedules (delete)',
        ];
    }

    /**
     * Preset matching pre-permission assistant behavior (for migrations / tests).
     *
     * @return list<string>
     */
    public static function legacyAssistantPreset(): array
    {
        return [
            self::ENROLLMENT_DETAIL_APPLICANT_PROFILE,
            self::ENROLLMENT_DETAIL_ACADEMIC,
            self::ENROLLMENT_DETAIL_HOME_ADDRESS,
            self::ENROLLMENT_DETAIL_PLAN_CHECKOUT,
            self::ENROLLMENT_RELATION_PAYMENTS,
            self::ENROLLMENT_ACTION_COPY_PAY_BALANCE_LINK,
            self::ENROLLMENT_ACTION_COPY_PAY_INITIAL_LINK,
            self::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER,
            self::ENROLLMENT_ACTION_REFRESH_PAYMENT_TOTALS,
            self::CATALOG_CATEGORIES_VIEW,
            self::CATALOG_CATEGORIES_CREATE,
            self::CATALOG_CATEGORIES_UPDATE,
            self::CATALOG_CATEGORIES_DELETE,
            self::CATALOG_PACKAGES_VIEW,
            self::CATALOG_PACKAGES_CREATE,
            self::CATALOG_PACKAGES_UPDATE,
            self::CATALOG_PACKAGES_DELETE,
            self::CATALOG_PROGRAMS_VIEW,
            self::CATALOG_PROGRAMS_CREATE,
            self::CATALOG_PROGRAMS_UPDATE,
            self::CATALOG_PROGRAMS_DELETE,
            self::CATALOG_SCHEDULES_VIEW,
            self::CATALOG_SCHEDULES_CREATE,
            self::CATALOG_SCHEDULES_UPDATE,
            self::CATALOG_SCHEDULES_DELETE,
        ];
    }

    /**
     * Livewire form field names for permission CheckboxLists (not User attributes).
     *
     * @return list<string>
     */
    public static function permissionFormFieldNames(): array
    {
        return [
            'perm_enrollment_sections',
            'perm_enrollment_tools',
            'perm_catalog_categories',
            'perm_catalog_packages',
            'perm_catalog_programs',
            'perm_catalog_schedules',
        ];
    }

    /**
     * Enrollment record / infolist sections (checkbox options for the assistant form).
     *
     * @return array<string, string>
     */
    public static function bucketEnrollmentSections(): array
    {
        return [
            self::ENROLLMENT_DETAIL_APPLICANT_PROFILE => 'Applicant profile',
            self::ENROLLMENT_DETAIL_ACADEMIC => 'Academic background',
            self::ENROLLMENT_DETAIL_HOME_ADDRESS => 'Home address',
            self::ENROLLMENT_DETAIL_PLAN_CHECKOUT => 'Plan & checkout (status, reference, program, dates)',
            self::ENROLLMENT_DETAIL_PLAN_FINANCIAL => 'First checkout amounts (base, fee, total)',
            self::ENROLLMENT_DETAIL_TUITION_BALANCE => 'Tuition & balance',
        ];
    }

    /**
     * Enrollment list tools, actions, and relation tabs.
     *
     * @return array<string, string>
     */
    public static function bucketEnrollmentTools(): array
    {
        return [
            self::ENROLLMENT_LIST_FIRST_PAYMENT => 'Enrollment list — First payment column',
            self::ENROLLMENT_LIST_EXPORT => 'Export enrollments (CSV)',
            self::ENROLLMENT_ACTION_COPY_PAY_BALANCE_LINK => 'Copy payment link (table & record)',
            self::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER => 'Verify bank transfers (Payments tab + actions)',
            self::ENROLLMENT_ACTION_REFRESH_PAYMENT_TOTALS => 'Refresh payment totals (enrollment record)',
            self::ENROLLMENT_RELATION_PAYMENTS => 'Enrollment record — Payments tab',
        ];
    }

    /**
     * Catalog CRUD toggles for one resource (View → Create → Edit → Delete).
     *
     * @param  'categories'|'packages'|'programs'|'schedules'  $resource
     * @return array<string, string>
     */
    public static function bucketCatalogOptions(string $resource): array
    {
        return match ($resource) {
            'categories' => [
                self::CATALOG_CATEGORIES_VIEW => 'View',
                self::CATALOG_CATEGORIES_CREATE => 'Create',
                self::CATALOG_CATEGORIES_UPDATE => 'Edit',
                self::CATALOG_CATEGORIES_DELETE => 'Delete',
            ],
            'packages' => [
                self::CATALOG_PACKAGES_VIEW => 'View',
                self::CATALOG_PACKAGES_CREATE => 'Create',
                self::CATALOG_PACKAGES_UPDATE => 'Edit',
                self::CATALOG_PACKAGES_DELETE => 'Delete',
            ],
            'programs' => [
                self::CATALOG_PROGRAMS_VIEW => 'View',
                self::CATALOG_PROGRAMS_CREATE => 'Create',
                self::CATALOG_PROGRAMS_UPDATE => 'Edit',
                self::CATALOG_PROGRAMS_DELETE => 'Delete',
            ],
            'schedules' => [
                self::CATALOG_SCHEDULES_VIEW => 'View',
                self::CATALOG_SCHEDULES_CREATE => 'Create',
                self::CATALOG_SCHEDULES_UPDATE => 'Edit',
                self::CATALOG_SCHEDULES_DELETE => 'Delete',
            ],
            default => throw new \InvalidArgumentException('Unknown catalog resource: '.$resource),
        };
    }

    /**
     * Map stored permission codes to form state for assistant create/edit.
     *
     * @param  list<string>  $codes
     * @return array<string, list<string>>
     */
    public static function expandCodesToPermissionFormState(array $codes): array
    {
        $set = array_flip($codes);
        $pick = static function (array $options) use ($set): array {
            return array_values(array_filter(
                array_keys($options),
                static fn (string $c): bool => isset($set[$c]),
            ));
        };

        return [
            'perm_enrollment_sections' => $pick(self::bucketEnrollmentSections()),
            'perm_enrollment_tools' => $pick(self::bucketEnrollmentTools()),
            'perm_catalog_categories' => $pick(self::bucketCatalogOptions('categories')),
            'perm_catalog_packages' => $pick(self::bucketCatalogOptions('packages')),
            'perm_catalog_programs' => $pick(self::bucketCatalogOptions('programs')),
            'perm_catalog_schedules' => $pick(self::bucketCatalogOptions('schedules')),
        ];
    }

    /**
     * Collect permission codes from bucket fields and remove those keys so User mass-assignment stays safe.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    public static function extractPermissionCodesFromFormData(array $data): array
    {
        $codes = [];
        foreach (self::permissionFormFieldNames() as $key) {
            $chunk = $data[$key] ?? [];
            if (is_array($chunk)) {
                foreach ($chunk as $c) {
                    if (is_string($c) && $c !== '') {
                        $codes[] = $c;
                    }
                }
            }
            unset($data[$key]);
        }

        return [array_values(array_unique($codes)), $data];
    }
}
