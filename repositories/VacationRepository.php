<?php

declare(strict_types=1);

class VacationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Zählt Werktage (Mo-Fr) im Zeitraum inklusive Start- und End-Datum.
     */
    public static function berechneWerktage(string $start, string $end): float
    {
        $startDatum = new DateTime($start);
        $endDatum = new DateTime($end);

        if ($startDatum > $endDatum) {
            return 0;
        }

        $tage = 0;
        $aktuell = clone $startDatum;

        while ($aktuell <= $endDatum) {
            $wochentag = (int) $aktuell->format('N');

            if ($wochentag <= 5) {
                $tage++;
            }

            $aktuell->modify('+1 day');
        }

        return (float) $tage;
    }

    public function getUrlaubstageJahr(int $employeeId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT urlaubstage_jahr FROM employees WHERE id = :id LIMIT 1"
        );
        $statement->execute(['id' => $employeeId]);

        $wert = $statement->fetchColumn();

        return $wert !== false ? (int) $wert : 30;
    }

    /**
     * Genehmigte Urlaubstage im angegebenen Jahr (Basis: Start-Datum).
     */
    public function getGenommeneTage(int $employeeId, int $jahr): float
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(anzahl_tage), 0)
             FROM urlaubsantraege
             WHERE employee_id = :employee_id
             AND status = 'genehmigt'
             AND YEAR(start_datum) = :jahr"
        );
        $statement->execute([
            'employee_id' => $employeeId,
            'jahr' => $jahr,
        ]);

        return (float) $statement->fetchColumn();
    }

    /**
     * Bereits beantragte ODER genehmigte Tage, die sich mit dem
     * angegebenen Zeitraum überschneiden (zur Konflikt-Prüfung).
     */
    public function hatUeberschneidung(int $employeeId, string $start, string $end): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM urlaubsantraege
             WHERE employee_id = :employee_id
             AND status IN ('beantragt', 'genehmigt')
             AND start_datum <= :end_datum
             AND end_datum >= :start_datum
             LIMIT 1"
        );
        $statement->execute([
            'employee_id' => $employeeId,
            'start_datum' => $start,
            'end_datum' => $end,
        ]);

        return $statement->fetch() !== false;
    }

    public function beantragen(
        int $employeeId,
        string $start,
        string $end,
        float $anzahlTage,
        ?string $notiz = null
    ): bool {
        $statement = $this->pdo->prepare(
            "INSERT INTO urlaubsantraege (employee_id, start_datum, end_datum, anzahl_tage, mitarbeiter_notiz)
             VALUES (:employee_id, :start_datum, :end_datum, :anzahl_tage, :notiz)"
        );

        return $statement->execute([
            'employee_id' => $employeeId,
            'start_datum' => $start,
            'end_datum' => $end,
            'anzahl_tage' => $anzahlTage,
            'notiz' => $notiz,
        ]);
    }

    public function getForEmployee(int $employeeId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, start_datum, end_datum, anzahl_tage, mitarbeiter_notiz, status, admin_kommentar, erstellt_am
             FROM urlaubsantraege
             WHERE employee_id = :employee_id
             ORDER BY start_datum DESC"
        );
        $statement->execute(['employee_id' => $employeeId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllRequests(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                u.id, u.employee_id, u.start_datum, u.end_datum, u.anzahl_tage,
                u.mitarbeiter_notiz, u.status, u.admin_kommentar, u.erstellt_am,
                CONCAT(e.vor_name, ' ', e.nach_name) AS mitarbeiter_name
             FROM urlaubsantraege AS u
             INNER JOIN employees AS e ON u.employee_id = e.id
             ORDER BY
                (u.status = 'beantragt') DESC,
                u.start_datum ASC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM urlaubsantraege WHERE id = :id LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Anzahl noch offener (unbeantworteter) Urlaubsanträge - für
     * das Benachrichtigungs-Badge im Admin-Bereich.
     */
    public function countOffen(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM urlaubsantraege WHERE status = 'beantragt'"
        );

        return (int) $statement->fetchColumn();
    }

    public function changeStatus(int $id, string $status, ?string $adminKommentar = null): bool
    {
        if (!in_array($status, ['genehmigt', 'abgelehnt', 'beantragt'], true)) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "UPDATE urlaubsantraege SET status = :status, admin_kommentar = :admin_kommentar WHERE id = :id"
        );

        return $statement->execute([
            'id' => $id,
            'status' => $status,
            'admin_kommentar' => $adminKommentar,
        ]);
    }
}
