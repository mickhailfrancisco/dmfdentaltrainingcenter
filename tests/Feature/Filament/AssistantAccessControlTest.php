<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AssistantUserResource;
use App\Filament\Resources\AssistantUserResource\Pages\CreateAssistantUser;
use App\Filament\Resources\AssistantUserResource\Pages\EditAssistantUser;
use App\Filament\Resources\AssistantUserResource\Pages\ListAssistantUsers;
use App\Filament\Resources\EnrollmentResource;
use App\Filament\Resources\EnrollmentResource\Pages\ListEnrollments;
use App\Filament\Resources\EnrollmentResource\Pages\ViewEnrollment;
use App\Filament\Resources\EnrollmentResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\PackageResource;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\User;
use App\Support\PermissionCodes;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests for assistant access control within the Filament admin panel.
 *
 * Covers:
 * - Panel access (admin and assistant allowed; no-role user denied)
 * - AssistantUserResource gated to admin only
 * - EnrollmentResource accessible to assistants with monetary fields masked
 *
 * @author CKD
 *
 * @created 2026-04-24
 */
class AssistantAccessControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (PermissionCodes::definitions() as $code => $label) {
            Permission::query()->firstOrCreate(
                ['code' => $code],
                ['label' => $label],
            );
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeAssistant(): User
    {
        $user = User::factory()->assistant()->create();
        $user->syncPermissionsByCode(PermissionCodes::legacyAssistantPreset());

        return $user;
    }

    /**
     * Assistant account with no row-level permissions (empty pivot).
     */
    private function makeAssistantWithoutPermissions(): User
    {
        $user = User::factory()->assistant()->create();
        $user->syncPermissionsByCode([]);

        return $user;
    }

    /**
     * Creates a minimal enrollment row for table/infolist visibility tests.
     * reference_number is capped at 20 chars per the DB varchar(20) constraint.
     * All NOT NULL columns without defaults are included.
     */
    private function makeEnrollment(): Enrollment
    {
        return Enrollment::create([
            'reference_number' => 'DMF-T-'.substr(uniqid(), -8),
            'status' => EnrollmentStatus::CONFIRMED->value,
            'first_name' => 'Test',
            'surname' => 'Student',
            'email' => 'student-'.uniqid().'@test.com',
            'phone' => '09000000000',
            'birthday' => '2000-01-01',
            'sex' => 'Male',
            'addr_street' => '123 Test St',
            'addr_city' => 'Quezon City',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1100',
            'school' => 'Test University',
            'year_level' => 'Graduate',
            'taker_status' => 'First Taker',
            'payment_type' => 'full',
            'base_amount' => 38500,
            'convenience_fee' => 500,
            'total_amount' => 39000,
            'purchasable_name_snapshot' => 'Package A',
            'purchasable_slug_snapshot' => 'package-a',
            'purchasable_type' => 'App\\Models\\Package',
            'purchasable_id' => 1,
            'tuition_list_amount' => 38500,
        ]);
    }

    // ── Panel access ──────────────────────────────────────────────────────────

    public function test_admin_can_access_panel(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue($admin->canAccessPanel(Filament::getCurrentPanel()));
    }

    public function test_assistant_can_access_panel(): void
    {
        $assistant = $this->makeAssistant();

        $this->assertTrue($assistant->canAccessPanel(Filament::getCurrentPanel()));
    }

    public function test_assistant_with_non_admin_email_cannot_pretend_to_be_admin(): void
    {
        $assistant = $this->makeAssistant();

        $this->assertFalse($assistant->isAdmin());
        $this->assertTrue($assistant->canAccessPanel(Filament::getCurrentPanel()));
    }

