<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TimeTrackingRepository.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';

Auth::requireAdmin();

$von = trim($_GET['von'] ?? '');
$bis = trim($_GET['bis'] ?? '');

if ($von === '' || $bis === '' || $von > $bis) {
    http_response_code(400);
    exit('Bitte gültigen Zeitraum (von/bis) angeben.');
}

// "bis" ist im Formular inklusive gemeint -> für die Abfrage einen Tag weiter
$bisExklusiv = (new DateTime($bis))->modify('+1 day')->format('Y-m-d');

try {
    $pdo = Database::getInstance()->getConnection();
    $timeTrackingRepository = new TimeTrackingRepository($pdo);
    $employeeRepository = new employeeRepository($pdo);

    $mitarbeiterListe = $employeeRepository->getAllActiveMitarbeiterMitSollstunden();
    $stundenProMitarbeiter = $timeTrackingRepository->summeStundenAlleMitarbeiter(
        $von . ' 00:00:00',
        $bisExklusiv . ' 00:00:00'
    );
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Es ist ein Fehler aufgetreten.');
}

$dateiname = 'zeiterfassung_' . $von . '_bis_' . $bis . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');

$output = fopen('php://output', 'w');

// BOM für korrekte Umlaute in Excel
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['Mitarbeiter', 'Zeitraum von', 'Zeitraum bis', 'Gearbeitete Stunden'], ';');

foreach ($mitarbeiterListe as $mitarbeiter) {
    $stunden = $stundenProMitarbeiter[(int) $mitarbeiter['id']] ?? 0.0;

    fputcsv($output, [
        $mitarbeiter['vor_name'] . ' ' . $mitarbeiter['nach_name'],
        $von,
        $bis,
        number_format($stunden, 2, ',', ''),
    ], ';');
}

fclose($output);
exit;
