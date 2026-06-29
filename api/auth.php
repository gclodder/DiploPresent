<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('GET', 'POST', 'DELETE');

function configured_password_hash(): string
{
    return trim(env_value('DIPLOPRESENT_PASSWORD_HASH'));
}

function auth_payload(): array
{
    return [
        'authenticated' => is_authenticated(),
        'configured' => configured_password_hash() !== '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(auth_payload());
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    start_auth_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    respond(['authenticated' => false, 'configured' => configured_password_hash() !== '']);
}

$hash = configured_password_hash();
if ($hash === '') {
    fail('Login is nog niet geconfigureerd. Zet DIPLOPRESENT_PASSWORD_HASH in .env.', 500);
}

$body = json_body();
$password = (string) ($body['password'] ?? '');
if ($password === '' || !password_verify($password, $hash)) {
    fail('Wachtwoord is onjuist.', 401);
}

start_auth_session();
session_regenerate_id(true);
$_SESSION['authenticated'] = true;
$_SESSION['authenticatedAt'] = time();

respond(auth_payload());
