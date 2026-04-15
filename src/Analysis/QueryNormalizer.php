<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Analysis;

/**
 * Normalizes SQL queries for deduplication and N+1 detection.
 *
 * Two queries are considered "the same query template" when their normalized
 * form matches — meaning literals (strings, numbers, placeholders) have been
 * replaced with a single canonical token.
 */
class QueryNormalizer
{
    /**
     * Normalize SQL by stripping all literal values so that structurally
     * identical queries (e.g. `WHERE id = 1` and `WHERE id = 42`) produce
     * the same hash.
     */
    public function normalize(string $sql): string
    {
        $normalized = $sql;

        // Replace single-quoted string literals ('...')
        // Do NOT replace double-quoted identifiers ("..."") — in PostgreSQL these
        // are table/column names, not values. Replacing them would collapse
        // structurally different queries into the same hash.
        $normalized = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "'?'", $normalized) ?? $normalized;

        // Replace numeric literals (integers and floats) that are not part of identifiers
        $normalized = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $normalized) ?? $normalized;

        // Replace bound-parameter placeholders that still have values (e.g. $1, :name)
        $normalized = preg_replace('/\$\d+/', '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/:[a-zA-Z_]\w*/', '?', $normalized) ?? $normalized;

        // Collapse whitespace
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;

        return $normalized;
    }

    /**
     * Produce a short hash that identifies a query template.
     * Used as a grouping key when detecting N+1 patterns.
     */
    public function hash(string $sql): string
    {
        return substr(md5($this->normalize($sql)), 0, 12);
    }

    /**
     * Extract the primary table name from a SQL string (best-effort).
     * Used to enrich N+1 reports with a human-readable label.
     */
    public function extractTable(string $sql): ?string
    {
        // FROM "table" or FROM table
        if (preg_match('/\bFROM\s+"?([a-zA-Z_:][a-zA-Z0-9_:]*)"?/i', $sql, $m)) {
            return $m[1];
        }

        // UPDATE "table" or DELETE FROM "table"
        if (preg_match('/\bUPDATE\s+"?([a-zA-Z_:][a-zA-Z0-9_:]*)"?/i', $sql, $m)) {
            return $m[1];
        }

        if (preg_match('/\bINSERT\s+INTO\s+"?([a-zA-Z_:][a-zA-Z0-9_:]*)"?/i', $sql, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extract the SQL operation type (SELECT, INSERT, UPDATE, DELETE, …).
     */
    public function extractOperation(string $sql): string
    {
        if (preg_match('/^\s*([A-Z]+)/i', $sql, $m)) {
            return strtoupper($m[1]);
        }

        return 'UNKNOWN';
    }
}
