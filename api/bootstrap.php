<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const STORAGE_ROOT = APP_ROOT . '/storage';
const IMPORT_ROOT = STORAGE_ROOT . '/imports';
const LIST_ROOT = STORAGE_ROOT . '/lists';
const SESSION_ROOT = STORAGE_ROOT . '/sessions';
const CONFIG_FILE = STORAGE_ROOT . '/config.json';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

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
