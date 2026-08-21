<?php
require_once __DIR__ . '/../models/Employee.php';
class EmployeeRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllEmployees(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                id,
                vor_name,
                nach_name,
                email,
                telefon,
                position,
                role,
                status,
                photo_url
             FROM employees
             ORDER BY id ASC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllActiveEmployees(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                id,
                vor_name,
                nach_name,
                email,
                telefon,
                position,
                photo_url,
                status
             FROM employees
             WHERE status = 'aktiv'
             ORDER BY id ASC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktive Mitarbeiter (Rolle "mitarbeiter") inkl. Sollstunden,
     * fÃ¼r die Admin-Zeiterfassungs-Statistik.
     */
    public function getAllActiveMitarbeiterMitSollstunden(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                id,
                vor_name,
                nach_name,
                soll_stunden_woche
             FROM employees
             WHERE status = 'aktiv' AND role = 'mitarbeiter'
             ORDER BY vor_name, nach_name"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployeeById(int $id): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT *
             FROM employees
             WHERE id = :id
             LIMIT 1"
        );

        $statement->execute([
            'id' => $id
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function getEmployeeByEmail(string $email): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT *
             FROM employees
             WHERE email = :email
             LIMIT 1"
        );

        $statement->execute([
            'email' => $email
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function searchEmployees(string $search): array
    {
        $searchValue = '%' . trim($search) . '%';

        $statement = $this->pdo->prepare(
            "SELECT
            id,
            vor_name,
            nach_name,
            email,
            telefon,
            position,
            role,
            status,
            photo_url
         FROM employees
         WHERE
            CAST(id AS CHAR) LIKE :search_id
            OR vor_name LIKE :search_first_name
            OR nach_name LIKE :search_last_name
            OR CONCAT(vor_name, ' ', nach_name) LIKE :search_full_name
            OR email LIKE :search_email
         ORDER BY vor_name, nach_name"
        );

        $statement->execute([
            'search_id' => $searchValue,
            'search_first_name' => $searchValue,
            'search_last_name' => $searchValue,
            'search_full_name' => $searchValue,
            'search_email' => $searchValue
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createEmployee(
        Employee $employee,
        string $passwordHash
    ): bool {
        $statement = $this->pdo->prepare(
            "
        INSERT INTO employees (
            vor_name,
            nach_name,
            email,
            telefon,
            strasse,
            haus_num,
            plz,
            stadt,
            geschlecht,
            password,
            position,
            gehalt,
            eintrittsdatum,
            role,
            status,
            photo_url,
            soll_stunden_woche,
            urlaubstage_jahr
        )
        VALUES (
            :vor_name,
            :nach_name,
            :email,
            :telefon,
            :strasse,
            :haus_num,
            :plz,
            :stadt,
            :geschlecht,
            :password,
            :position,
            :gehalt,
            :eintrittsdatum,
            :role,
            :status,
            :photo_url,
            :soll_stunden_woche,
            :urlaubstage_jahr
        )
        "
        );

        return $statement->execute([
            ':vor_name' => $employee->vorName,
            ':nach_name' => $employee->nachName,
            ':email' => $employee->email,
            ':telefon' => $employee->telefon,
            ':strasse' => $employee->strasse,
            ':haus_num' => $employee->hausNum,
            ':plz' => $employee->plz,
            ':stadt' => $employee->stadt,
            ':geschlecht' => $employee->geschlecht,
            ':password' => $passwordHash,
            ':position' => $employee->position,
            ':gehalt' => $employee->gehalt,
            ':eintrittsdatum' => $employee->eintrittsdatum,
            ':role' => $employee->rolle,
            ':status' => $employee->status,
            ':photo_url' => $employee->photoUrl,
            ':soll_stunden_woche' => $employee->sollStundenWoche,
            ':urlaubstage_jahr' => $employee->urlaubstageJahr
        ]);
    }

    public function updateEmployee(
        Employee $employee,
        ?string $passwordHash = null
    ): bool {
        $sql = "
        UPDATE employees
        SET
            vor_name = :vor_name,
            nach_name = :nach_name,
            email = :email,
            telefon = :telefon,
            strasse = :strasse,
            haus_num = :haus_num,
            plz = :plz,
            stadt = :stadt,
            geschlecht = :geschlecht,
            position = :position,
            gehalt = :gehalt,
            eintrittsdatum = :eintrittsdatum,
            role = :role,
            status = :status,
            soll_stunden_woche = :soll_stunden_woche,
            urlaubstage_jahr = :urlaubstage_jahr
    ";

        if ($passwordHash !== null) {
            $sql .= ", password = :password";
        }

        if ($employee->photoUrl !== null) {
            $sql .= ", photo_url = :photo_url";
        }

        $sql .= " WHERE id = :id";

        $statement = $this->pdo->prepare($sql);

        $parameters = [
            ':id' => $employee->id,
            ':vor_name' => $employee->vorName,
            ':nach_name' => $employee->nachName,
            ':email' => $employee->email,
            ':telefon' => $employee->telefon,
            ':strasse' => $employee->strasse,
            ':haus_num' => $employee->hausNum,
            ':plz' => $employee->plz,
            ':stadt' => $employee->stadt,
            ':geschlecht' => $employee->geschlecht,
            ':position' => $employee->position,
            ':gehalt' => $employee->gehalt,
            ':eintrittsdatum' => $employee->eintrittsdatum,
            ':role' => $employee->rolle,
            ':status' => $employee->status,
            ':soll_stunden_woche' => $employee->sollStundenWoche,
            ':urlaubstage_jahr' => $employee->urlaubstageJahr
        ];

        if ($passwordHash !== null) {
            $parameters[':password'] = $passwordHash;
        }

        if ($employee->photoUrl !== null) {
            $parameters[':photo_url'] = $employee->photoUrl;
        }

        return $statement->execute($parameters);
    }

    public function getLastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePhotoUrl(int $id, string $photoUrl): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE employees
             SET photo_url = :photo_url
             WHERE id = :id"
        );

        return $statement->execute([
            ':photo_url' => $photoUrl,
            ':id' => $id
        ]);
    }

    /**
     * Aktualisiert die vom Mitarbeiter selbst Ã¤nderbaren Felder:
     * Name, Geschlecht, Telefon, Adresse. (Rolle/Status/Gehalt bleiben
     * dem Admin vorbehalten.)
     */
    public function updateEigeneKontaktdaten(
        int $id,
        string $vorName,
        string $nachName,
        string $geschlecht,
        ?string $telefon,
        string $strasse,
        int $hausNum,
        int $plz,
        string $stadt
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE employees
             SET vor_name = :vor_name, nach_name = :nach_name, geschlecht = :geschlecht,
                 telefon = :telefon, strasse = :strasse, haus_num = :haus_num,
                 plz = :plz, stadt = :stadt
             WHERE id = :id"
        );

        return $statement->execute([
            'id' => $id,
            'vor_name' => $vorName,
            'nach_name' => $nachName,
            'geschlecht' => $geschlecht,
            'telefon' => $telefon,
            'strasse' => $strasse,
            'haus_num' => $hausNum,
            'plz' => $plz,
            'stadt' => $stadt,
        ]);
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE employees SET password = :password WHERE id = :id"
        );

        return $statement->execute(['id' => $id, 'password' => $passwordHash]);
    }

    public function deleteEmployee(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "DELETE FROM employees
             WHERE id = :id"
        );

        return $statement->execute([
            'id' => $id
        ]);
    }

    public function changeStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['aktiv', 'inaktiv'], true)) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "UPDATE employees
             SET status = :status
             WHERE id = :id"
        );

        return $statement->execute([
            'id' => $id,
            'status' => $status
        ]);
    }

    public function emailExists(
        string $email,
        ?int $excludeEmployeeId = null
    ): bool {
        $sql = "
            SELECT id
            FROM employees
            WHERE email = :email
        ";

        $parameters = [
            'email' => $email
        ];

        if ($excludeEmployeeId !== null) {
            $sql .= " AND id != :exclude_id";
            $parameters['exclude_id'] = $excludeEmployeeId;
        }

        $sql .= " LIMIT 1";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function countAllEmployees(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM employees"
        );

        return (int) $statement->fetchColumn();
    }

    public function countActiveEmployees(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*)
             FROM employees
             WHERE status = 'aktiv'"
        );

        return (int) $statement->fetchColumn();
    }
}
