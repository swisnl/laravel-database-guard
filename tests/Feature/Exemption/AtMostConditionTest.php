<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Exemption;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Exemptions\AtMostCondition;
use Swis\Laravel\DatabaseGuard\Tests\Feature\TestCase;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Exemptions\ExposedAtMostCondition;

#[CoversClass(AtMostCondition::class)]
final class AtMostConditionTest extends TestCase
{
    #[Test]
    public function it_uses_the_declared_limit(): void
    {
        $this->assertSame(10, (new ExposedAtMostCondition(10))->limit($this->context()));
    }

    #[Test]
    public function it_uses_the_configured_default_without_a_limit(): void
    {
        $this->configure(['default' => 25]);

        $this->assertSame(25, (new ExposedAtMostCondition)->limit($this->context()));
    }

    #[Test]
    public function it_uses_zero_without_a_limit_or_default(): void
    {
        $this->assertSame(0, (new ExposedAtMostCondition)->limit($this->context()));
    }

    #[Test]
    public function it_caps_the_limit_at_the_configured_maximum(): void
    {
        $this->configure(['default' => 25, 'max' => 50]);

        $this->assertSame(50, (new ExposedAtMostCondition(1_000))->limit($this->context()));
        $this->assertSame(25, (new ExposedAtMostCondition)->limit($this->context()));
    }

    #[Test]
    public function it_rejects_invalid_settings(): void
    {
        $this->configure(['max' => 'lots']);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The database-guard.conditions setting max of '.ExposedAtMostCondition::class.' must be a non-negative integer, got string.');

        (new ExposedAtMostCondition(10))->limit($this->context());
    }

    #[Test]
    public function it_rejects_invalid_condition_settings(): void
    {
        config(['database-guard.conditions' => [ExposedAtMostCondition::class => 50]]);

        $this->expectException(\UnexpectedValueException::class);

        (new ExposedAtMostCondition(10))->limit($this->context());
    }

    #[Test]
    public function it_describes_the_limit(): void
    {
        $this->configure(['max' => 50]);

        $this->assertSame('10 things', (new ExposedAtMostCondition(10))->description($this->context()));
        $this->assertSame('50 things, capped from 1000 things by config', (new ExposedAtMostCondition(1_000))->description($this->context()));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function configure(array $settings): void
    {
        config(['database-guard.conditions' => [ExposedAtMostCondition::class => $settings]]);
    }

    private function context(): AnalysisContext
    {
        return new AnalysisContext(DB::connection());
    }
}