    // ── User model helpers ────────────────────────────────────────────────────

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue($admin->isAdmin());
    }

    public function test_is_assistant_returns_true_for_assistant_role(): void
    {
        $assistant = $this->makeAssistant();

        $this->assertTrue($assistant->isAssistant());
    }

    public function test_is_admin_returns_false_for_assistant(): void
    {
        $assistant = $this->makeAssistant();

        $this->assertFalse($assistant->isAdmin());
    }

    // ── AssistantUserResource access ──────────────────────────────────────────

    public function test_admin_can_see_assistant_list(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        Livewire::test(ListAssistantUsers::class)
            ->assertSuccessful();
    }

    public function test_assistant_cannot_see_assistant_list(): void
    {
        $assistant = $this->makeAssistant();

        $this->actingAs($assistant);

        Livewire::test(ListAssistantUsers::class)
            ->assertForbidden();
    }

    public function test_admin_can_create_assistant(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        $emailLocal = 'asst-'.uniqid();
        $emailFull = $emailLocal.'@dmfdental.com';

        Livewire::test(CreateAssistantUser::class)
            ->fillForm([
                'name' => 'New Assistant',
                'email' => $emailLocal,
                'password' => 'password123',
                'role' => UserRole::Assistant->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(User::class, [
            'email' => $emailFull,
            'role' => UserRole::Assistant->value,
        ]);
    }

    public function test_assistant_cannot_create_assistant(): void
    {
        $assistant = $this->makeAssistant();

        $this->actingAs($assistant);

        Livewire::test(CreateAssistantUser::class)
            ->assertForbidden();
    }

    // ── EnrollmentResource access ─────────────────────────────────────────────

    public function test_admin_can_see_enrollment_list(): void
    {
        $admin = $this->makeAdmin();
        $enrollment = $this->makeEnrollment();

        $this->actingAs($admin);

        Livewire::test(ListEnrollments::class)
            ->assertSuccessful()
            ->loadTable()
            ->assertCanSeeTableRecords([$enrollment]);
    }

    public function test_assistant_can_see_enrollment_list(): void
    {
        $assistant = $this->makeAssistant();
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        Livewire::test(ListEnrollments::class)
            ->assertSuccessful()
            ->loadTable()
            ->assertCanSeeTableRecords([$enrollment]);
    }

    public function test_admin_sees_total_amount_column(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        Livewire::test(ListEnrollments::class)
            ->assertTableColumnVisible('total_amount');
    }

    public function test_assistant_cannot_see_total_amount_column(): void
    {
        $assistant = $this->makeAssistant();

        $this->actingAs($assistant);

        Livewire::test(ListEnrollments::class)
            ->assertTableColumnHidden('total_amount');
    }

    public function test_assistant_can_search_enrollments(): void
    {
        $assistant = $this->makeAssistant();
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        Livewire::test(ListEnrollments::class)
            ->loadTable()
            ->searchTable($enrollment->first_name)
            ->assertCanSeeTableRecords([$enrollment]);
    }

    public function test_assistant_without_record_permissions_cannot_open_enrollment_view(): void
    {
        $assistant = $this->makeAssistantWithoutPermissions();
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        $this->assertFalse(EnrollmentResource::canView($enrollment));

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertForbidden();
    }

    public function test_assistant_without_record_permissions_cannot_see_enrollment_list(): void
    {
        $assistant = $this->makeAssistantWithoutPermissions();
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        $this->assertFalse(EnrollmentResource::canViewAny());

        Livewire::test(ListEnrollments::class)
            ->assertForbidden();
    }

    public function test_assistant_with_catalog_only_permissions_cannot_see_enrollment_list(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::CATALOG_PACKAGES_VIEW,
        ]);
        $this->makeEnrollment();

        $this->actingAs($assistant);

        $this->assertFalse(EnrollmentResource::canViewAny());

        Livewire::test(ListEnrollments::class)
            ->assertForbidden();
    }

    public function test_assistant_with_one_detail_permission_can_open_enrollment_view(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_APPLICANT_PROFILE,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        $this->assertTrue(EnrollmentResource::canView($enrollment));

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSuccessful();
    }

    public function test_assistant_with_payments_permission_only_can_open_enrollment_view(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_RELATION_PAYMENTS,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        $this->assertTrue(EnrollmentResource::canView($enrollment));

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSuccessful();
    }

    public function test_assistant_with_payments_permission_can_open_payments_relation_manager(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_RELATION_PAYMENTS,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        $this->assertTrue(PaymentsRelationManager::canViewForRecord($enrollment, ViewEnrollment::class));

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $enrollment,
            'pageClass' => ViewEnrollment::class,
        ])
            ->assertSuccessful()
            ->loadTable()
            ->assertTableColumnVisible('paid_at');
    }

    public function test_assistant_without_payments_permission_cannot_view_payments_relation_manager(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_APPLICANT_PROFILE,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        $this->assertFalse(PaymentsRelationManager::canViewForRecord($enrollment, ViewEnrollment::class));
    }

    public function test_assistant_with_verify_permission_can_view_payments_tab_without_payments_tab_permission(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_TUITION_BALANCE,
            PermissionCodes::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        $this->assertTrue(PaymentsRelationManager::canViewForRecord($enrollment, ViewEnrollment::class));

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $enrollment,
            'pageClass' => ViewEnrollment::class,
        ])
            ->assertSuccessful()
            ->loadTable()
            ->assertTableColumnVisible('paid_at');
    }

    public function test_assistant_with_refresh_payment_totals_permission_sees_action(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT,
            PermissionCodes::ENROLLMENT_ACTION_REFRESH_PAYMENT_TOTALS,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertActionVisible('refreshPaymentTotals');
    }

    public function test_assistant_without_refresh_payment_totals_permission_does_not_see_action(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSuccessful()
            ->assertDontSee('Refresh payment totals');
    }

    // ── Catalog bulk delete vs granular permissions ──────────────────────────

    public function test_assistant_partial_permissions_see_only_allowed_enrollment_sections(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_APPLICANT_PROFILE,
            PermissionCodes::ENROLLMENT_DETAIL_ACADEMIC,
            PermissionCodes::ENROLLMENT_DETAIL_TUITION_BALANCE,
        ]);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($assistant);

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSuccessful()
            ->assertSee('Applicant Profile')
            ->assertSee('Academic Background')
            ->assertSee('Tuition & balance')
            ->assertDontSee('Plan &amp; checkout', false)
            ->assertDontSee('Home Address');
    }

    public function test_assistant_with_packages_view_only_cannot_catalog_delete(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::CATALOG_PACKAGES_VIEW,
        ]);

        $this->actingAs($assistant);

        $this->assertTrue(PackageResource::canViewAny());
        $this->assertFalse(PackageResource::currentUserCanCatalogAction('delete'));
    }

    public function test_assistant_with_packages_delete_may_catalog_delete(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::CATALOG_PACKAGES_VIEW,
            PermissionCodes::CATALOG_PACKAGES_DELETE,
        ]);

        $this->actingAs($assistant);

        $this->assertTrue(PackageResource::currentUserCanCatalogAction('delete'));
    }

    public function test_assistant_without_export_permission_does_not_see_export_action(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_DETAIL_APPLICANT_PROFILE,
        ]);
        $this->makeEnrollment();

        $this->actingAs($assistant);

        Livewire::test(ListEnrollments::class)
            ->assertSuccessful()
            ->assertDontSee('Export CSV');
    }

    public function test_assistant_with_export_permission_sees_export_action(): void
    {
        $assistant = User::factory()->assistant()->create();
        $assistant->syncPermissionsByCode([
            PermissionCodes::ENROLLMENT_LIST_EXPORT,
        ]);
        $this->makeEnrollment();

        $this->actingAs($assistant);

        Livewire::test(ListEnrollments::class)
            ->assertSuccessful()
            ->assertActionExists('export')
            ->assertSee('Export CSV');
    }

    public function test_edit_assistant_persists_verify_bank_transfer_permission_when_row_was_missing(): void
    {
        Permission::query()
            ->where('code', PermissionCodes::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER)
            ->delete();

        $admin = $this->makeAdmin();
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($admin);

        Livewire::test(EditAssistantUser::class, ['record' => $assistant->getKey()])
            ->fillForm([
                'perm_enrollment_tools' => [
                    PermissionCodes::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER,
                ],
            ])
            ->call('save')
            ->assertNotified();

        $assistant->refresh();

        $this->assertDatabaseHas('permissions', [
            'code' => PermissionCodes::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER,
        ]);
        $this->assertTrue($assistant->hasPermission(PermissionCodes::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER));
    }

    // ── AssistantUserResource static authorization ────────────────────────────

    public function test_can_view_any_returns_false_for_assistant(): void
    {
        $assistant = $this->makeAssistant();

        $this->actingAs($assistant);

        $this->assertFalse(AssistantUserResource::canViewAny());
    }

    public function test_can_view_any_returns_true_for_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        $this->assertTrue(AssistantUserResource::canViewAny());
    }

    // ── Role correctness ──────────────────────────────────────────────────────

    public function test_newly_created_assistant_has_assistant_role(): void
    {
        $user = User::factory()->assistant()->create();

        $this->assertEquals(UserRole::Assistant, $user->role);
    }

    public function test_admin_factory_state_sets_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertEquals(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->isAdmin());
    }
}
