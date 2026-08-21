<?php

declare(strict_types=1);

class TimeTrackingRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Gibt den aktuell offenen (noch nicht beendeten) Eintrag zurück,
     * falls der Mitarbeiter gerade eingestempelt ist.
     */
    public function getOffenerEintrag(int $employeeId): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT id, start_zeit
             FROM zeiterfassung
             WHERE employee_id = :employee_id AND end_zeit IS NULL
             LIMIT 1"
        );
        $statement->execute(['employee_id' => $employeeId]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Stempelt ein (Start). Gibt false zurück, wenn bereits eingestempelt.
     */
    public function einstempeln(int $employeeId): bool
    {
        if ($this->getOffenerEintrag($employeeId) !== false) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "INSERT INTO zeiterfassung (employee_id, start_zeit)
             VALUES (:employee_id, NOW())"
        );

        return $statement->execute(['employee_id' => $employeeId]);
    }

    /**
     * Stempelt aus (Ende). Gibt false zurück, wenn nicht eingestempelt.
     */
    public function ausstempeln(int $employeeId): bool
    {
        $offen = $this->getOffenerEintrag($employeeId);

        if ($offen === false) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "UPDATE zeiterfassung
             SET end_zeit = NOW()
             WHERE id = :id"
        );

        return $statement->execute(['id' => $offen['id']]);
    }

    /**
     * Summe der gearbeiteten Stunden (nur abgeschlossene Einträge)
     * im Zeitraum [von, bis).
     */
    public function summeStunden(int $employeeId, string $von, string $bis): float
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, start_zeit, end_zeit)), 0) AS sekunden
             FROM zeiterfassung
             WHERE employee_id = :employee_id
             AND end_zeit IS NOT NULL
             AND start_zeit >= :von
             AND start_zeit < :bis"
        );

        $statement->execute([
            'employee_id' => $employeeId,
            'von' => $von,
            'bis' => $bis,
        ]);

        $sekunden = (float) $statement->fetchColumn();

        return round($sekunden / 3600, 2);
    }

    /**
     * Letzte Einträge eines Mitarbeiters, neueste zuerst.
     */
    public function letzteEintraege(int $employeeId, int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, start_zeit, end_zeit
             FROM zeiterfassung
             WHERE employee_id = :employee_id
             ORDER BY start_zeit DESC
             LIMIT " . max(1, $limit)
        );
        $statement->execute(['employee_id' => $employeeId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Summe der gearbeiteten Stunden für ALLE Mitarbeiter im Zeitraum
     * [von, bis), in einer einzigen Abfrage (für Admin-Statistiken).
     *
     * @return array<int, float> employee_id => Stunden
     */
    public function summeStundenAlleMitarbeiter(string $von, string $bis): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                employee_id,
                COALESCE(SUM(TIMESTAMPDIFF(SECOND, start_zeit, end_zeit)), 0) AS sekunden
             FROM zeiterfassung
             WHERE end_zeit IS NOT NULL
             AND start_zeit >= :von
             AND start_zeit < :bis
             GROUP BY employee_id"
        );
        $statement->execute(['von' => $von, 'bis' => $bis]);

        $ergebnis = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $zeile) {
            $ergebnis[(int) $zeile['employee_id']] = round((float) $zeile['sekunden'] / 3600, 2);
        }

        return $ergebnis;
    }

    /**
     * Mitarbeiter, die aktuell eingestempelt sind (für Live-Übersicht).
     */
    public function getAktuellEingestempelt(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                z.employee_id,
                z.start_zeit,
                CONCAT(e.vor_name, ' ', e.nach_name) AS mitarbeiter_name
             FROM zeiterfassung AS z
             INNER JOIN employees AS e ON z.employee_id = e.id
             WHERE z.end_zeit IS NULL
             ORDER BY z.start_zeit ASC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
