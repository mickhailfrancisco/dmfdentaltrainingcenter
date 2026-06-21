<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\ManualPaymentChannelResource;
use App\Filament\Resources\ManualPaymentChannelResource\Pages\EditManualPaymentChannel;
use App\Filament\Resources\ManualPaymentChannelResource\Pages\ListManualPaymentChannels;
use App\Models\ManualPaymentChannel;
use App\Models\User;
use App\Services\ManualPaymentChannelService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManualPaymentChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Storage::fake('dmf_s3');
        config([
            'manual-payment.disk' => 'dmf_s3',
            'manual-payment.qr_directory' => 'manual-payment/qr',
            'manual-payment.logo_directory' => 'manual-payment/logos',
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_migration_seeds_four_payment_channels(): void
    {
        $this->assertDatabaseCount('manual_payment_channels', 4);

        $this->assertDatabaseHas('manual_payment_channels', [
            'channel_code' => ManualPaymentChannel::CHANNEL_BDO,
            'type' => ManualPaymentChannel::TYPE_BANK_TRANSFER,
        ]);

        $this->assertDatabaseHas('manual_payment_channels', [
            'channel_code' => ManualPaymentChannel::CHANNEL_PALAWAN_EXPRESS,
            'type' => ManualPaymentChannel::TYPE_REMITTANCE,
        ]);
    }

    public function test_admin_can_list_payment_channels(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin);

        Livewire::test(ListManualPaymentChannels::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(ManualPaymentChannel::all())
            ->assertSee('BDO');
    }

    public function test_admin_navigation_shows_payment_channels_link(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get('/admin/enrollments')
            ->assertSuccessful()
            ->assertSee('Payment channels');
    }

    public function test_admin_can_edit_bdo_account_fields(): void
    {
        $admin = $this->makeAdmin();
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_BDO)
            ->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(EditManualPaymentChannel::class, ['record' => $channel->getKey()])
            ->fillForm([
                'display_name' => 'BDO',
                'account_name' => 'Updated Account Name',
                'account_number' => '9999-9999-9999',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $channel->refresh();

        $this->assertSame('Updated Account Name', $channel->account_name);
        $this->assertSame('9999-9999-9999', $channel->account_number);
        $this->assertSame('images/banks/logos/bdo.svg', $channel->logo_path);
    }

    public function test_edit_form_shows_qr_preview_action(): void
    {
        $admin = $this->makeAdmin();
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_BDO)
            ->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(EditManualPaymentChannel::class, ['record' => $channel->getKey()])
            ->assertSee('Preview QR code')
            ->assertSee('bdo_qr.jpg')
            ->assertSee('Current QR')
            ->assertSee('Replace QR image')
            ->assertSee('images/banks/qr/bdo_qr.jpg', false);
    }

    public function test_edit_form_can_open_qr_preview_modal(): void
    {
        $admin = $this->makeAdmin();
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_BDO)
            ->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(EditManualPaymentChannel::class, ['record' => $channel->getKey()])
            ->call('mountFormComponentAction', 'data.previewCurrentQrAction', 'previewCurrentQr')
            ->assertSet('mountedFormComponentActions', ['previewCurrentQr'])
            ->assertSee('images/banks/qr/bdo_qr.jpg', false);
    }

    public function test_saving_without_new_upload_preserves_legacy_qr_path(): void
    {
        $admin = $this->makeAdmin();
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_BDO)
            ->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(EditManualPaymentChannel::class, ['record' => $channel->getKey()])
            ->fillForm([
                'display_name' => 'BDO',
                'account_name' => $channel->account_name,
                'account_number' => $channel->account_number,
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('images/banks/qr/bdo_qr.jpg', $channel->fresh()->qr_path);
    }

    public function test_list_table_can_open_qr_preview_modal(): void
    {
        $admin = $this->makeAdmin();
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_BDO)
            ->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(ListManualPaymentChannels::class)
            ->mountTableAction('previewQr', $channel)
            ->assertSet('mountedTableActions', ['previewQr']);
    }

    public function test_qr_preview_modal_view_renders_channel_qr(): void
    {
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_BDO)
            ->firstOrFail();

        $html = ManualPaymentChannelResource::qrPreviewModalView($channel)->render();

        $this->assertStringContainsString('images/banks/qr/bdo_qr.jpg', $html);
        $this->assertStringContainsString('BDO', $html);
    }

    public function test_admin_qr_upload_stores_on_configured_disk(): void
    {
        $admin = $this->makeAdmin();
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_BDO)
            ->firstOrFail();

        $channel->update(['qr_path' => null]);
        $originalLogoPath = $channel->logo_path;

        $upload = UploadedFile::fake()->image('bdo-qr.jpg');

        $this->actingAs($admin);

        Livewire::test(EditManualPaymentChannel::class, ['record' => $channel->getKey()])
            ->assertFormFieldIsVisible('qr_path')
            ->fillForm([
                'display_name' => 'BDO',
                'account_name' => $channel->account_name,
                'account_number' => $channel->account_number,
                'is_active' => true,
                'qr_path' => $upload,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $channel->refresh();

        $this->assertNotNull($channel->qr_path);
        $this->assertStringStartsWith('manual-payment/qr/', (string) $channel->qr_path);
        $this->assertSame($originalLogoPath, $channel->logo_path);
        Storage::disk('dmf_s3')->assertExists((string) $channel->qr_path);
    }

    public function test_replacing_qr_deletes_previous_object_from_disk(): void
    {
        $service = app(ManualPaymentChannelService::class);
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_BPI)
            ->firstOrFail();

        $firstPath = 'manual-payment/qr/bpi-first.jpg';
        $secondPath = 'manual-payment/qr/bpi-second.jpg';

        Storage::disk('dmf_s3')->put($firstPath, 'first-image');
        Storage::disk('dmf_s3')->put($secondPath, 'second-image');

        $service->updateChannel($channel, [
            'display_name' => $channel->display_name,
            'account_name' => $channel->account_name,
            'account_number' => $channel->account_number,
            'is_active' => true,
            'qr_path' => $firstPath,
        ]);

        Storage::disk('dmf_s3')->assertExists($firstPath);

        $service->updateChannel($channel->fresh(), [
            'display_name' => $channel->display_name,
            'account_name' => $channel->account_name,
            'account_number' => $channel->account_number,
            'is_active' => true,
            'qr_path' => $secondPath,
        ]);

        Storage::disk('dmf_s3')->assertMissing($firstPath);
        Storage::disk('dmf_s3')->assertExists($secondPath);
        $this->assertSame($secondPath, $channel->fresh()->qr_path);
    }

    public function test_legacy_public_paths_are_not_deleted_on_replace(): void
    {
        $service = app(ManualPaymentChannelService::class);
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_CHINABANK)
            ->firstOrFail();

        $legacyPath = 'images/banks/qr/chinabank_qr.jpg';
        $newPath = 'manual-payment/qr/chinabank-new.jpg';

        Storage::disk('dmf_s3')->put($newPath, 'new-image');

        $service->updateChannel($channel, [
            'display_name' => $channel->display_name,
            'account_name' => $channel->account_name,
            'account_number' => $channel->account_number,
            'is_active' => true,
            'qr_path' => $newPath,
        ]);

        $service->deletePreviousAsset($legacyPath);

        $this->assertSame($newPath, $channel->fresh()->qr_path);
    }

    public function test_assistant_cannot_access_payment_channels_resource(): void
    {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant);

        $this->assertFalse(ManualPaymentChannelResource::canViewAny());

        Livewire::test(ListManualPaymentChannels::class)
            ->assertForbidden();
    }

    public function test_admin_can_edit_palawan_remittance_fields(): void
    {
        $admin = $this->makeAdmin();
        $channel = ManualPaymentChannel::query()
            ->where('channel_code', ManualPaymentChannel::CHANNEL_PALAWAN_EXPRESS)
            ->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(EditManualPaymentChannel::class, ['record' => $channel->getKey()])
            ->fillForm([
                'receiver_name' => 'New Receiver',
                'contact_number' => '09123456789',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $channel->refresh();

        $this->assertSame('New Receiver', $channel->receiver_name);
        $this->assertSame('09123456789', $channel->contact_number);
    }
}
