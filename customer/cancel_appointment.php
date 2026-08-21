<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('kunde');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_appointments.php');
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: my_appointments.php?fehler=allgemein');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: my_appointments.php?fehler=nicht_gefunden');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $terminwunschRepository = new TerminwunschRepository($pdo);

    // Sicherheitsprüfung: gehört dieser Termin wirklich dem eingeloggten Kunden?
    if (!$terminwunschRepository->gehoertZuKunde($id, (int) $_SESSION['user_id'])) {
        header('Location: my_appointments.php?fehler=nicht_gefunden');
        exit;
    }

    $termin = $terminwunschRepository->getTerminwunschById($id);

    if ($termin === false) {
        header('Location: my_appointments.php?fehler=nicht_gefunden');
        exit;
    }

    // Nur angefragte/bestätigte, noch nicht vergangene Termine sind stornierbar
    $istInZukunft = new DateTime($termin['terminwunsche_start']) >= new DateTime();
    $istStornierbar = in_array($termin['status'], ['angefragt', 'bestaetigt'], true);

    if (!$istInZukunft || !$istStornierbar) {
        header('Location: my_appointments.php?fehler=nicht_stornierbar');
        exit;
    }

    $terminwunschRepository->changeStatus($id, 'storniert');

    try {
        $emailService = new EmailService();

        $emailService->sendStornoBenachrichtigungAdmin(
            [
                'mitarbeiter_name' => $termin['mitarbeiter_name'],
                'dienstleistung_name' => $termin['dienstleistung_name'],
                'start' => $termin['terminwunsche_start'],
            ],
            'Kunde: ' . $termin['kunden_name']
        );
    } catch (Throwable $emailException) {
        error_log(
            'Storno-Benachrichtigung konnte nicht gesendet werden: '
                . $emailException->getMessage()
        );
    }

    header('Location: my_appointments.php?storniert=1');
    exit;
} catch (Throwable $exception) {
    header('Location: my_appointments.php?fehler=allgemein');
    exit;
}
