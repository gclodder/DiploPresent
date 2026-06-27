<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('POST');

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    fail('Geen bestand ontvangen.');
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    fail('Upload is mislukt.', 400, ['uploadError' => $file['error'] ?? null]);
}
if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
    fail('Bestand is groter dan 5 MB.');
}

$name = safe_filename((string) ($file['name'] ?? ''), ['csv']);
$target = IMPORT_ROOT . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    fail('Bestand kon niet worden opgeslagen.', 500);
}

respond(['name' => $name], 201);
