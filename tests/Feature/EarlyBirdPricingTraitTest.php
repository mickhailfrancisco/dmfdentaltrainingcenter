<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarlyBirdPricingTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_and_program_have_no_early_bird_when_price_early_is_null(): void
    {
        $package = Package::factory()->create([
            'price_full' => 10000,
            'price_early' => null,
            'early_deadline' => null,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $program = Program::factory()->create([
            'price_full' => 10000,
            'price_early' => null,
            'early_deadline' => null,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $this->assertFalse($package->isFirstEarlyBirdActive());
        $this->assertFalse($package->isEarlyBirdActive());
        $this->assertSame(10000, $package->active_price);

        $this->assertFalse($program->isFirstEarlyBirdActive());
        $this->assertFalse($program->isEarlyBirdActive());
        $this->assertSame(10000, $program->active_price);
    }

    public function test_active_price_returns_early_bird_price_when_deadline_is_in_future(): void
    {
        $deadline = now('Asia/Manila')->addDays(3)->toDateString();

        $package = Package::factory()->create([
            'price_full' => 10000,
            'price_early' => 8000,
            'early_deadline' => $deadline,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $program = Program::factory()->create([
            'price_full' => 10000,
            'price_early' => 8000,
            'early_deadline' => $deadline,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $this->assertTrue($package->isFirstEarlyBirdActive());
        $this->assertSame(8000, $package->active_price);

        $this->assertTrue($program->isFirstEarlyBirdActive());
        $this->assertSame(8000, $program->active_price);
    }

    public function test_active_price_returns_full_price_when_early_bird_deadline_has_passed(): void
    {
        $pastDeadline = now('Asia/Manila')->subDay()->toDateString();

        $package = Package::factory()->create([
            'price_full' => 10000,
            'price_early' => 8000,
            'early_deadline' => $pastDeadline,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $program = Program::factory()->create([
            'price_full' => 10000,
            'price_early' => 8000,
            'early_deadline' => $pastDeadline,
            'price_early_2' => null,
            'early_deadline_2' => null,
        ]);

        $this->assertFalse($package->isFirstEarlyBirdActive());
        $this->assertSame(10000, $package->active_price);

        $this->assertFalse($program->isFirstEarlyBirdActive());
        $this->assertSame(10000, $program->active_price);
    }

    public function test_second_early_bird_is_active_only_when_first_has_passed(): void
    {
        $pastDeadline = now('Asia/Manila')->subDay()->toDateString();
        $futureDeadline = now('Asia/Manila')->addDays(3)->toDateString();

        $package = Package::factory()->create([
            'price_full' => 10000,
            'price_early' => 8000,
            'early_deadline' => $pastDeadline,
            'price_early_2' => 9000,
            'early_deadline_2' => $futureDeadline,
        ]);

        $this->assertFalse($package->isFirstEarlyBirdActive());
        $this->assertTrue($package->isSecondEarlyBirdActive());
        $this->assertSame(9000, $package->active_price);

        $program = Program::factory()->create([
            'price_full' => 10000,
            'price_early' => 8000,
            'early_deadline' => $pastDeadline,
            'price_early_2' => 9000,
            'early_deadline_2' => $futureDeadline,
        ]);

        $this->assertFalse($program->isFirstEarlyBirdActive());
        $this->assertTrue($program->isSecondEarlyBirdActive());
        $this->assertSame(9000, $program->active_price);
    }

    public function test_downpayment_amount_is_fifty_percent_of_list_price(): void
    {
        $package = Package::factory()->create(['price_full' => 10000]);
        $program = Program::factory()->create(['price_full' => 10000]);

        $this->assertSame(5000, $package->downpayment_amount);
        $this->assertSame(5000, $program->downpayment_amount);
    }
}
