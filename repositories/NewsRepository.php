<?php

declare(strict_types=1);

class NewsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Veröffentlichte News, neueste zuerst - für die Kundenansicht.
     */
    public function getVeroeffentlicht(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, title, content, photo_url, created_at
             FROM salon_news
             WHERE status = 'veroeffentlicht'
             ORDER BY created_at DESC
             LIMIT " . max(1, $limit)
        );
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Alle News (veröffentlicht + Entwurf) - für die Admin-Verwaltung.
     */
    public function getAll(): array
    {
        $statement = $this->pdo->query(
            "SELECT id, title, content, photo_url, status, created_at
             FROM salon_news
             ORDER BY created_at DESC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT id, title, content, photo_url, status, created_at
             FROM salon_news
             WHERE id = :id
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function create(string $title, string $content, string $status): bool
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO salon_news (title, content, status)
             VALUES (:title, :content, :status)"
        );

        return $statement->execute([
            'title' => $title,
            'content' => $content,
            'status' => $status,
        ]);
    }

    public function getLastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePhotoUrl(int $id, string $photoUrl): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE salon_news SET photo_url = :photo_url WHERE id = :id"
        );

        return $statement->execute(['id' => $id, 'photo_url' => $photoUrl]);
    }

    public function update(
        int $id,
        string $title,
        string $content,
        string $status,
        ?string $photoUrl = null
    ): bool {
        $sql = "UPDATE salon_news SET title = :title, content = :content, status = :status";

        if ($photoUrl !== null) {
            $sql .= ", photo_url = :photo_url";
        }

        $sql .= " WHERE id = :id";

        $statement = $this->pdo->prepare($sql);

        $params = [
            'id' => $id,
            'title' => $title,
            'content' => $content,
            'status' => $status,
        ];

        if ($photoUrl !== null) {
            $params['photo_url'] = $photoUrl;
        }

        return $statement->execute($params);
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare("DELETE FROM salon_news WHERE id = :id");

        return $statement->execute(['id' => $id]);
    }
}
