<?php

declare(strict_types=1);

class SickLeaveRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function melden(
        int $employeeId,
        string $start,
        ?string $end,
        ?string $auDateiUrl
    ): bool {
        $statement = $this->pdo->prepare(
            "INSERT INTO krankmeldungen (employee_id, start_datum, end_datum, au_datei_url)
             VALUES (:employee_id, :start_datum, :end_datum, :au_datei_url)"
        );

        return $statement->execute([
            'employee_id' => $employeeId,
            'start_datum' => $start,
            'end_datum' => $end,
            'au_datei_url' => $auDateiUrl,
        ]);
    }

    public function getForEmployee(int $employeeId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, start_datum, end_datum, au_datei_url, erstellt_am
             FROM krankmeldungen
             WHERE employee_id = :employee_id
             ORDER BY start_datum DESC"
        );
        $statement->execute(['employee_id' => $employeeId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllForAdmin(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                k.id, k.start_datum, k.end_datum, k.au_datei_url, k.admin_gelesen, k.erstellt_am,
                CONCAT(e.vor_name, ' ', e.nach_name) AS mitarbeiter_name
             FROM krankmeldungen AS k
             INNER JOIN employees AS e ON k.employee_id = e.id
             ORDER BY k.start_datum DESC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Anzahl ungelesener Krankmeldungen - für das Benachrichtigungs-Badge
     * im Admin-Bereich.
     */
    public function countUngelesen(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM krankmeldungen WHERE admin_gelesen = 0"
        );

        return (int) $statement->fetchColumn();
    }

    /**
     * Markiert alle Krankmeldungen als gelesen - wird aufgerufen,
     * sobald der Admin die Übersichtsseite öffnet.
     */
    public function alleAlsGelesenMarkieren(): void
    {
        $this->pdo->exec("UPDATE krankmeldungen SET admin_gelesen = 1 WHERE admin_gelesen = 0");
    }
}
