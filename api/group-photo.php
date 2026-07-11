<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('GET', 'POST', 'DELETE');

function department(): string
{
    $department = strtolower(trim((string) ($_REQUEST['department'] ?? '')));
    if (!in_array($department, ['havo', 'vwo'], true)) {
        fail('Ongeldige afdeling.');
    }
    return $department;
}

function group_photo_path(string $department): string
{
    return STORAGE_ROOT . '/photos/examenfoto_' . $department . '.jpg';
}

function group_photo_payload(string $department): array
{
    $path = group_photo_path($department);
    return [
        'department' => $department,
        'name' => basename($path),
        'exists' => is_file($path),
        'size' => is_file($path) ? filesize($path) : 0,
        'modifiedAt' => is_file($path) ? date(DATE_ATOM, filemtime($path)) : null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond([
        group_photo_payload('havo'),
        group_photo_payload('vwo'),
    ]);
}

require_admin();

$department = department();
$path = group_photo_path($department);

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (is_file($path) && !unlink($path)) {
        fail('Groepsfoto kon niet worden verwijderd.', 500);
    }
    respond(group_photo_payload($department));
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    fail('Geen bestand ontvangen.');
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    fail('Upload mislukt.', 400, ['code' => $file['error'] ?? null]);
}

$temporary = (string) ($file['tmp_name'] ?? '');
$mime = mime_content_type($temporary) ?: '';
if (!in_array($mime, ['image/jpeg', 'image/pjpeg'], true)) {
    fail('Alleen JPG-groepsfoto’s zijn toegestaan.');
}

if (!move_uploaded_file($temporary, $path)) {
    fail('Groepsfoto kon niet worden opgeslagen.', 500);
}

respond(group_photo_payload($department), 201);
