<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Versendet Termin-bezogene E-Mails (Bestätigung, Ablehnung, Erinnerung)
 * über SMTP (z. B. Gmail) mittels PHPMailer.
 *
 * Nutzung:
 *   $emailService = new EmailService();
 *   $emailService->sendTerminBestaetigt('kunde@mail.com', 'Anna Muster', $terminDaten);
 */
class EmailService
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail_config.php';
    }

    /**
     * @param array{mitarbeiter_name: string, dienstleistung_name: string, start: string} $termin
     *        "start" im Format 'Y-m-d H:i:s'
     */
    public function sendTerminBestaetigt(
        string $empfaengerEmail,
        string $empfaengerName,
        array $termin
    ): bool {
        $betreff = 'Dein Termin bei Bella Beauty wurde bestätigt';

        $inhalt = '<p>Hallo ' . htmlspecialchars($empfaengerName) . ',</p>'
            . '<p>dein Termin wurde <strong>bestätigt</strong>:</p>'
            . $this->terminDetailsHtml($termin)
            . '<p>Wir freuen uns auf dich!</p>';

        return $this->send($empfaengerEmail, $empfaengerName, $betreff, $inhalt);
    }

    /**
     * @param array{mitarbeiter_name: string, dienstleistung_name: string, start: string} $termin
     */
    public function sendTerminAbgelehnt(
        string $empfaengerEmail,
        string $empfaengerName,
        array $termin
    ): bool {
        $betreff = 'Dein Terminwunsch bei Bella Beauty';

        $inhalt = '<p>Hallo ' . htmlspecialchars($empfaengerName) . ',</p>'
            . '<p>leider müssen wir dir mitteilen, dass dein angefragter Termin '
            . '<strong>nicht bestätigt</strong> werden konnte:</p>'
            . $this->terminDetailsHtml($termin)
            . '<p>Bitte buche gerne einen neuen Termin über unsere Webseite.</p>';

        return $this->send($empfaengerEmail, $empfaengerName, $betreff, $inhalt);
    }

    /**
     * @param array{mitarbeiter_name: string, dienstleistung_name: string, start: string} $termin
     */
    public function sendTerminErinnerung(
        string $empfaengerEmail,
        string $empfaengerName,
        array $termin,
        int $tageVorher
    ): bool {
        $zeitraum = $tageVorher === 1 ? 'morgen' : 'in ' . $tageVorher . ' Tagen';

        $betreff = 'Erinnerung: Dein Termin bei Bella Beauty ' . $zeitraum;

        $inhalt = '<p>Hallo ' . htmlspecialchars($empfaengerName) . ',</p>'
            . '<p>kurze Erinnerung an deinen Termin ' . htmlspecialchars($zeitraum) . ':</p>'
            . $this->terminDetailsHtml($termin)
            . '<p>Wir freuen uns auf dich!</p>';

        return $this->send($empfaengerEmail, $empfaengerName, $betreff, $inhalt);
    }

    /**
     * Benachrichtigt den Admin, wenn ein Kunde oder Mitarbeiter einen
     * Termin storniert hat.
     *
     * @param array{mitarbeiter_name: string, dienstleistung_name: string, start: string} $termin
     * @param string $stornoDurch z.B. "Kunde: Anna Muster" oder "Mitarbeiter: Julia Schmidt"
     */
    public function sendStornoBenachrichtigungAdmin(
        array $termin,
        string $stornoDurch
    ): bool {
        $adminEmail = $this->config['admin_email'] ?? null;

        if ($adminEmail === null || $adminEmail === '') {
            return false;
        }

        $jetzt = (new DateTime())->format('d.m.Y H:i');

        $betreff = 'Termin storniert – ' . $stornoDurch;

        $inhalt = '<p>Ein Termin wurde soeben storniert.</p>'
            . '<p><strong>Storniert von:</strong> ' . htmlspecialchars($stornoDurch) . '</p>'
            . '<p><strong>Storniert am:</strong> ' . $jetzt . ' Uhr</p>'
            . '<p><strong>Betroffener Termin:</strong></p>'
            . $this->terminDetailsHtml($termin);

        return $this->send($adminEmail, 'Admin', $betreff, $inhalt);
    }

    /**
     * Benachrichtigt den Admin über eine neue Krankmeldung.
     */
    public function sendKrankmeldungAdmin(
        string $mitarbeiterName,
        string $zeitraumText,
        bool $hatAuDatei
    ): bool {
        $adminEmail = $this->config['admin_email'] ?? null;

        if ($adminEmail === null || $adminEmail === '') {
            return false;
        }

        $betreff = 'Krankmeldung – ' . $mitarbeiterName;

        $inhalt = '<p><strong>' . htmlspecialchars($mitarbeiterName) . '</strong> '
            . 'hat sich krankgemeldet.</p>'
            . '<p><strong>Zeitraum:</strong> ' . htmlspecialchars($zeitraumText) . '</p>'
            . '<p><strong>AU-Bescheinigung hochgeladen:</strong> ' . ($hatAuDatei ? 'Ja' : 'Nein') . '</p>';

        return $this->send($adminEmail, 'Admin', $betreff, $inhalt);
    }

    private function terminDetailsHtml(array $termin): string
    {
        $start = new DateTime($termin['start']);

        return '<ul>'
            . '<li><strong>Datum:</strong> ' . $start->format('d.m.Y') . '</li>'
            . '<li><strong>Uhrzeit:</strong> ' . $start->format('H:i') . ' Uhr</li>'
            . '<li><strong>Dienstleistung:</strong> ' . htmlspecialchars($termin['dienstleistung_name']) . '</li>'
            . '<li><strong>Mitarbeiter:in:</strong> ' . htmlspecialchars($termin['mitarbeiter_name']) . '</li>'
            . '</ul>';
    }

    public function sendPasswortReset(
        string $empfaengerEmail,
        string $empfaengerName,
        string $resetLink
    ): bool {
        $betreff = 'Passwort zurücksetzen – Bella Beauty';

        $inhalt = '<p>Hallo ' . htmlspecialchars($empfaengerName) . ',</p>'
            . '<p>du hast angefragt, dein Passwort zurückzusetzen. '
            . 'Klicke auf den folgenden Link, um ein neues Passwort zu vergeben:</p>'
            . '<p><a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a></p>'
            . '<p>Der Link ist <strong>1 Stunde</strong> lang gültig.</p>'
            . '<p>Falls du das nicht angefragt hast, kannst du diese E-Mail ignorieren.</p>';

        return $this->send($empfaengerEmail, $empfaengerName, $betreff, $inhalt);
    }

    private function send(
        string $empfaengerEmail,
        string $empfaengerName,
        string $betreff,
        string $htmlInhalt
    ): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['smtp_port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($empfaengerEmail, $empfaengerName);

            $mail->isHTML(true);
            $mail->Subject = $betreff;
            $mail->Body = $htmlInhalt;
            $mail->AltBody = strip_tags(str_replace('</li>', "\n", $htmlInhalt));

            return $mail->send();
        } catch (PHPMailerException $exception) {
            error_log('E-Mail-Versand fehlgeschlagen: ' . $mail->ErrorInfo);

            return false;
        }
    }
}
