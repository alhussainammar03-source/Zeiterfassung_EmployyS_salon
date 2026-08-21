<?php

declare(strict_types=1);

class TerminSlotRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Gibt alle noch freien Termine zurück (ab heute), gruppiert nach Datum.
     */
    public function getFreieTermine(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, datum, uhrzeit, dauer_minuten
             FROM termin
             WHERE ist_gebucht = 0 AND datum >= CURDATE()
             ORDER BY datum ASC, uhrzeit ASC"
        );

        $statement->execute();
        $slots = $statement->fetchAll();

        $gruppiert = [];

        foreach ($slots as $slot) {
            $gruppiert[$slot['datum']][] = $slot;
        }

        return $gruppiert;
    }

    /**
     * Gibt alle Zeitslots zurück (ab heute), egal ob gebucht oder frei.
     * Für die Admin-Übersicht.
     */
    public function getAllSlots(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, datum, uhrzeit, dauer_minuten, ist_gebucht
             FROM termin
             WHERE datum >= CURDATE()
             ORDER BY datum ASC, uhrzeit ASC"
        );

        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Admin schlägt einen neuen freien Termin-Slot vor (fügt ihn hinzu).
     *
     * Gibt false zurück, wenn zu diesem Datum/Uhrzeit bereits ein Slot
     * existiert (UNIQUE KEY datum+uhrzeit in der Tabelle).
     */
    public function proposeSlot(
        string $datum,
        string $uhrzeit,
        int $dauerMinuten = 30
    ): bool {
        $statement = $this->pdo->prepare(
            "INSERT INTO termin (datum, uhrzeit, dauer_minuten, ist_gebucht)
             VALUES (:datum, :uhrzeit, :dauer_minuten, 0)"
        );

        try {
            return $statement->execute([
                ':datum' => $datum,
                ':uhrzeit' => $uhrzeit,
                ':dauer_minuten' => $dauerMinuten,
            ]);
        } catch (PDOException $exception) {
            // Duplikat (gleiches Datum + Uhrzeit existiert schon)
            if ((int) $exception->getCode() === 23000) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Storniert (löscht) einen Termin-Slot aus der Tabelle "termin".
     *
     * Hinweis: Es gibt keine Datenbank-Verknüpfung zwischen "termin"
     * und "terminwunsche" (kein gemeinsamer Fremdschlüssel). Eine
     * eventuell dazu passende Buchung in "terminwunsche" wird durch
     * diese Methode NICHT automatisch storniert.
     */
    public function cancelSlot(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "DELETE FROM termin WHERE id = :id"
        );

        return $statement->execute([':id' => $id]);
    }

    public function getSlotById(int $id): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT id, datum, uhrzeit, dauer_minuten, ist_gebucht
             FROM termin
             WHERE id = :id
             LIMIT 1"
        );

        $statement->execute([':id' => $id]);

        return $statement->fetch();
    }

    /**
     * Gibt alle Termin-Slots eines bestimmten Tages zurück.
     */
    public function getSlotsByDatum(string $datum): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, datum, uhrzeit, dauer_minuten, ist_gebucht
             FROM termin
             WHERE datum = :datum
             ORDER BY uhrzeit ASC"
        );

        $statement->execute([':datum' => $datum]);

        return $statement->fetchAll();
    }

    /**
     * Bearbeitet einen bestehenden Termin-Slot (Datum, Uhrzeit, Dauer).
     */
    public function updateSlot(
        int $id,
        string $datum,
        string $uhrzeit,
        int $dauerMinuten
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE termin
             SET datum = :datum, uhrzeit = :uhrzeit, dauer_minuten = :dauer_minuten
             WHERE id = :id"
        );

        return $statement->execute([
            ':datum' => $datum,
            ':uhrzeit' => $uhrzeit,
            ':dauer_minuten' => $dauerMinuten,
            ':id' => $id,
        ]);
    }

    /**
     * Erzeugt Termin-Slots für einen einzelnen Tag im Zeitraum
     * $startZeit bis $endZeit, im Abstand von $dauerMinuten.
     *
     * @return array{erstellt: int, uebersprungen: int}
     */
    public function proposeDay(
        string $datum,
        string $startZeit,
        string $endZeit,
        int $dauerMinuten
    ): array {
        return $this->generateDaySlots(
            $datum,
            $startZeit,
            $endZeit,
            $dauerMinuten
        );
    }

    /**
     * Erzeugt Termin-Slots für eine ganze Woche (7 Tage ab $startDatum),
     * aber nur für die in $wochentage ausgewählten Wochentage.
     *
     * @param int[] $wochentage 1 = Montag ... 7 = Sonntag (siehe date('N'))
     * @return array{erstellt: int, uebersprungen: int}
     */
    public function proposeWeek(
        string $startDatum,
        array $wochentage,
        string $startZeit,
        string $endZeit,
        int $dauerMinuten
    ): array {
        $gesamtErstellt = 0;
        $gesamtUebersprungen = 0;

        $datum = DateTime::createFromFormat('Y-m-d', $startDatum);

        if ($datum === false) {
            return ['erstellt' => 0, 'uebersprungen' => 0];
        }

        for ($i = 0; $i < 7; $i++) {
            $wochentag = (int) $datum->format('N');

            if (in_array($wochentag, $wochentage, true)) {
                $ergebnis = $this->generateDaySlots(
                    $datum->format('Y-m-d'),
                    $startZeit,
                    $endZeit,
                    $dauerMinuten
                );

                $gesamtErstellt += $ergebnis['erstellt'];
                $gesamtUebersprungen += $ergebnis['uebersprungen'];
            }

            $datum->modify('+1 day');
        }

        return [
            'erstellt' => $gesamtErstellt,
            'uebersprungen' => $gesamtUebersprungen,
        ];
    }

    /**
     * Erzeugt für einen Tag im Zeitraum $startZeit bis $endZeit
     * fortlaufend Slots im Abstand von $dauerMinuten. Duplikate
     * (Slot existiert schon) werden übersprungen statt zu fehlern.
     *
     * @return array{erstellt: int, uebersprungen: int}
     */
    private function generateDaySlots(
        string $datum,
        string $startZeit,
        string $endZeit,
        int $dauerMinuten
    ): array {
        $erstellt = 0;
        $uebersprungen = 0;

        if ($dauerMinuten <= 0) {
            return ['erstellt' => 0, 'uebersprungen' => 0];
        }

        $aktuelleZeit = DateTime::createFromFormat('H:i', $startZeit);
        $endZeitObjekt = DateTime::createFromFormat('H:i', $endZeit);

        if ($aktuelleZeit === false || $endZeitObjekt === false) {
            return ['erstellt' => 0, 'uebersprungen' => 0];
        }

        while ($aktuelleZeit < $endZeitObjekt) {
            $uhrzeit = $aktuelleZeit->format('H:i:s');

            if ($this->proposeSlot($datum, $uhrzeit, $dauerMinuten)) {
                $erstellt++;
            } else {
                $uebersprungen++;
            }

            $aktuelleZeit->modify('+' . $dauerMinuten . ' minutes');
        }

        return ['erstellt' => $erstellt, 'uebersprungen' => $uebersprungen];
    }
}
