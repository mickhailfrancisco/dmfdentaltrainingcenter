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
        Storage::fake('s3');

        config([
            'manual-payment.disk' => 's3',
            'manual-payment.use_signed_urls' => true,
            'manual-payment.signed_url_minutes' => 10,
        ]);

        $path = 'manual-payment/qr/bdo-qr.jpg';
        Storage::disk('s3')->put($path, 'qr-image');

        $url = app(ManualPaymentChannelService::class)->publicUrl($path);

        $this->assertNotNull($url);
        $this->assertStringContainsString('expiration=', (string) $url);
    }

    public function test_public_disk_urls_do_not_use_signatures(): void
    {
        Storage::fake('public');

        config([
            'manual-payment.disk' => 'public',
            'manual-payment.use_signed_urls' => true,
        ]);

        $path = 'manual-payment/qr/bdo-qr.jpg';
        Storage::disk('public')->put($path, 'qr-image');

        $url = app(ManualPaymentChannelService::class)->publicUrl($path);

        $this->assertNotNull($url);
        $this->assertStringNotContainsString('X-Amz-Signature=', (string) $url);
        $this->assertStringNotContainsString('expiration=', (string) $url);
    }

    public function test_upload_visibility_is_private_on_s3(): void
    {
        config([
            'manual-payment.disk' => 's3',
            'manual-payment.use_signed_urls' => true,
        ]);

        $this->assertSame('private', app(ManualPaymentChannelService::class)->uploadVisibility());
    }

    public function test_upload_visibility_is_public_on_local_public_disk(): void
    {
        config([
            'manual-payment.disk' => 'public',
            'manual-payment.use_signed_urls' => true,
        ]);

        $this->assertSame('public', app(ManualPaymentChannelService::class)->uploadVisibility());
    }
}
