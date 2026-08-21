<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/employeeRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Diese Aktion ist nicht erlaubt.');
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    exit('Ungültige Mitarbeiter-ID.');
}

try {
    $pdo = Database::getInstance()->getConnection();

    $employeeRepository = new employeeRepository($pdo);

    $employee = $employeeRepository->getEmployeeById($id);

    if ($employee === false) {
        exit('Mitarbeiter wurde nicht gefunden.');
    }

    $employeeRepository->deleteEmployee($id);

    header('Location: employees.php?deleted=1');
    exit;
} catch (PDOException $exception) {
    exit('Der Mitarbeiter kann möglicherweise wegen vorhandener Termine '
        . 'nicht gelöscht werden. Setze ihn stattdessen auf inaktiv.');
}
