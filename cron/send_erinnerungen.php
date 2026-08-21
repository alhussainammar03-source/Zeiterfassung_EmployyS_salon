<?php

/**
 * Sendet Erinnerungs-E-Mails für Termine, die in 2 Tagen bzw. 1 Tag
 * stattfinden. Gedacht zum täglichen Ausführen über den Windows
 * Task Scheduler (siehe Anleitung), NICHT über den Browser.
 *
 * Aufruf: php cron/send_erinnerungen.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../services/EmailService.php';

$logDatei = __DIR__ . '/erinnerungen.log';

function logZeile(string $text, string $logDatei): void
{
    $zeitstempel = date('Y-m-d H:i:s');
    file_put_contents(
        $logDatei,
        "[{$zeitstempel}] {$text}\n",
        FILE_APPEND
    );
}

try {
    $pdo = Database::getInstance()->getConnection();
    $terminwunschRepository = new TerminwunschRepository($pdo);
    $emailService = new EmailService();

    foreach ([2, 1] as $tageVorher) {
        $termine = $terminwunschRepository->findForErinnerung($tageVorher);

        logZeile(
            count($termine) . " Termin(e) für {$tageVorher}-Tage-Erinnerung gefunden.",
            $logDatei
        );

        foreach ($termine as $termin) {
            $terminDaten = [
                'mitarbeiter_name' => $termin['mitarbeiter_name'],
                'dienstleistung_name' => $termin['dienstleistung_name'],
                'start' => $termin['terminwunsche_start'],
            ];

            $erfolgreich = $emailService->sendTerminErinnerung(
                $termin['kunden_email'],
                $termin['kunden_name'],
                $terminDaten,
                $tageVorher
            );

            if ($erfolgreich) {
                $terminwunschRepository->markiereErinnerungGesendet(
                    (int) $termin['id'],
                    $tageVorher
                );

                logZeile(
                    "OK: Erinnerung ({$tageVorher} Tage) an {$termin['kunden_email']} gesendet (Terminwunsch #{$termin['id']}).",
                    $logDatei
                );
            } else {
                logZeile(
                    "FEHLER: Erinnerung ({$tageVorher} Tage) an {$termin['kunden_email']} NICHT gesendet (Terminwunsch #{$termin['id']}).",
                    $logDatei
                );
            }
        }
    }

    logZeile('Durchlauf abgeschlossen.', $logDatei);
} catch (Throwable $exception) {
    logZeile('FEHLER im Skript: ' . $exception->getMessage(), $logDatei);
}
