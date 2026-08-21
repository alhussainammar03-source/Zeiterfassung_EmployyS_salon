<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/ReportRepository.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';

Auth::requireAdmin();

$von = trim($_GET['von'] ?? '');
$bis = trim($_GET['bis'] ?? '');

if ($von === '' || $bis === '') {
    $von = (new DateTime('first day of this month'))->format('Y-m-d');
    $bis = (new DateTime('last day of this month'))->format('Y-m-d');
}

$bisExklusiv = (new DateTime($bis))->modify('+1 day')->format('Y-m-d');

try {
    $pdo = Database::getInstance()->getConnection();
    $reportRepository = new ReportRepository($pdo);
    $employeeRepository = new employeeRepository($pdo);

    $umsatzGesamt = $reportRepository->getUmsatzGesamt($von . ' 00:00:00', $bisExklusiv . ' 00:00:00');
    $meistgebucht = $reportRepository->getMeistgebuchteDienstleistungenSortiert($von . ' 00:00:00', $bisExklusiv . ' 00:00:00', 'umsatz', 50);
    $gebuchteStunden = $reportRepository->getGebuchteStundenProMitarbeiter($von . ' 00:00:00', $bisExklusiv . ' 00:00:00');
    $mitarbeiterListe = $employeeRepository->getAllActiveMitarbeiterMitSollstunden();
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Es ist ein Fehler aufgetreten.');
}

$dateiname = 'bericht_' . $von . '_bis_' . $bis . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['Bericht für Zeitraum', $von . ' bis ' . $bis], ';');
fputcsv($output, [], ';');

fputcsv($output, ['Gesamtumsatz'], ';');
fputcsv($output, [number_format($umsatzGesamt, 2, ',', '') . ' €'], ';');
fputcsv($output, [], ';');

fputcsv($output, ['Meistgebuchte Dienstleistungen'], ';');
fputcsv($output, ['Dienstleistung', 'Anzahl', 'Umsatz'], ';');
foreach ($meistgebucht as $zeile) {
    fputcsv($output, [
        $zeile['name'],
        $zeile['anzahl'],
        number_format((float) $zeile['umsatz'], 2, ',', '') . ' €',
    ], ';');
}
fputcsv($output, [], ';');

fputcsv($output, ['Mitarbeiter-Auslastung'], ';');
fputcsv($output, ['Mitarbeiter', 'Gebuchte Stunden'], ';');
foreach ($mitarbeiterListe as $mitarbeiter) {
    $id = (int) $mitarbeiter['id'];
    fputcsv($output, [
        $mitarbeiter['vor_name'] . ' ' . $mitarbeiter['nach_name'],
        number_format($gebuchteStunden[$id] ?? 0.0, 2, ',', ''),
    ], ';');
}

fclose($output);
exit;
