<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EnrollmentFinancialService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnrollmentFinancialServiceTest extends TestCase
{
    #[DataProvider('driverExpressionProvider')]
    public function test_tuition_sum_expression_uses_correct_scalar_max_function(string $driver, string $expectedFragment, ?string $forbiddenFragment): void
    {
        $expression = EnrollmentFinancialService::tuitionSumExpressionForDriver($driver);

        $this->assertStringContainsString($expectedFragment, $expression);

        if ($forbiddenFragment !== null) {
            $this->assertStringNotContainsString($forbiddenFragment, $expression);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function driverExpressionProvider(): array
    {
        return [
            'pgsql uses GREATEST' => ['pgsql', 'GREATEST', null],
            'mysql uses GREATEST not aggregate MAX' => ['mysql', 'GREATEST', 'MAX(0'],
            'sqlite uses MAX scalar' => ['sqlite', 'MAX(0', null],
        ];
    }
}
