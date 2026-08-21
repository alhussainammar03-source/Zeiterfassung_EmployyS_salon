<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';

Auth::requireAdmin();

try {
    $pdo = Database::getInstance()->getConnection();
    $employeeRepository = new employeeRepository($pdo);

    $employees = $employeeRepository->getAllEmployees();
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Es ist ein Fehler aufgetreten.');
}

$dateiname = 'mitarbeiter_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');

$output = fopen('php://output', 'w');

// BOM für korrekte Umlaute in Excel
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['ID', 'Vorname', 'Nachname', 'E-Mail', 'Telefon', 'Position/Rolle', 'Status'], ';');

foreach ($employees as $employee) {
    fputcsv($output, [
        '#EMP-' . str_pad((string) $employee['id'], 3, '0', STR_PAD_LEFT),
        $employee['vor_name'],
        $employee['nach_name'],
        $employee['email'],
        $employee['telefon'] ?? '',
        $employee['position'] ?? ucfirst($employee['role'] ?? ''),
        $employee['status'] === 'aktiv' ? 'Aktiv' : 'Inaktiv',
    ], ';');
}

fclose($output);
exit;
