<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard;

use Illuminate\Database\Connection;
use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\StatementAnalyser;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Guard\GuardScope;
use Swis\Laravel\DatabaseGuard\Guard\StatementDenied;

/**
 * Analyses every statement on the attached connections while a guarded scope runs.
 */
final class DatabaseGuard
{
    /**
     * @var \WeakMap<Connection, true>
     */
    private \WeakMap $attached;

    /**
     * @var \WeakMap<Connection, AnalysisContext>
     */
    private \WeakMap $contexts;

    private ?GuardScope $scope = null;

    private bool $analysing = false;

    /** @param ExemptFrom[] $defaultExemptions */
    public function __construct(
        private readonly StatementAnalyser $analyser,
        private readonly array $defaultExemptions,
        private readonly \Closure $warn,
        private readonly bool $warnUnused,
    ) {
        $this->attached = new \WeakMap;
        $this->contexts = new \WeakMap;
    }

    public function attach(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'mysql' || isset($this->attached[$connection])) {
            return;
        }

        $this->attached[$connection] = true;

        $connection->beforeExecuting(
            fn (string $query, array $bindings, Connection $conn) => $this->handle($query, $conn),
        );
    }

    /**
     * Forget what was learned about the connections, such as which tables were created
     * and their row counts, so every migration run starts from the actual database.
     */
    public function resetContexts(): void
    {
        $this->contexts = new \WeakMap;
    }

    public function context(Connection $connection): AnalysisContext
    {
        return $this->contexts[$connection] ??= new AnalysisContext($connection);
    }

    public function within(GuardScope $scope, \Closure $run): mixed
    {
        $previous = $this->scope;
        $this->scope = $scope;

        try {
            $result = $run();
        } finally {
            $this->scope = $previous;
        }

        if ($this->warnUnused) {
            foreach ($scope->unused() as $exemption) {
                $this->warn("Unused exemption in $scope->source: #[ExemptFrom] {$exemption->describe()}");
            }
        }

        return $result;
    }

    public function warn(string $message): void
    {
        ($this->warn)($message);
    }

    /**
     * @throws StatementDenied
     */
    private function handle(string $query, Connection $connection): void
    {
        $scope = $this->scope;

        // Outside a guarded scope (e.g. the migrations table insert) or re-entrant: skip.
        if ($scope === null || $this->analysing) {
            return;
        }

        $this->analysing = true;

        try {
            $context = $this->context($connection);
            $denied = [];

            foreach ($this->analyser->analyse($query, $context) as $violation) {
                if (! $this->isExempt($violation, $context, $scope)) {
                    $denied[] = $violation;
                }
            }
        } finally {
            $this->analysing = false;
        }

        if ($denied === []) {
            return;
        }

        $exception = new StatementDenied($scope->source, $denied);

        if ($connection->pretending()) {
            $this->warn($exception->getMessage());

            return;
        }

        throw $exception;
    }

    private function isExempt(Violation $violation, AnalysisContext $context, GuardScope $scope): bool
    {
        foreach ($this->activeExemptions($scope) as $exemption) {
            if (! $exemption->matches($violation, $context->connection->getTablePrefix())) {
                continue;
            }

            $scope->markUsed($exemption);

            $unmet = $exemption->unmetConditions($violation, $context);

            if ($unmet === []) {
                return true;
            }

            foreach ($unmet as $description) {
                $violation->recordUnmetConditions($description);
            }
        }

        return false;
    }

    /**
     * @return iterable<ExemptFrom>
     */
    private function activeExemptions(GuardScope $scope): iterable
    {
        return [...$this->defaultExemptions, ...$scope->exemptions];
    }
}
