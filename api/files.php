<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('GET');

$type = $_GET['type'] ?? 'imports';
$settings = match ($type) {
    'imports' => [IMPORT_ROOT, ['csv']],
    'lists' => [LIST_ROOT, ['json']],
    default => fail('Onbekend bestandstype.'),
};

[$directory, $extensions] = $settings;
$files = [];
foreach (new DirectoryIterator($directory) as $entry) {
    if (!$entry->isFile()) {
        continue;
    }
    if (!in_array(strtolower($entry->getExtension()), $extensions, true)) {
        continue;
    }
    $file = [
        'name' => $entry->getFilename(),
        'size' => $entry->getSize(),
        'modifiedAt' => date(DATE_ATOM, $entry->getMTime()),
    ];
    if ($type === 'lists') {
        $path = $entry->getPathname();
        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $students = $payload['students'] ?? $payload['body'] ?? $payload['content']['students'] ?? [];
            $file['studentCount'] = is_array($students) ? count($students) : null;
        } catch (JsonException) {
            $file['studentCount'] = null;
        }
    }
    $files[] = $file;
}

usort($files, fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
respond($files);
