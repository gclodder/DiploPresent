<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('GET', 'DELETE');

$name = safe_filename((string) ($_GET['name'] ?? ''), ['csv']);
$path = IMPORT_ROOT . '/' . $name;
if (!is_file($path)) {
    fail('Importbestand niet gevonden.', 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!unlink($path)) {
        fail('Importbestand kon niet worden verwijderd.', 500);
    }
    respond(['name' => $name]);
}

$content = file_get_contents($path);
if ($content === false) {
    fail('Importbestand kon niet worden gelezen.', 500);
}
respond(['name' => $name, 'content' => $content]);
