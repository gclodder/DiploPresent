<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('GET', 'POST', 'PUT', 'DELETE');

function stored_auth_config(): array
{
    if (!is_file(AUTH_FILE)) {
        return [];
    }

    try {
        $config = json_decode((string) file_get_contents(AUTH_FILE), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }

    return is_array($config) ? $config : [];
}

function admin_password_hash(): string
{
    $hash = trim(env_value('DIPLOPRESENT_ADMIN_PASSWORD_HASH'));
    if ($hash !== '') {
        return $hash;
    }

    return trim(env_value('DIPLOPRESENT_PASSWORD_HASH'));
}

function user_password_hash(): string
{
    $config = stored_auth_config();
    $storedHash = trim((string) ($config['userPasswordHash'] ?? ''));
    if ($storedHash !== '') {
        return $storedHash;
    }

    return trim(env_value('DIPLOPRESENT_USER_PASSWORD_HASH'));
}

function user_password_rotated_at(): ?string
{
    $rotatedAt = stored_auth_config()['userPasswordRotatedAt'] ?? null;
    return is_string($rotatedAt) && $rotatedAt !== '' ? $rotatedAt : null;
}

function random_readable_password(): string
{
    $words = [
        'agenda', 'anker', 'avond', 'badge', 'balie', 'beeld', 'beheer', 'bloem',
        'bord', 'brief', 'brug', 'bureau', 'camera', 'certificaat', 'coach', 'dag',
        'diploma', 'deur', 'docent', 'feest', 'foto', 'groep', 'havo', 'kaart',
        'klas', 'knop', 'lijst', 'lint', 'lokaal', 'map', 'mentor', 'naam',
        'podium', 'prijs', 'rapport', 'rij', 'school', 'scherm', 'stoel', 'tafel',
        'team', 'teken', 'toets', 'vlag', 'vloer', 'vwo', 'zaal', 'zomer',
    ];

    $passwordWords = [];
    for ($index = 0; $index < 3; $index++) {
        $passwordWords[] = $words[random_int(0, count($words) - 1)];
    }

    $passwordWords[] = (string) random_int(10, 99);
    return implode('-', $passwordWords);
}

function auth_payload(): array
{
    $adminConfigured = admin_password_hash() !== '';
    $userConfigured = user_password_hash() !== '';

    return [
        'authenticated' => is_authenticated(),
        'role' => current_user_role(),
        'isAdmin' => is_admin(),
        'configured' => $adminConfigured,
        'configuredAdmin' => $adminConfigured,
        'configuredUser' => $userConfigured,
        'userPasswordRotatedAt' => user_password_rotated_at(),
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
    respond(auth_payload());
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_admin();
    $body = json_body();
    $customPassword = trim((string) ($body['password'] ?? ''));
    if ($customPassword !== '' && strlen($customPassword) < 8) {
        fail('Kies een gebruikerswachtwoord van minimaal 8 tekens.');
    }

    $password = $customPassword !== '' ? $customPassword : random_readable_password();
    $config = stored_auth_config();
    $config['userPasswordHash'] = password_hash($password, PASSWORD_DEFAULT);
    $config['userPasswordRotatedAt'] = date(DATE_ATOM);
    atomic_write_json(AUTH_FILE, $config);

    respond([
        ...auth_payload(),
        'generatedPassword' => $password,
    ]);
}

$body = json_body();
$role = (string) ($body['role'] ?? 'user');
if (!in_array($role, ['admin', 'user'], true)) {
    fail('Onbekende loginrol.');
}

$hash = $role === 'admin' ? admin_password_hash() : user_password_hash();
if ($hash === '') {
    $message = $role === 'admin'
        ? 'Admin-login is nog niet geconfigureerd. Zet DIPLOPRESENT_ADMIN_PASSWORD_HASH in .env.'
        : 'Gebruikerslogin is nog niet geconfigureerd. Roteer het gebruikerswachtwoord via beheer.';
    fail($message, 500);
}

$password = (string) ($body['password'] ?? '');
if ($password === '' || !password_verify($password, $hash)) {
    fail('Wachtwoord is onjuist.', 401);
}

start_auth_session();
session_regenerate_id(true);
$_SESSION['authenticated'] = true;
$_SESSION['role'] = $role;
$_SESSION['authenticatedAt'] = time();

respond(auth_payload());
