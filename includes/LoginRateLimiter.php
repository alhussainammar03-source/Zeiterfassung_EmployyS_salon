<?php

declare(strict_types=1);

/**
 * Schützt den Login vor Brute-Force-Angriffen: sperrt E-Mail-Adresse
 * bzw. IP-Adresse vorübergehend nach zu vielen fehlgeschlagenen
 * Versuchen innerhalb eines Zeitfensters.
 */
class LoginRateLimiter
{
    private const MAX_VERSUCHE = 5;
    private const ZEITFENSTER_MINUTEN = 15;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Prüft, ob Login-Versuche für diese E-Mail ODER IP-Adresse
     * aktuell gesperrt sind.
     *
     * @return int Verbleibende Minuten bis zur Entsperrung, 0 = nicht gesperrt
     */
    public function istGesperrt(string $email, string $ipAdresse): int
    {
        $emailMinuten = $this->minutenBisEntsperrung('email', $email);
        $ipMinuten = $this->minutenBisEntsperrung('ip_adresse', $ipAdresse);

        return max($emailMinuten, $ipMinuten);
    }

    public function versuchProtokollieren(string $email, string $ipAdresse): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_versuche (email, ip_adresse) VALUES (:email, :ip)'
        );

        $statement->execute([
            'email' => $email,
            'ip' => $ipAdresse,
        ]);
    }

    /**
     * Löscht alle protokollierten Versuche für diese E-Mail nach
     * erfolgreichem Login.
     */
    public function zuruecksetzen(string $email): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM login_versuche WHERE email = :email'
        );

        $statement->execute(['email' => $email]);
    }

    /**
     * @return int Verbleibende Minuten bis zur Entsperrung, 0 = nicht gesperrt
     */
    private function minutenBisEntsperrung(string $spalte, string $wert): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS anzahl, MIN(versucht_am) AS aeltester
             FROM login_versuche
             WHERE {$spalte} = :wert
             AND versucht_am >= DATE_SUB(NOW(), INTERVAL " . self::ZEITFENSTER_MINUTEN . " MINUTE)"
        );

        $statement->execute(['wert' => $wert]);
        $ergebnis = $statement->fetch(PDO::FETCH_ASSOC);

        if ((int) $ergebnis['anzahl'] < self::MAX_VERSUCHE) {
            return 0;
        }

        $aeltesterVersuch = new DateTime($ergebnis['aeltester']);
        $entsperrtAb = (clone $aeltesterVersuch)->modify('+' . self::ZEITFENSTER_MINUTEN . ' minutes');
        $verbleibend = (new DateTime())->diff($entsperrtAb);

        $minuten = ($verbleibend->days * 24 * 60) + ($verbleibend->h * 60) + $verbleibend->i;

        return max(1, $minuten);
    }
}
