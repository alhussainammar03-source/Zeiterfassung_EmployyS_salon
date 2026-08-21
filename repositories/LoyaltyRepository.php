<?php

declare(strict_types=1);

class LoyaltyRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSettings(): array
    {
        $statement = $this->pdo->query(
            "SELECT points_per_euro, threshold_points, reward_text
             FROM loyalty_settings
             WHERE id = 1
             LIMIT 1"
        );

        $settings = $statement->fetch(PDO::FETCH_ASSOC);

        return $settings !== false ? $settings : [
            'points_per_euro' => 1.0,
            'threshold_points' => 1000,
            'reward_text' => '20€ Gutschein',
        ];
    }

    public function updateSettings(
        float $pointsPerEuro,
        int $thresholdPoints,
        string $rewardText
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE loyalty_settings
             SET points_per_euro = :points_per_euro,
                 threshold_points = :threshold_points,
                 reward_text = :reward_text
             WHERE id = 1"
        );

        return $statement->execute([
            'points_per_euro' => $pointsPerEuro,
            'threshold_points' => $thresholdPoints,
            'reward_text' => $rewardText,
        ]);
    }

    public function getPunkte(int $customerId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT loyalty_points FROM `user` WHERE id = :id LIMIT 1"
        );
        $statement->execute(['id' => $customerId]);

        $wert = $statement->fetchColumn();

        return $wert !== false ? (int) $wert : 0;
    }

    /**
     * Vergibt Punkte für einen abgeschlossenen Termin, basierend auf
     * dem Preis der Dienstleistung und den aktuellen Einstellungen.
     */
    public function punkteVergebenFuerBetrag(int $customerId, float $betrag): int
    {
        $settings = $this->getSettings();
        $punkte = (int) round($betrag * (float) $settings['points_per_euro']);

        if ($punkte <= 0) {
            return 0;
        }

        $statement = $this->pdo->prepare(
            "UPDATE `user` SET loyalty_points = loyalty_points + :punkte WHERE id = :id"
        );
        $statement->execute(['punkte' => $punkte, 'id' => $customerId]);

        return $punkte;
    }

    /**
     * Löst die Prämie ein: zieht die Schwellen-Punktzahl ab und
     * erstellt einen Gutschein-Code, den der Kunde im Salon vorzeigen kann.
     *
     * @return string|null Der Gutschein-Code, oder null wenn nicht genug Punkte.
     */
    public function praemieEinloesen(int $customerId): ?string
    {
        $settings = $this->getSettings();
        $aktuellePunkte = $this->getPunkte($customerId);

        if ($aktuellePunkte < (int) $settings['threshold_points']) {
            return null;
        }

        $code = strtoupper(bin2hex(random_bytes(4)));

        $this->pdo->beginTransaction();

        try {
            $updateStatement = $this->pdo->prepare(
                "UPDATE `user` SET loyalty_points = loyalty_points - :punkte WHERE id = :id"
            );
            $updateStatement->execute([
                'punkte' => (int) $settings['threshold_points'],
                'id' => $customerId,
            ]);

            $insertStatement = $this->pdo->prepare(
                "INSERT INTO loyalty_redemptions (customer_id, code, punkte, reward_text)
                 VALUES (:customer_id, :code, :punkte, :reward_text)"
            );
            $insertStatement->execute([
                'customer_id' => $customerId,
                'code' => $code,
                'punkte' => (int) $settings['threshold_points'],
                'reward_text' => $settings['reward_text'],
            ]);

            $this->pdo->commit();

            return $code;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function getRedemptionsForCustomer(int $customerId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, code, punkte, reward_text, status, erstellt_am, eingeloest_am
             FROM loyalty_redemptions
             WHERE customer_id = :customer_id
             ORDER BY erstellt_am DESC"
        );
        $statement->execute(['customer_id' => $customerId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllRedemptions(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                lr.id, lr.code, lr.punkte, lr.reward_text, lr.status,
                lr.erstellt_am, lr.eingeloest_am,
                CONCAT(u.vor_name, ' ', u.nach_name) AS kunden_name
             FROM loyalty_redemptions AS lr
             INNER JOIN `user` AS u ON lr.customer_id = u.id
             ORDER BY (lr.status = 'offen') DESC, lr.erstellt_am DESC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markiereEingeloest(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE loyalty_redemptions
             SET status = 'eingeloest', eingeloest_am = NOW()
             WHERE id = :id AND status = 'offen'"
        );

        return $statement->execute(['id' => $id]);
    }
}
