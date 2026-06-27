<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_method('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = safe_filename((string) ($_GET['name'] ?? ''), ['json']);
    respond(['name' => $name, 'content' => read_json_file(LIST_ROOT . '/' . $name)]);
}

$body = json_body();
$name = safe_filename((string) ($body['name'] ?? ''), ['json']);
$students = $body['students'] ?? null;

if (!is_array($students) || count($students) === 0) {
    fail('De lijst bevat geen leerlingen.');
}
$normalized = [];
foreach ($students as $index => $student) {
    if (!is_array($student)) {
        fail('Ongeldige leerlingregel.', 400, ['row' => $index]);
    }
    $studentNumber = trim((string) ($student['studentNumber'] ?? ''));
    $fullName = trim((string) ($student['fullName'] ?? ''));
    if ($studentNumber === '' || $fullName === '') {
        fail('Leerlingnummer en naam zijn verplicht.', 400, ['row' => $index]);
    }
    $normalized[] = [
        'position' => $index + 1,
        'studentNumber' => $studentNumber,
        'fullName' => $fullName,
        'firstName' => trim((string) ($student['firstName'] ?? '')),
        'lastName' => trim((string) ($student['lastName'] ?? '')),
        'mentor' => trim((string) ($student['mentor'] ?? '')),
        'cumLaude' => filter_var($student['cumLaude'] ?? false, FILTER_VALIDATE_BOOL),
        'group' => trim((string) ($student['group'] ?? '')),
    ];
}

$payload = [
    'version' => 1,
    'createdAt' => date(DATE_ATOM),
    'students' => $normalized,
];
atomic_write_json(LIST_ROOT . '/' . $name, $payload);
respond(['name' => $name, 'count' => count($normalized)], 201);
