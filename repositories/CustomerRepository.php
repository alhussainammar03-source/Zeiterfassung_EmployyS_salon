<?php

declare(strict_types=1);

class CustomerRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllCustomers(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                id,
                vor_name,
                nach_name,
                email,
                telefon1,
                telefon2,
                status,
                created_at
             FROM `user`
             ORDER BY id ASC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchCustomers(string $search): array
    {
        $searchValue = '%' . trim($search) . '%';

        $statement = $this->pdo->prepare(
            "SELECT
                id,
                vor_name,
                nach_name,
                email,
                telefon1,
                telefon2,
                status,
                created_at
             FROM `user`
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
            'search_email' => $searchValue,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCustomerById(int $id): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT *
             FROM `user`
             WHERE id = :id
             LIMIT 1"
        );

        $statement->execute(['id' => $id]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Anzahl der Terminwünsche (aktuell oder je) dieses Kunden.
     * Für die Übersicht, z.B. "5 Termine bisher".
     */
    public function countTerminwuenscheVonKunde(int $customerId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM terminwunsche WHERE customer_id = :customer_id"
        );

        $statement->execute(['customer_id' => $customerId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Aktualisiert die vom Kunden selbst änderbaren Felder:
     * Name, Geschlecht, Telefon, Adresse.
     */
    public function updateEigeneKontaktdaten(
        int $id,
        string $vorName,
        string $nachName,
        string $geschlecht,
        ?string $telefon1,
        ?string $telefon2,
        string $strasse,
        int $hausNum,
        int $plz,
        string $stadt
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE `user`
             SET vor_name = :vor_name, nach_name = :nach_name, geschlecht = :geschlecht,
                 telefon1 = :telefon1, telefon2 = :telefon2, strasse = :strasse,
                 haus_num = :haus_num, plz = :plz, stadt = :stadt
             WHERE id = :id"
        );

        return $statement->execute([
            'id' => $id,
            'vor_name' => $vorName,
            'nach_name' => $nachName,
            'geschlecht' => $geschlecht,
            'telefon1' => $telefon1,
            'telefon2' => $telefon2,
            'strasse' => $strasse,
            'haus_num' => $hausNum,
            'plz' => $plz,
            'stadt' => $stadt,
        ]);
    }

    /**
     * Aktualisiert Kundendaten durch den Admin - im Gegensatz zu
     * updateEigeneKontaktdaten() darf hier auch die E-Mail geändert werden.
     */
    public function updateAlsAdmin(
        int $id,
        string $vorName,
        string $nachName,
        string $geschlecht,
        string $email,
        ?string $telefon1,
        ?string $telefon2,
        string $strasse,
        int $hausNum,
        int $plz,
        string $stadt
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE `user`
             SET vor_name = :vor_name, nach_name = :nach_name, geschlecht = :geschlecht,
                 email = :email, telefon1 = :telefon1, telefon2 = :telefon2,
                 strasse = :strasse, haus_num = :haus_num, plz = :plz, stadt = :stadt
             WHERE id = :id"
        );

        return $statement->execute([
            'id' => $id,
            'vor_name' => $vorName,
            'nach_name' => $nachName,
            'geschlecht' => $geschlecht,
            'email' => $email,
            'telefon1' => $telefon1,
            'telefon2' => $telefon2,
            'strasse' => $strasse,
            'haus_num' => $hausNum,
            'plz' => $plz,
            'stadt' => $stadt,
        ]);
    }

    /**
     * Prüft, ob eine E-Mail bereits von einem anderen Kunden genutzt wird
     * (für die Admin-Bearbeitung, analog zu employeeRepository::emailExists).
     */
    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = "SELECT id FROM `user` WHERE email = :email";
        $params = ['email' => $email];

        if ($exceptId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $exceptId;
        }

        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return $statement->fetch() !== false;
    }

    public function updatePhotoUrl(int $id, string $photoUrl): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE `user` SET photo_url = :photo_url WHERE id = :id"
        );

        return $statement->execute(['id' => $id, 'photo_url' => $photoUrl]);
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE `user` SET password = :password WHERE id = :id"
        );

        return $statement->execute(['id' => $id, 'password' => $passwordHash]);
    }

    public function changeStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['aktiv', 'inaktiv'], true)) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "UPDATE `user`
             SET status = :status
             WHERE id = :id"
        );

        return $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }

    public function deleteCustomer(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "DELETE FROM `user` WHERE id = :id"
        );

        return $statement->execute(['id' => $id]);
    }

    public function countAllCustomers(): int
    {
        $statement = $this->pdo->query("SELECT COUNT(*) FROM `user`");

        return (int) $statement->fetchColumn();
    }

    public function countActiveCustomers(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM `user` WHERE status = 'aktiv'"
        );

        return (int) $statement->fetchColumn();
    }
}
