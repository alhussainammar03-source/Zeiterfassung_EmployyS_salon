<?php

declare(strict_types=1);

class PromotionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Aktive Aktionen, die noch nicht abgelaufen sind - für Kunden.
     */
    public function getAktiv(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, title, description, icon, valid_until
             FROM promotions
             WHERE status = 'aktiv'
             AND (valid_until IS NULL OR valid_until >= CURDATE())
             ORDER BY created_at DESC
             LIMIT " . max(1, $limit)
        );
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(): array
    {
        $statement = $this->pdo->query(
            "SELECT id, title, description, icon, valid_until, status, created_at
             FROM promotions
             ORDER BY created_at DESC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT id, title, description, icon, valid_until, status
             FROM promotions
             WHERE id = :id
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function create(
        string $title,
        string $description,
        string $icon,
        ?string $validUntil,
        string $status
    ): bool {
        $statement = $this->pdo->prepare(
            "INSERT INTO promotions (title, description, icon, valid_until, status)
             VALUES (:title, :description, :icon, :valid_until, :status)"
        );

        return $statement->execute([
            'title' => $title,
            'description' => $description,
            'icon' => $icon !== '' ? $icon : '🎉',
            'valid_until' => $validUntil,
            'status' => $status,
        ]);
    }

    public function update(
        int $id,
        string $title,
        string $description,
        string $icon,
        ?string $validUntil,
        string $status
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE promotions
             SET title = :title, description = :description, icon = :icon,
                 valid_until = :valid_until, status = :status
             WHERE id = :id"
        );

        return $statement->execute([
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'icon' => $icon !== '' ? $icon : '🎉',
            'valid_until' => $validUntil,
            'status' => $status,
        ]);
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare("DELETE FROM promotions WHERE id = :id");

        return $statement->execute(['id' => $id]);
    }
}
