<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Rules;

use Swis\Laravel\DatabaseGuard\Analysis\ServerCapabilities;

/** Static INSTANT / metadata-only classification of MySQL 8.0 ALTER TABLE clauses. */
final class InstantAlter
{
    public static function isInstant(string $clause, ServerCapabilities $server): bool
    {
        // Neutralize identifiers and string literals so names/comments can't match keywords.
        $clause = preg_replace("/'(?:[^'\\\\]|\\\\.)*'|`[^`]*`/s", "''", trim($clause))
            ?? throw new \RuntimeException('Could not classify ALTER TABLE clause: '.preg_last_error_msg());

        $matches = fn (string $pattern): bool => match (preg_match($pattern, $clause)) {
            1 => true,
            0 => false,
            default => throw new \RuntimeException('Could not classify ALTER TABLE clause: '.preg_last_error_msg()),
        };

        return match (true) {
            $matches('/^algorithm\s*=?\s*instant$/i') => true,
            // ADD COLUMN (not index/key/constraint/partition), unless it implies an index or a rebuild
            $matches('/^add\s+(column\s+)?(?!(index|key|unique|primary|fulltext|spatial|constraint|foreign|check|partition)\b)/i') => (
                ! $matches('/\b(auto_increment|primary\s+key|unique|stored|references)\b/i')
                && ($server->supportsInstantColumnAnywhere() || ! $matches('/\b(first|after)\b/i'))
            ),
            // In-place, metadata only.
            $matches('/^drop\s+(index|key|foreign\s+key)\b/i') => true,
            $matches('/^drop\s+(primary\s+key|check|constraint|partition)\b/i') => false,
            // // DROP [COLUMN]
            $matches('/^drop\s/i') => $server->supportsInstantColumnAnywhere(),
            // RENAME COLUMN/INDEX/KEY, RENAME [TO|AS] table.
            $matches('/^rename\s/i') => true,
            $matches("/^alter\s+(column\s+)?''\s+(set\s+default|drop\s+default|set\s+(visible|invisible))\b/i") => true,
            // Modify/change, add index, engine, charset conversion, unknown...
            default => false,
        };
    }

    /**
     * @param  string[]  $clauses
     */
    public static function isExplicitlyInstant(array $clauses): bool
    {
        foreach ($clauses as $clause) {
            if (preg_match('/^algorithm\s*=?\s*instant$/i', trim($clause))) {
                return true;
            }
        }

        return false;
    }
}
