<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('GET', 'PUT');

$defaults = [
    'importBaseUrl' => 'storage/imports',
    'listBaseUrl' => 'storage/lists',
    'photoBaseUrl' => 'storage/photos',
    'defaultTitle' => 'Diplomauitreiking ' . date('Y'),
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(is_file(CONFIG_FILE) ? array_merge($defaults, read_json_file(CONFIG_FILE)) : $defaults);
}

require_admin();

$body = json_body();
$config = [];
foreach (array_keys($defaults) as $key) {
    $value = trim((string) ($body[$key] ?? $defaults[$key]));
    if ($key === 'defaultTitle') {
        if ($value === '') {
            fail('De standaardtitel mag niet leeg zijn.');
        }
        $config[$key] = $value;
        continue;
    }
    if ($value === '' || str_contains($value, '..') || preg_match('#^[a-z]+://#i', $value)) {
        fail("Ongeldige waarde voor {$key}.");
    }
    $config[$key] = trim($value, '/');
}

atomic_write_json(CONFIG_FILE, $config);
respond($config);
