<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const STORAGE_ROOT = APP_ROOT . '/storage';
const IMPORT_ROOT = STORAGE_ROOT . '/imports';
const LIST_ROOT = STORAGE_ROOT . '/lists';
const SESSION_ROOT = STORAGE_ROOT . '/sessions';
const CONFIG_FILE = STORAGE_ROOT . '/config.json';
const AUTH_FILE = STORAGE_ROOT . '/auth.json';
const ENV_FILE = APP_ROOT . '/.env';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function env_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [];
    if (!is_file(ENV_FILE)) {
        return $config;
    }

    $lines = file(ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $config;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $config[$key] = $value;
    }

    return $config;
}

function env_value(string $key, string $default = ''): string
{
    $fromServer = $_SERVER[$key] ?? getenv($key);
    if (is_string($fromServer) && $fromServer !== '') {
        return $fromServer;
    }

    return env_config()[$key] ?? $default;
}

function auth_session_name(): string
{
    $name = env_value('DIPLOPRESENT_SESSION_NAME', 'diplopresent_auth');
    return preg_match('/^[A-Za-z0-9_-]{6,64}$/', $name) ? $name : 'diplopresent_auth';
}

function start_auth_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(auth_session_name());
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_authenticated(): bool
{
    start_auth_session();
    return ($_SESSION['authenticated'] ?? false) === true;
}

function current_user_role(): string
{
    if (!is_authenticated()) {
        return '';
    }

    $role = (string) ($_SESSION['role'] ?? 'user');
    return in_array($role, ['admin', 'user'], true) ? $role : 'user';
}

function is_admin(): bool
{
    return current_user_role() === 'admin';
}

function require_auth(): void
{
    if (!is_authenticated()) {
        fail('Niet ingelogd.', 401);
    }
}

function require_admin(): void
{
    require_auth();
    if (!is_admin()) {
        fail('Alleen beheerders mogen deze actie uitvoeren.', 403);
    }
}

function respond(mixed $data = null, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        ['success' => $status < 400, 'data' => $data],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

function fail(string $message, int $status = 400, array $details = []): never
{
    http_response_code($status);
    echo json_encode(
        [
            'success' => false,
            'error' => [
                'message' => $message,
                'details' => $details,
            ],
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

function require_method(string ...$allowed): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        fail('Methode niet toegestaan.', 405);
    }
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        fail('Lege request body.');
    }

    try {
        $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fail('Ongeldige JSON.', 400, ['json' => $exception->getMessage()]);
    }

    if (!is_array($value)) {
        fail('Een JSON-object werd verwacht.');
    }

    return $value;
}

function ensure_storage(): void
{
    foreach ([STORAGE_ROOT, IMPORT_ROOT, LIST_ROOT, SESSION_ROOT, STORAGE_ROOT . '/photos'] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            fail('Opslagmap kan niet worden aangemaakt.', 500);
        }
    }

    $rules = [
        STORAGE_ROOT . '/.htaccess' => <<<'HTACCESS'
Options -Indexes

<FilesMatch "^(config|auth)\.json$">
    Require all denied
</FilesMatch>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>

<FilesMatch "\.(php|phtml|phar)$">
    Require all denied
</FilesMatch>
HTACCESS,
        IMPORT_ROOT . '/.htaccess' => "Require all denied\n",
        LIST_ROOT . '/.htaccess' => "Require all denied\n",
        SESSION_ROOT . '/.htaccess' => "Require all denied\n",
        STORAGE_ROOT . '/photos/.htaccess' => <<<'HTACCESS'
Options -Indexes

<FilesMatch "\.(php|phtml|phar)$">
    Require all denied
</FilesMatch>
HTACCESS,
    ];

    foreach ($rules as $path => $content) {
        if (!is_file($path)) {
            @file_put_contents($path, $content);
        }
    }
}

function safe_filename(string $name, array $extensions): string
{
    $name = trim(rawurldecode($name));
    if ($name === '' || basename($name) !== $name || str_contains($name, "\0")) {
        fail('Ongeldige bestandsnaam.');
    }

    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions, true)) {
        fail('Bestandstype niet toegestaan.');
    }

    return $name;
}

function atomic_write_json(string $path, mixed $value): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        fail('Doelmap bestaat niet.', 500);
    }

    $temporary = tempnam($directory, '.tmp-');
    if ($temporary === false) {
        fail('Tijdelijk bestand kan niet worden aangemaakt.', 500);
    }

    $json = json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        fail('Bestand kon niet worden opgeslagen.', 500);
    }
}

function read_json_file(string $path): array
{
    if (!is_file($path)) {
        fail('Bestand niet gevonden.', 404);
    }

    try {
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fail('Bestand bevat ongeldige JSON.', 500, ['json' => $exception->getMessage()]);
    }
}

ensure_storage();

if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'auth.php') {
    require_auth();
}
