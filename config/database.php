<?php

function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? null;
    }
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

/** Load the .env file (simple KEY=VALUE lines) once at start-up. */
function load_env(string $file): void
{
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // allow "quoted" values
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[0] === substr($v, -1)) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {      // real environment variables win
            $_ENV[$k] = $v;
        }
    }
}

/** Get the shared database connection. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $required) {
        if (env($required) === null) {
            throw new RuntimeException("Missing database setting: $required (check your .env file)");
        }
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        env('DB_HOST'),
        env('DB_PORT', '5432'),
        env('DB_NAME', 'postgres'),
        env('DB_SSLMODE', 'require')      // Supabase requires SSL
    );

    $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
