<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Analysis;

final class StatementParser
{
    private const string TBL = '(?:`?([\w$]+)`?\.)?`?([\w$]+)`?';

    public function parse(string $sql): ParsedStatement
    {
        $s = $this->normalise($sql);
        $t = self::TBL;

        return match (true) {
            (bool) preg_match('/^create\s+(?:temporary\s+)?table\s+(?:if\s+not\s+exists\s+)?'.$t.'/i', $s, $m) => new ParsedStatement(
                StatementKind::CreateTable,
                $this->table($m, 1),
                [],
                $s,
            ),
            (bool) preg_match('/^alter\s+table\s+'.$t.'\s+(.+)$/is', $s, $m) => new ParsedStatement(
                StatementKind::AlterTable,
                $this->table($m, 1),
                $this->split($m[3]),
                $s,
            ),
            (bool) preg_match('/^create\s+(?:unique\s+|fulltext\s+|spatial\s+)?index\s+\S+\s+on\s+'.$t.'/is', $s, $m) => new ParsedStatement(
                StatementKind::CreateIndex,
                $this->table($m, 1),
                [],
                $s,
            ),
            (bool) preg_match('/^drop\s+index\s+\S+\s+on\s+'.$t.'/is', $s, $m) => new ParsedStatement(
                StatementKind::DropIndex,
                $this->table($m, 1),
                [],
                $s,
            ),
            (bool) preg_match('/^drop\s+table\s+(?:if\s+exists\s+)?'.$t.'$/is', $s, $m) => new ParsedStatement(
                StatementKind::DropTable,
                $this->table($m, 1),
                [],
                $s,
            ),
            (bool) preg_match('/^truncate\s+(?:table\s+)?'.$t.'$/is', $s, $m) => new ParsedStatement(
                StatementKind::Truncate,
                $this->table($m, 1),
                [],
                $s
            ),
            (bool) preg_match('/^rename\s+table\s+'.$t.'\s+to\s+/is', $s, $m) => new ParsedStatement(
                StatementKind::RenameTable,
                $this->table($m, 1),
                [],
                $s
            ),
            (bool) preg_match('/^set\s+(?:session\s+|@@session\.|@@)?(\w+)\s*=\s*([^,]+)$/is', $s, $m) => new ParsedStatement(
                StatementKind::SetVariable,
                null,
                [],
                $s,
                strtolower($m[1]),
                trim($m[2], " '\""),
            ),
            (bool) preg_match('/^update\s+(?:low_priority\s+|ignore\s+)*'.$t.'/is', $s, $m) => new ParsedStatement(
                StatementKind::Update,
                $this->table($m, 1),
                [],
                $s,
            ),
            (bool) preg_match('/^delete\s+(?:low_priority\s+|quick\s+|ignore\s+)*from\s+'.$t.'/is', $s, $m) => new ParsedStatement(
                StatementKind::Delete,
                $this->table($m, 1),
                [],
                $s
            ),
            (bool) preg_match('/^(?:insert|replace)\s+(?:\w+\s+)*?(?:into\s+)?'.$t.'/is', $s, $m) => new ParsedStatement(
                StatementKind::Insert,
                $this->table($m, 1),
                [],
                $s,
            ),
            default => new ParsedStatement(
                StatementKind::Other,
                null,
                [],
                $s,
            ),
        };
    }

    private function normalise(string $sql): string
    {
        // Block comments (keep /*! version hints) and whole-line comments only,
        // so '#' or '--' inside string literals survive.
        // A failed replacement must not turn the SQL into something harmless-looking.
        $sql = preg_replace('~/\*(?!!).*?\*/~s', ' ', $sql) ?? throw $this->parseError();
        $sql = preg_replace('~^\s*(?:--\s|#).*$~m', '', $sql) ?? throw $this->parseError();

        return rtrim(trim($sql), "; \t\n\r");
    }

    /**
     * @param  array<array-key, string>  $matches
     */
    private function table(array $matches, int $group): TableReference
    {
        return new TableReference($matches[$group + 1], $matches[$group] !== '' ? $matches[$group] : null);
    }

    private function parseError(): \RuntimeException
    {
        return new \RuntimeException('Could not parse SQL statement: '.preg_last_error_msg());
    }

    /**
     * Split on top-level commas (outside parens and quotes).
     *
     * @return list<string>
     */
    private function split(string $clause): array
    {
        $out = [];
        $buf = '';
        $depth = 0;
        $quote = null;

        foreach (str_split($clause) as $ch) {
            if ($quote !== null) {
                if ($ch === $quote) {
                    $quote = null;
                }
            } elseif (in_array($ch, ["'", '"', '`'], true)) {
                $quote = $ch;
            } elseif ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            } elseif ($ch === ',' && $depth === 0) {
                $out[] = trim($buf);
                $buf = '';

                continue;
            }

            $buf .= $ch;
        }

        $out[] = trim($buf);

        return array_values(array_filter($out, fn (string $part): bool => $part !== ''));
    }
}
