<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../repositories/LoyaltyRepository.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: termin_nach_gefragt.php');
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: termin_nach_gefragt.php?fehler=ungueltige_daten');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = trim($_POST['status'] ?? '');

$erlaubteStatuswerte = [
    'angefragt',
    'bestaetigt',
    'abgelehnt',
    'abgeschlossen'
];

if (
    $id === false ||
    $id === null ||
    !in_array($status, $erlaubteStatuswerte, true)
) {
    header('Location: termin_nach_gefragt.php?fehler=ungueltige_daten');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();

    $repository = new TerminwunschRepository($pdo);

    // Termindaten VOR der Status-Änderung holen, damit wir sie für
    // die E-Mail zur Verfügung haben.
    $terminwunsch = $repository->getTerminwunschById($id);

    $erfolgreich = $repository->changeStatus($id, $status);

    if (!$erfolgreich) {
        header(
            'Location: terminwunsch_details.php?id='
                . $id
                . '&fehler=status'
        );
        exit;
    }

    // E-Mail nur bei bestätigt/abgelehnt senden, und nur wenn die
    // Termindaten gefunden wurden. Ein E-Mail-Fehler soll den
    // eigentlichen Status-Wechsel nicht verhindern (best effort).
    if ($terminwunsch !== false && in_array($status, ['bestaetigt', 'abgelehnt'], true)) {
        try {
            $emailService = new EmailService();

            $terminDaten = [
                'mitarbeiter_name' => $terminwunsch['mitarbeiter_name'],
                'dienstleistung_name' => $terminwunsch['dienstleistung_name'],
                'start' => $terminwunsch['terminwunsche_start'],
            ];

            if ($status === 'bestaetigt') {
                $emailService->sendTerminBestaetigt(
                    $terminwunsch['kunden_email'],
                    $terminwunsch['kunden_name'],
                    $terminDaten
                );
            } else {
                $emailService->sendTerminAbgelehnt(
                    $terminwunsch['kunden_email'],
                    $terminwunsch['kunden_name'],
                    $terminDaten
                );
            }
        } catch (Throwable $emailException) {
            error_log(
                'Termin-Status-E-Mail konnte nicht gesendet werden: '
                    . $emailException->getMessage()
            );
        }
    }

    // Bei "abgeschlossen": Treuepunkte für den Kunden vergeben (best effort)
    if ($terminwunsch !== false && $status === 'abgeschlossen') {
        try {
            $loyaltyRepository = new LoyaltyRepository($pdo);
            $loyaltyRepository->punkteVergebenFuerBetrag(
                (int) $terminwunsch['kunde_id'],
                (float) $terminwunsch['dienstleistung_preis']
            );
        } catch (Throwable $loyaltyException) {
            error_log(
                'Treuepunkte konnten nicht vergeben werden: '
                    . $loyaltyException->getMessage()
            );
        }
    }

    header(
        'Location: terminwunsch_details.php?id='
            . $id
            . '&status_geaendert=1'
    );
    exit;
} catch (Throwable $exception) {
    header(
        'Location: terminwunsch_details.php?id='
            . $id
            . '&fehler=datenbank'
    );
    exit;
}
