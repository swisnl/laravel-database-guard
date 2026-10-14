<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Analysis\ServerCapabilities;

#[CoversClass(ServerCapabilities::class)]
final class ServerCapabilitiesTest extends TestCase
{
    #[Test]
    #[DataProvider('versions')]
    public function it_reads_the_server_version(string $version, string $parsed, bool $mariaDb, bool $instantColumnAnywhere): void
    {
        $server = ServerCapabilities::fromVersion($version);

        $this->assertSame($parsed, $server->version);
        $this->assertSame($mariaDb, $server->isMariaDb());
        $this->assertSame($instantColumnAnywhere, $server->supportsInstantColumnAnywhere());
    }

    /**
     * @return iterable<string, array{string, string, bool, bool}>
     */
    public static function versions(): iterable
    {
        yield 'mysql 8.0.29' => ['8.0.29', '8.0.29', false, true];
        yield 'mysql 8.0.28' => ['8.0.28', '8.0.28', false, false];
        yield 'mysql 8.4 with suffix' => ['8.4.2-0ubuntu0.24.04.1', '8.4.2', false, true];
        yield 'mariadb' => ['11.4.2-MariaDB-ubu2404-log', '11.4.2', true, false];
        yield 'unknown' => ['unknown', '0.0.0', false, false];
    }
}
