<?php

declare(strict_types=1);

class WorkingHoursRepository
{
    private PDO $pdo;

    private const WOCHENTAGE = [
        1 => 'Montag',
        2 => 'Dienstag',
        3 => 'Mittwoch',
        4 => 'Donnerstag',
        5 => 'Freitag',
        6 => 'Samstag',
        7 => 'Sonntag',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function wochentagName(int $wochentag): string
    {
        return self::WOCHENTAGE[$wochentag] ?? '';
    }

    /**
     * Gibt für jeden Wochentag (1-7) die Arbeitszeit zurück. Tage ohne
     * gespeicherten Eintrag werden als "frei" befüllt.
     */
    public function getForEmployee(int $employeeId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT wochentag, ist_frei, start_zeit, end_zeit
             FROM arbeitszeiten
             WHERE employee_id = :employee_id"
        );
        $statement->execute(['employee_id' => $employeeId]);

        $gespeichert = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $zeile) {
            $gespeichert[(int) $zeile['wochentag']] = $zeile;
        }

        $ergebnis = [];
        foreach (self::WOCHENTAGE as $nummer => $name) {
            $ergebnis[$nummer] = $gespeichert[$nummer] ?? [
                'wochentag' => $nummer,
                'ist_frei' => 1,
                'start_zeit' => null,
                'end_zeit' => null,
            ];
        }

        return $ergebnis;
    }

    /**
     * Speichert die Arbeitszeiten für alle 7 Wochentage (upsert).
     *
     * @param array<int, array{ist_frei: bool, start_zeit: ?string, end_zeit: ?string}> $tage
     */
    public function saveForEmployee(int $employeeId, array $tage): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO arbeitszeiten (employee_id, wochentag, ist_frei, start_zeit, end_zeit)
             VALUES (:employee_id, :wochentag, :ist_frei, :start_zeit, :end_zeit)
             ON DUPLICATE KEY UPDATE
                ist_frei = VALUES(ist_frei),
                start_zeit = VALUES(start_zeit),
                end_zeit = VALUES(end_zeit)"
        );

        foreach (self::WOCHENTAGE as $nummer => $name) {
            $tag = $tage[$nummer] ?? ['ist_frei' => true, 'start_zeit' => null, 'end_zeit' => null];

            $statement->execute([
                'employee_id' => $employeeId,
                'wochentag' => $nummer,
                'ist_frei' => $tag['ist_frei'] ? 1 : 0,
                'start_zeit' => $tag['ist_frei'] ? null : $tag['start_zeit'],
                'end_zeit' => $tag['ist_frei'] ? null : $tag['end_zeit'],
            ]);
        }
    }
}
