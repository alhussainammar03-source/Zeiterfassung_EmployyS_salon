<?php

class TerminwunschRepository
{




    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllTerminwuensche(string $sortRichtung = 'ASC'): array
    {
        $sortRichtung = strtoupper($sortRichtung) === 'DESC' ? 'DESC' : 'ASC';

        $statement = $this->pdo->query(
            "SELECT
            tw.id,
            tw.customer_id,
            tw.employee_id,
            tw.service_id,
            tw.terminwunsche_start,
            tw.terminwunsche_ende,
            tw.status,
            tw.customer_note,
            tw.created_at,

            CONCAT(k.vor_name, ' ', k.nach_name) AS kunden_name,
            k.email AS kunden_email,
            k.telefon1  AS kunden_telefon1,
            k.telefon2 AS kunden_telefon2,

            CONCAT(m.vor_name, ' ', m.nach_name) AS mitarbeiter_name,

            d.name AS dienstleistung_name

        FROM terminwunsche AS tw

        INNER JOIN user AS k
            ON tw.customer_id = k.id

        INNER JOIN employees AS m
            ON tw.employee_id = m.id

        INNER JOIN services AS d
            ON tw.service_id = d.id

        ORDER BY tw.terminwunsche_start {$sortRichtung}"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }





    public function isAvailable(
        int $employeeId,
        string $terminStart,
        string $terminEnd
    ): bool {
        $statement = $this->pdo->prepare(
            "SELECT id
         FROM terminwunsche
         WHERE employee_id = :employee_id
         AND status = 'bestaetigt'
         AND terminwunsche_start < :termin_end
         AND terminwunsche_ende > :termin_start
         LIMIT 1"
        );

        $statement->execute([
            ':employee_id' => $employeeId,
            ':termin_start' => $terminStart,
            ':termin_end' => $terminEnd
        ]);

        $conflict = $statement->fetch(PDO::FETCH_ASSOC);

        return $conflict === false;
    }

    /**
     * Zählt, wie viele verschiedene Mitarbeiter für den angegebenen
     * Zeitraum bereits bestätigt bzw. angefragt sind. Wird genutzt,
     * um dem Kunden einen Hinweis zu geben (Frei / In Bearbeitung /
     * Ausgebucht), ohne den Slot direkt zu blockieren.
     *
     * @return array{bestaetigt: int, angefragt: int}
     */
    public function getAuslastung(
        string $terminStart,
        string $terminEnd
    ): array {
        $statement = $this->pdo->prepare(
            "SELECT status, COUNT(DISTINCT employee_id) AS anzahl
         FROM terminwunsche
         WHERE status IN ('angefragt', 'bestaetigt')
         AND terminwunsche_start < :termin_end
         AND terminwunsche_ende > :termin_start
         GROUP BY status"
        );

        $statement->execute([
            ':termin_start' => $terminStart,
            ':termin_end' => $terminEnd
        ]);

        $ergebnis = ['bestaetigt' => 0, 'angefragt' => 0];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $zeile) {
            $ergebnis[$zeile['status']] = (int) $zeile['anzahl'];
        }

        return $ergebnis;
    }

    /**
     * Findet alle offenen/bestätigten Terminwünsche, die sich mit dem
     * angegebenen Zeitraum überschneiden. Wird genutzt, um Admin vor
     * dem Stornieren eines Termin-Slots zu warnen, falls dazu schon
     * Kundenanfragen existieren.
     */
    public function findByZeitraum(
        string $terminStart,
        string $terminEnd
    ): array {
        $statement = $this->pdo->prepare(
            "SELECT
                tw.id,
                tw.status,
                tw.terminwunsche_start,
                tw.terminwunsche_ende,
                CONCAT(k.vor_name, ' ', k.nach_name) AS kunden_name,
                CONCAT(m.vor_name, ' ', m.nach_name) AS mitarbeiter_name
             FROM terminwunsche AS tw
             INNER JOIN user AS k ON tw.customer_id = k.id
             INNER JOIN employees AS m ON tw.employee_id = m.id
             WHERE tw.status IN ('angefragt', 'bestaetigt')
             AND tw.terminwunsche_start < :termin_end
             AND tw.terminwunsche_ende > :termin_start
             ORDER BY tw.terminwunsche_start ASC"
        );

        $statement->execute([
            ':termin_start' => $terminStart,
            ':termin_end' => $terminEnd
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }


    public function createTerminwunsch(

        int $employeeId,
        int $serviceId,
        string $terminStart,
        string $terminEnd,
        ?string $customerNote = null
    ): bool {
        $statement = $this->pdo->prepare(
            "INSERT INTO terminwunsche
                        (
                            customer_id,
                            employee_id,
                            service_id,
                            terminwunsche_start,
                            terminwunsche_ende,
                            status,
                            customer_note
                        )
                        VALUES
                        (
                            :customer_id,
                            :employee_id,
                            :service_id,
                            :terminwunsche_start,
                            :terminwunsche_ende,
                            'angefragt',
                            :customer_note
                        )"
        );

        return $statement->execute([
            ':customer_id' => $_SESSION['user_id'],
            ':employee_id' => $employeeId,
            ':service_id' => $serviceId,
            ':terminwunsche_start' => $terminStart,
            ':terminwunsche_ende' => $terminEnd,
            ':customer_note' => $customerNote !== ''
                ? $customerNote
                : null
        ]);
    }

    public function getFreierZeitslotById(int $zeitslotId): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT
            id,
            datum,
            uhrzeit,
            dauer_minuten
         FROM termin
         WHERE id = :id
         AND ist_gebucht = 0
         LIMIT 1"
        );

        $statement->execute([
            ':id' => $zeitslotId
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }




    public function changeStatus(int $id, string $status): bool
    {
        $erlaubteStatuswerte = [
            'angefragt',
            'bestaetigt',
            'abgelehnt',
            'abgeschlossen',
            'storniert'
        ];

        if (!in_array($status, $erlaubteStatuswerte, true)) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE terminwunsche
         SET status = :status
         WHERE id = :id'
        );

        return $statement->execute([
            'id' => $id,
            'status' => $status
        ]);
    }





    //termin suchen    


    public function sucheTerminwuensche(
        string $suche = '',
        string $status = '',
        string $datumVon = '',
        string $datumBis = '',
        string $sortRichtung = 'ASC'
    ): array {

        $suchwert = '%' . trim($suche) . '%';
        $sortRichtung = strtoupper($sortRichtung) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "
        SELECT
            tw.id,
            tw.customer_id,
            tw.employee_id,
            tw.service_id,
            tw.terminwunsche_start,
            tw.terminwunsche_ende,
            tw.status,
            tw.customer_note,
            tw.created_at,

            CONCAT(k.vor_name, ' ', k.nach_name) AS kunden_name,
            k.email AS kunden_email,
            k.telefon1,
            k.telefon2,

            CONCAT(m.vor_name, ' ', m.nach_name) AS mitarbeiter_name,
            m.email AS mitarbeiter_email,

            s.name AS dienstleistung_name

        FROM terminwunsche AS tw

        INNER JOIN user AS k
            ON tw.customer_id = k.id

        INNER JOIN employees AS m
            ON tw.employee_id = m.id

        INNER JOIN services AS s
            ON tw.service_id = s.id

        WHERE 1 = 1
    ";

        $parameter = [];

        if ($suche !== '') {

            $sql .= "
            AND (
                CAST(tw.id AS CHAR) LIKE :id

                OR k.vor_name LIKE :kunde_vorname
                OR k.nach_name LIKE :kunde_nachname
                OR CONCAT(k.vor_name, ' ', k.nach_name) LIKE :kunde_name
                OR k.email LIKE :kunde_email
                OR k.telefon1 LIKE :telefon1
                OR k.telefon2 LIKE :telefon2

                OR m.vor_name LIKE :mitarbeiter_vorname
                OR m.nach_name LIKE :mitarbeiter_nachname
                OR CONCAT(m.vor_name, ' ', m.nach_name) LIKE :mitarbeiter_name
                OR m.email LIKE :mitarbeiter_email

                OR s.name LIKE :dienstleistung
            )
        ";

            $parameter = [
                'id' => $suchwert,

                'kunde_vorname' => $suchwert,
                'kunde_nachname' => $suchwert,
                'kunde_name' => $suchwert,
                'kunde_email' => $suchwert,
                'telefon1' => $suchwert,
                'telefon2' => $suchwert,

                'mitarbeiter_vorname' => $suchwert,
                'mitarbeiter_nachname' => $suchwert,
                'mitarbeiter_name' => $suchwert,
                'mitarbeiter_email' => $suchwert,

                'dienstleistung' => $suchwert
            ];
        }

        if ($status !== '') {

            $sql .= " AND tw.status = :status";

            $parameter['status'] = $status;
        }

        if ($datumVon !== '') {

            $sql .= " AND DATE(tw.terminwunsche_start) >= :datum_von";

            $parameter['datum_von'] = $datumVon;
        }

        if ($datumBis !== '') {

            $sql .= " AND DATE(tw.terminwunsche_start) <= :datum_bis";

            $parameter['datum_bis'] = $datumBis;
        }

        $sql .= "
        ORDER BY
            tw.terminwunsche_start {$sortRichtung}
    ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameter);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }




    public function getTerminwunschById(int $id): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT
            tw.id,
            tw.customer_id,
            tw.employee_id,
            tw.service_id,
            tw.terminwunsche_start,
            tw.terminwunsche_ende,
            tw.status,
            tw.customer_note,
            tw.created_at,

            CONCAT(k.vor_name, ' ', k.nach_name) AS kunden_name,
            k.email AS kunden_email,

            CONCAT(m.vor_name, ' ', m.nach_name) AS mitarbeiter_name,

            d.name AS dienstleistung_name,
            d.price AS dienstleistung_preis

         FROM terminwunsche AS tw

         INNER JOIN user AS k
            ON tw.customer_id = k.id

         INNER JOIN employees AS m
            ON tw.employee_id = m.id

         INNER JOIN services AS d
            ON tw.service_id = d.id

         WHERE tw.id = :id
         LIMIT 1"
        );

