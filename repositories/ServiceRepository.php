<?php

declare(strict_types=1);

class ServiceRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Nur aktive Dienstleistungen - für die Kundenbuchung.
     */
    public function getAllServices(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                id,
                name,
                duration_minutes,
                price,
                photo_url,
                category
             FROM services
             WHERE status = 'aktiv'
             ORDER BY category, name"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function serviceCheck(int $serviceId): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                id,
                name,
                duration_minutes,
                price
             FROM services
             WHERE id = :service_id
             AND status = 'aktiv'
             LIMIT 1"
        );

        $stmt->execute([
            ':service_id' => $serviceId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Alle Dienstleistungen (aktiv + inaktiv) - für die Admin-Verwaltung.
     */
    public function getAllServicesAdmin(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, name, description, duration_minutes, price, status, photo_url, category
             FROM services
             ORDER BY name"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchServices(string $search): array
    {
        $searchValue = '%' . trim($search) . '%';

        $stmt = $this->pdo->prepare(
            "SELECT id, name, description, duration_minutes, price, status, photo_url, category
             FROM services
             WHERE name LIKE :search OR description LIKE :search OR category LIKE :search
             ORDER BY name"
        );

        $stmt->execute(['search' => $searchValue]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getServiceById(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, description, duration_minutes, price, status, photo_url, category
             FROM services
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Bereits verwendete Kategorien (für die Datalist-Vorschläge im Formular).
     */
    public function getAlleKategorien(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT category
             FROM services
             WHERE category IS NOT NULL AND category != ''
             ORDER BY category"
        );

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category');
    }

    /**
     * IDs der meistgebuchten Dienstleistungen (alle Zeit), für die
     * "Beliebt"-Markierung.
     */
    public function getBeliebtesteServiceIds(int $limit = 3): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT tw.service_id, COUNT(*) AS anzahl
             FROM terminwunsche AS tw
             WHERE tw.status IN ('bestaetigt', 'abgeschlossen')
             GROUP BY tw.service_id
             HAVING anzahl > 0
             ORDER BY anzahl DESC
             LIMIT " . max(1, $limit)
        );
        $stmt->execute();

        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'service_id'));
    }

    public function createService(
        string $name,
        ?string $description,
        int $durationMinutes,
        float $price,
        string $status,
        ?string $category = null
    ): bool {
        $stmt = $this->pdo->prepare(
            "INSERT INTO services (name, description, duration_minutes, price, status, category)
             VALUES (:name, :description, :duration_minutes, :price, :status, :category)"
        );

        return $stmt->execute([
            'name' => $name,
            'description' => $description,
            'duration_minutes' => $durationMinutes,
            'price' => $price,
            'status' => $status,
            'category' => $category,
        ]);
    }

    public function getLastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePhotoUrl(int $id, string $photoUrl): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE services SET photo_url = :photo_url WHERE id = :id"
        );

        return $stmt->execute(['id' => $id, 'photo_url' => $photoUrl]);
    }

    public function updateService(
        int $id,
        string $name,
        ?string $description,
        int $durationMinutes,
        float $price,
        string $status,
        ?string $category = null,
        ?string $photoUrl = null
    ): bool {
        $sql = "UPDATE services
             SET name = :name,
                 description = :description,
                 duration_minutes = :duration_minutes,
                 price = :price,
                 status = :status,
                 category = :category";

        if ($photoUrl !== null) {
            $sql .= ", photo_url = :photo_url";
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $params = [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'duration_minutes' => $durationMinutes,
            'price' => $price,
            'status' => $status,
            'category' => $category,
        ];

        if ($photoUrl !== null) {
            $params['photo_url'] = $photoUrl;
        }

        return $stmt->execute($params);
    }

    public function changeStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['aktiv', 'inaktiv'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE services SET status = :status WHERE id = :id"
        );

        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function deleteService(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM services WHERE id = :id");

        return $stmt->execute(['id' => $id]);
    }
}
