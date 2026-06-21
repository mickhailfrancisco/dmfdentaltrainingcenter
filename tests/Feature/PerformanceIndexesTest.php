<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceIndexesTest extends TestCase
{
    public function test_schedules_table_has_composite_index_on_program_id_and_is_active(): void
    {
        $indexes = collect(Schema::getIndexes('schedules'));

        $hasComposite = $indexes->contains(function (array $index) {
            return count($index['columns']) === 2
                && in_array('program_id', $index['columns'], true)
                && in_array('is_active', $index['columns'], true);
        });

        $this->assertTrue($hasComposite, 'schedules table is missing composite index on (program_id, is_active)');
    }

    public function test_programs_table_has_composite_index_on_is_active_and_sort_order(): void
    {
        $indexes = collect(Schema::getIndexes('programs'));

        $hasComposite = $indexes->contains(function (array $index) {
            return count($index['columns']) === 2
                && in_array('is_active', $index['columns'], true)
                && in_array('sort_order', $index['columns'], true);
        });

        $this->assertTrue($hasComposite, 'programs table is missing composite index on (is_active, sort_order)');
    }
}
