<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\YearsOfExcellence;
use Carbon\Carbon;
use Tests\TestCase;

class YearsOfExcellenceTest extends TestCase
{
    public function test_it_counts_one_year_on_the_starting_date(): void
    {
        $this->assertSame(1, YearsOfExcellence::asOf(Carbon::parse('2017-02-01')));
    }

    public function test_it_does_not_increment_before_february_first(): void
    {
        $this->assertSame(9, YearsOfExcellence::asOf(Carbon::parse('2026-01-31')));
    }

    public function test_it_increments_exactly_on_february_first(): void
    {
        $this->assertSame(10, YearsOfExcellence::asOf(Carbon::parse('2026-02-01')));
    }

    public function test_it_holds_steady_for_the_rest_of_the_year(): void
    {
        $this->assertSame(10, YearsOfExcellence::asOf(Carbon::parse('2026-08-26')));
        $this->assertSame(10, YearsOfExcellence::asOf(Carbon::parse('2026-12-31')));
    }
}
