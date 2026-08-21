<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/CustomerRepository.php';

Auth::requireAdmin();

try {
    $pdo = Database::getInstance()->getConnection();
    $customerRepository = new CustomerRepository($pdo);

    $customers = $customerRepository->getAllCustomers();
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Es ist ein Fehler aufgetreten.');
}

$dateiname = 'kunden_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['ID', 'Vorname', 'Nachname', 'E-Mail', 'Telefon', 'Registriert am', 'Status'], ';');

foreach ($customers as $customer) {
    fputcsv($output, [
        '#C-' . str_pad((string) $customer['id'], 4, '0', STR_PAD_LEFT),
        $customer['vor_name'],
        $customer['nach_name'],
        $customer['email'],
        $customer['telefon1'] ?? '',
        date('d.m.Y', strtotime($customer['created_at'])),
        $customer['status'] === 'aktiv' ? 'Aktiv' : 'Inaktiv',
    ], ';');
}

fclose($output);
exit;
