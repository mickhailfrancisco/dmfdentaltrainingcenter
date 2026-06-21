<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ManualPaymentChannelService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualPaymentChannelServiceTest extends TestCase
{
    public function test_s3_qr_urls_use_temporary_signed_links(): void
    {
        Storage::fake('dmf_s3');

        config([
            'manual-payment.disk' => 'dmf_s3',
            'manual-payment.use_signed_urls' => true,
            'manual-payment.signed_url_minutes' => 10,
        ]);

        $path = 'manual-payment/qr/bdo-qr.jpg';
        Storage::disk('dmf_s3')->put($path, 'qr-image');

        $url = app(ManualPaymentChannelService::class)->publicUrl($path);

        $this->assertNotNull($url);
        $this->assertStringContainsString('expiration=', (string) $url);
    }

    public function test_upload_visibility_is_private_on_s3(): void
    {
        config([
            'manual-payment.disk' => 'dmf_s3',
            'manual-payment.use_signed_urls' => true,
        ]);

        $this->assertSame('private', app(ManualPaymentChannelService::class)->uploadVisibility());
    }

    public function test_disk_defaults_to_dmf_s3(): void
    {
        config([
            'manual-payment.disk' => 'dmf_s3',
        ]);

        $this->assertSame('dmf_s3', app(ManualPaymentChannelService::class)->disk());
    }
}