        $statement->execute([
            'id' => $id
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Findet alle bestätigten Terminwünsche, deren Termin genau
     * $tageVorher Tage in der Zukunft liegt und für die die
     * entsprechende Erinnerung noch nicht gesendet wurde.
     *
     * @param int $tageVorher 1 oder 2
     */
    public function findForErinnerung(int $tageVorher): array
    {
        $spalte = $tageVorher === 1
            ? 'erinnerung_1_tag_gesendet'
            : 'erinnerung_2_tage_gesendet';

        $statement = $this->pdo->prepare(
            "SELECT
                tw.id,
                CONCAT(k.vor_name, ' ', k.nach_name) AS kunden_name,
                k.email AS kunden_email,
                CONCAT(m.vor_name, ' ', m.nach_name) AS mitarbeiter_name,
                d.name AS dienstleistung_name,
                tw.terminwunsche_start

             FROM terminwunsche AS tw
             INNER JOIN user AS k ON tw.customer_id = k.id
             INNER JOIN employees AS m ON tw.employee_id = m.id
             INNER JOIN services AS d ON tw.service_id = d.id

             WHERE tw.status = 'bestaetigt'
             AND {$spalte} = 0
             AND DATE(tw.terminwunsche_start) = DATE_ADD(CURDATE(), INTERVAL :tage DAY)"
        );

        $statement->execute([':tage' => $tageVorher]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Markiert eine Erinnerung als gesendet, damit sie nicht doppelt
     * verschickt wird.
     *
     * @param int $tageVorher 1 oder 2
     */
    public function markiereErinnerungGesendet(int $id, int $tageVorher): bool
    {
        $spalte = $tageVorher === 1
            ? 'erinnerung_1_tag_gesendet'
            : 'erinnerung_2_tage_gesendet';

        $statement = $this->pdo->prepare(
            "UPDATE terminwunsche SET {$spalte} = 1 WHERE id = :id"
        );

        return $statement->execute([':id' => $id]);
    }

    /**
     * Alle Termine eines Kunden, neueste zuerst nach Startzeit sortiert
     * (zukünftige zuerst).
     */
    public function getByCustomerId(int $customerId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                tw.id,
                tw.status,
                tw.terminwunsche_start,
                tw.terminwunsche_ende,
                tw.customer_note,
                CONCAT(m.vor_name, ' ', m.nach_name) AS mitarbeiter_name,
                d.name AS dienstleistung_name,
                d.duration_minutes AS dienstleistung_dauer,
                d.photo_url AS dienstleistung_foto

             FROM terminwunsche AS tw
             INNER JOIN employees AS m ON tw.employee_id = m.id
             INNER JOIN services AS d ON tw.service_id = d.id

             WHERE tw.customer_id = :customer_id
             ORDER BY tw.terminwunsche_start DESC"
        );

        $statement->execute([':customer_id' => $customerId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Der nächste bevorstehende Termin eines Kunden (bestätigt oder
     * angefragt), für die Dashboard-Anzeige "Dein nächster Termin".
     */
    public function getNaechsterTerminFuerKunde(int $customerId): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT
                tw.id,
                tw.status,
                tw.terminwunsche_start,
                tw.terminwunsche_ende,
                CONCAT(m.vor_name, ' ', m.nach_name) AS mitarbeiter_name,
                d.name AS dienstleistung_name,
                d.price AS dienstleistung_preis,
                d.photo_url AS dienstleistung_foto

             FROM terminwunsche AS tw
             INNER JOIN employees AS m ON tw.employee_id = m.id
             INNER JOIN services AS d ON tw.service_id = d.id

             WHERE tw.customer_id = :customer_id
             AND tw.status IN ('angefragt', 'bestaetigt')
             AND tw.terminwunsche_start >= NOW()

             ORDER BY tw.terminwunsche_start ASC
             LIMIT 1"
        );

        $statement->execute([':customer_id' => $customerId]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Termine eines Mitarbeiters, optional auf einen Zeitraum
     * eingeschränkt (für Tages-/Wochen-/Monatsansicht).
     */
    public function getByEmployeeId(
        int $employeeId,
        ?string $vonDatum = null,
        ?string $bisDatum = null
    ): array {
        $sql = "SELECT
                tw.id,
                tw.status,
                tw.terminwunsche_start,
                tw.terminwunsche_ende,
                tw.customer_note,
                tw.mitarbeiter_notiz,
                CONCAT(k.vor_name, ' ', k.nach_name) AS kunden_name,
                k.telefon1 AS kunden_telefon,
                d.name AS dienstleistung_name,
                d.duration_minutes AS dienstleistung_dauer,
                d.price AS dienstleistung_preis

             FROM terminwunsche AS tw
             INNER JOIN user AS k ON tw.customer_id = k.id
             INNER JOIN services AS d ON tw.service_id = d.id

             WHERE tw.employee_id = :employee_id";

        $parameter = [':employee_id' => $employeeId];

        if ($vonDatum !== null && $bisDatum !== null) {
            $sql .= " AND tw.terminwunsche_start >= :von
                      AND tw.terminwunsche_start < :bis";
            $parameter[':von'] = $vonDatum;
            $parameter[':bis'] = $bisDatum;
        }

        $sql .= " ORDER BY tw.terminwunsche_start ASC";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameter);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prüft, ob ein Terminwunsch zu einem bestimmten Kunden gehört.
     * Für Berechtigungsprüfungen vor dem Stornieren.
     */
    public function gehoertZuKunde(int $terminwunschId, int $customerId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM terminwunsche
             WHERE id = :id AND customer_id = :customer_id
             LIMIT 1"
        );

        $statement->execute([
            ':id' => $terminwunschId,
            ':customer_id' => $customerId,
        ]);

        return $statement->fetch() !== false;
    }

    /**
     * Prüft, ob ein Terminwunsch zu einem bestimmten Mitarbeiter gehört.
     * Für Berechtigungsprüfungen vor dem Stornieren/Notieren.
     */
    public function gehoertZuMitarbeiter(int $terminwunschId, int $employeeId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM terminwunsche
             WHERE id = :id AND employee_id = :employee_id
             LIMIT 1"
        );

        $statement->execute([
            ':id' => $terminwunschId,
            ':employee_id' => $employeeId,
        ]);

        return $statement->fetch() !== false;
    }

    public function updateMitarbeiterNotiz(int $id, string $notiz): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE terminwunsche
             SET mitarbeiter_notiz = :notiz
             WHERE id = :id"
        );

        return $statement->execute([
            ':notiz' => $notiz !== '' ? $notiz : null,
            ':id' => $id,
        ]);
    }

    /**
     * Anzahl noch offener (unbeantworteter) Terminwünsche - für
     * das Benachrichtigungs-Badge im Admin-Bereich.
     */
    public function countOffen(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM terminwunsche WHERE status = 'angefragt'"
        );

        return (int) $statement->fetchColumn();
    }
}
