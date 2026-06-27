<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('GET', 'POST', 'PUT');

function parse_session_code(string $value): string
{
    $value = strtolower(trim($value));
    if (!preg_match('/^[a-f0-9]{12}$/', $value)) {
        fail('Ongeldige sessiecode.');
    }
    return $value;
}

function session_path(string $id): string
{
    return SESSION_ROOT . '/' . $id . '.json';
}

function public_session(array $session): array
{
    unset($session['controllerToken']);
    return $session;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    $listName = safe_filename((string) ($body['listName'] ?? ''), ['json']);
    if (!is_file(LIST_ROOT . '/' . $listName)) {
        fail('Presentatielijst niet gevonden.', 404);
    }

    $id = bin2hex(random_bytes(6));
    $token = bin2hex(random_bytes(24));
    $session = [
        'id' => $id,
        'controllerToken' => $token,
        'listName' => $listName,
        'title' => trim((string) ($body['title'] ?? 'Diplomauitreiking')),
        'department' => in_array($body['department'] ?? '', ['havo', 'vwo'], true)
            ? $body['department']
            : 'havo',
        'index' => -1,
        'peek' => false,
        'testPattern' => false,
        'revision' => 1,
        'createdAt' => date(DATE_ATOM),
        'updatedAt' => date(DATE_ATOM),
    ];
    atomic_write_json(session_path($id), $session);
    respond(['session' => public_session($session), 'controllerToken' => $token], 201);
}

$id = parse_session_code((string) ($_GET['id'] ?? ''));
$path = session_path($id);
$session = read_json_file($path);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(public_session($session));
}

$body = json_body();
$token = (string) ($body['controllerToken'] ?? '');
if (!hash_equals((string) ($session['controllerToken'] ?? ''), $token)) {
    fail('Ongeldige controllercode.', 403);
}

if (array_key_exists('index', $body)) {
    $session['index'] = max(-1, (int) $body['index']);
}
if (array_key_exists('peek', $body)) {
    $session['peek'] = (bool) $body['peek'];
}
if (array_key_exists('testPattern', $body)) {
    $session['testPattern'] = (bool) $body['testPattern'];
}
$session['revision'] = (int) ($session['revision'] ?? 0) + 1;
$session['updatedAt'] = date(DATE_ATOM);
atomic_write_json($path, $session);
respond(public_session($session));
