<?php

declare(strict_types=1);

/**
 * Statistiken für admin/reports.php: Umsatz, Mitarbeiter-Auslastung,
 * meistgebuchte Dienstleistungen.
 *
 * Umsatz basiert auf abgeschlossenen Terminen (status = 'abgeschlossen'),
 * da das den tatsächlich realisierten Umsatz darstellt.
 */
class ReportRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getUmsatzGesamt(string $von, string $bis): float
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(s.price), 0)
             FROM terminwunsche AS tw
             INNER JOIN services AS s ON tw.service_id = s.id
             WHERE tw.status = 'abgeschlossen'
             AND tw.terminwunsche_start >= :von
             AND tw.terminwunsche_start < :bis"
        );
        $statement->execute(['von' => $von, 'bis' => $bis]);

        return (float) $statement->fetchColumn();
    }

    /**
     * Umsatz gruppiert nach Woche (Montag als Wochenbeginn).
     */
    public function getUmsatzProWoche(string $von, string $bis): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                DATE(DATE_SUB(tw.terminwunsche_start, INTERVAL WEEKDAY(tw.terminwunsche_start) DAY)) AS wochenbeginn,
                COALESCE(SUM(s.price), 0) AS umsatz
             FROM terminwunsche AS tw
             INNER JOIN services AS s ON tw.service_id = s.id
             WHERE tw.status = 'abgeschlossen'
             AND tw.terminwunsche_start >= :von
             AND tw.terminwunsche_start < :bis
             GROUP BY wochenbeginn
             ORDER BY wochenbeginn ASC"
        );
        $statement->execute(['von' => $von, 'bis' => $bis]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Umsatz gruppiert nach Monat.
     */
    public function getUmsatzProMonat(string $von, string $bis): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                DATE_FORMAT(tw.terminwunsche_start, '%Y-%m') AS monat,
                COALESCE(SUM(s.price), 0) AS umsatz
             FROM terminwunsche AS tw
             INNER JOIN services AS s ON tw.service_id = s.id
             WHERE tw.status = 'abgeschlossen'
             AND tw.terminwunsche_start >= :von
             AND tw.terminwunsche_start < :bis
             GROUP BY monat
             ORDER BY monat ASC"
        );
        $statement->execute(['von' => $von, 'bis' => $bis]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gebuchte Stunden (bestätigt + abgeschlossen) pro Mitarbeiter
     * im Zeitraum, für die Auslastungs-Berechnung.
     *
     * @return array<int, float> employee_id => Stunden
     */
    public function getGebuchteStundenProMitarbeiter(string $von, string $bis): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                tw.employee_id,
                COALESCE(SUM(TIMESTAMPDIFF(SECOND, tw.terminwunsche_start, tw.terminwunsche_ende)), 0) AS sekunden
             FROM terminwunsche AS tw
             WHERE tw.status IN ('bestaetigt', 'abgeschlossen')
             AND tw.terminwunsche_start >= :von
             AND tw.terminwunsche_start < :bis
             GROUP BY tw.employee_id"
        );
        $statement->execute(['von' => $von, 'bis' => $bis]);

        $ergebnis = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $zeile) {
            $ergebnis[(int) $zeile['employee_id']] = round((float) $zeile['sekunden'] / 3600, 2);
        }

        return $ergebnis;
    }

    /**
     * Umsatz gruppiert nach Tag - für den Tagesverlauf-Chart.
     */
    public function getUmsatzProTag(string $von, string $bis): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                DATE(tw.terminwunsche_start) AS tag,
                COALESCE(SUM(s.price), 0) AS umsatz
             FROM terminwunsche AS tw
             INNER JOIN services AS s ON tw.service_id = s.id
             WHERE tw.status = 'abgeschlossen'
             AND tw.terminwunsche_start >= :von
             AND tw.terminwunsche_start < :bis
             GROUP BY tag
             ORDER BY tag ASC"
        );
        $statement->execute(['von' => $von, 'bis' => $bis]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUmsatzZiel(): float
    {
        $statement = $this->pdo->query(
            "SELECT monatliches_umsatzziel FROM report_settings WHERE id = 1 LIMIT 1"
        );

        $wert = $statement->fetchColumn();

        return $wert !== false ? (float) $wert : 30000.0;
    }

    public function setUmsatzZiel(float $ziel): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE report_settings SET monatliches_umsatzziel = :ziel WHERE id = 1"
        );

        return $statement->execute(['ziel' => $ziel]);
    }

    /**
     * Meistgebuchte Dienstleistungen, sortierbar nach Menge oder Umsatz.
     */
    public function getMeistgebuchteDienstleistungenSortiert(
        string $von,
        string $bis,
        string $sortierung = 'menge',
        int $limit = 10
    ): array {
        $sortSpalte = $sortierung === 'umsatz' ? 'umsatz' : 'anzahl';

        $statement = $this->pdo->prepare(
            "SELECT
                s.id,
                s.name,
                COUNT(*) AS anzahl,
                COALESCE(SUM(s.price), 0) AS umsatz
             FROM terminwunsche AS tw
             INNER JOIN services AS s ON tw.service_id = s.id
             WHERE tw.status IN ('bestaetigt', 'abgeschlossen')
             AND tw.terminwunsche_start >= :von
             AND tw.terminwunsche_start < :bis
             GROUP BY s.id, s.name
             ORDER BY {$sortSpalte} DESC
             LIMIT " . max(1, $limit)
        );
        $statement->execute(['von' => $von, 'bis' => $bis]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
