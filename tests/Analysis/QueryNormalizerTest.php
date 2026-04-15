<?php

declare(strict_types=1);

use Mateffy\Laraperf\Analysis\QueryNormalizer;

it('normalizes numeric literals', function () {
    $normalizer = new QueryNormalizer;

    $result = $normalizer->normalize('SELECT * FROM users WHERE id = 42 AND age > 25');

    expect($result)->not->toContain('42')
        ->and($result)->not->toContain('25')
        ->and($result)->toContain('?');
});

it('normalizes string literals', function () {
    $normalizer = new QueryNormalizer;

    $result = $normalizer->normalize("SELECT * FROM users WHERE name = 'Alice' AND email = 'alice@example.com'");

    expect($result)->not->toContain('Alice')
        ->and($result)->not->toContain('alice@example.com')
        ->and($result)->toContain("'?'");
});

it('normalizes bound parameter placeholders', function () {
    $normalizer = new QueryNormalizer;

    $result = $normalizer->normalize('SELECT * FROM users WHERE id = $1 AND status = $2');

    expect($result)->toContain('?')
        ->and($result)->not->toContain('$1')
        ->and($result)->not->toContain('$2');
});

it('normalizes named parameter placeholders', function () {
    $normalizer = new QueryNormalizer;

    $result = $normalizer->normalize('SELECT * FROM users WHERE id = :user_id AND active = :active');

    expect($result)->toContain('?')
        ->and($result)->not->toContain(':user_id')
        ->and($result)->not->toContain(':active');
});

it('collapses whitespace', function () {
    $normalizer = new QueryNormalizer;

    $result = $normalizer->normalize("SELECT  *\n  FROM   users\n  WHERE  id = 1");

    expect($result)->not->toContain("\n")
        ->and(preg_match('/\s{2,}/', $result))->toBe(0);
});

it('produces the same hash for structurally identical queries', function () {
    $normalizer = new QueryNormalizer;

    $hash1 = $normalizer->hash('SELECT * FROM users WHERE id = 1');
    $hash2 = $normalizer->hash('SELECT * FROM users WHERE id = 999');

    expect($hash1)->toBe($hash2);
});

it('produces different hashes for structurally different queries', function () {
    $normalizer = new QueryNormalizer;

    $hash1 = $normalizer->hash('SELECT * FROM users WHERE id = 1');
    $hash2 = $normalizer->hash('SELECT * FROM posts WHERE id = 1');

    expect($hash1)->not->toBe($hash2);
});

it('extracts table name from SELECT', function () {
    $normalizer = new QueryNormalizer;

    expect($normalizer->extractTable('SELECT * FROM users WHERE id = 1'))->toBe('users');
});

it('extracts table name from UPDATE', function () {
    $normalizer = new QueryNormalizer;

    expect($normalizer->extractTable('UPDATE users SET name = ? WHERE id = ?'))->toBe('users');
});

it('extracts table name from INSERT INTO', function () {
    $normalizer = new QueryNormalizer;

    expect($normalizer->extractTable('INSERT INTO posts (title, body) VALUES (?, ?)'))->toBe('posts');
});

it('extracts table name with quoted identifiers', function () {
    $normalizer = new QueryNormalizer;

    expect($normalizer->extractTable('SELECT * FROM "crm:contacts" WHERE id = 1'))->toBe('crm:contacts');
});

it('returns null when no table can be extracted', function () {
    $normalizer = new QueryNormalizer;

    expect($normalizer->extractTable('PRAGMA table_info(users)'))->toBeNull();
});

it('extracts SQL operation type', function () {
    $normalizer = new QueryNormalizer;

    expect($normalizer->extractOperation('SELECT * FROM users'))->toBe('SELECT')
        ->and($normalizer->extractOperation('INSERT INTO users'))->toBe('INSERT')
        ->and($normalizer->extractOperation('UPDATE users SET name = ?'))->toBe('UPDATE')
        ->and($normalizer->extractOperation('DELETE FROM users'))->toBe('DELETE')
        ->and($normalizer->extractOperation('  select  * from users'))->toBe('SELECT');
});

it('returns UNKNOWN for unrecognised operations', function () {
    $normalizer = new QueryNormalizer;

    expect($normalizer->extractOperation('PRAGMA table_info'))->toBe('PRAGMA');
});

it('hash is stable across normalised queries with different values', function () {
    $normalizer = new QueryNormalizer;

    $queries = [
        'select * from "users" where "id" = 1',
        'select * from "users" where "id" = 2',
        'select * from "users" where "id" = 9999',
    ];

    $hashes = array_map(fn (string $q) => $normalizer->hash($q), $queries);

    expect($hashes[0])->toBe($hashes[1])
        ->and($hashes[1])->toBe($hashes[2]);
});
