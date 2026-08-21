<?php

declare(strict_types=1);

/**
 * Repräsentiert einen Mitarbeiter-Datensatz.
 *
 * Ersetzt die langen, positionellen Parameterlisten in
 * EmployeeRepository::createEmployee() / updateEmployee().
 */
class Employee
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $vorName,
        public readonly string $nachName,
        public readonly string $email,
        public readonly ?string $telefon,
        public readonly string $strasse,
        public readonly int $hausNum,
        public readonly int $plz,
        public readonly string $stadt,
        public readonly string $geschlecht,
        public readonly ?string $position,
        public readonly ?float $gehalt,
        public readonly ?string $eintrittsdatum,
        public readonly string $rolle,
        public readonly string $status,
        public readonly ?string $photoUrl = null,
        public readonly ?float $sollStundenWoche = null,
        public readonly int $urlaubstageJahr = 30,
    ) {}

    /**
     * Baut ein Employee-Objekt aus $_POST-Daten.
     *
     * Berücksichtigt, dass employee_create.php das Feld "rolle"
     * und employee_edit.php das Feld "role" nutzt.
     *
     * @param array $data Rohdaten aus $_POST
     * @param int|null $id null bei Neuanlage, vorhandene ID bei Bearbeitung
     * @param string|null $photoUrl Ergebnis-URL des Cloudinary-Uploads,
     *                              null wenn kein neues Foto hochgeladen wurde
     */
    public static function fromRequest(
        array $data,
        ?int $id = null,
        ?string $photoUrl = null
    ): self {
        $gehalt = trim($data['gehalt'] ?? '');
        $telefon = trim($data['telefon'] ?? '');
        $position = trim($data['position'] ?? '');
        $eintrittsdatum = trim($data['eintrittsdatum'] ?? '');
        $sollStunden = trim($data['soll_stunden_woche'] ?? '');
        $urlaubstage = trim($data['urlaubstage_jahr'] ?? '');

        return new self(
            id: $id,
            vorName: trim($data['vor_name'] ?? ''),
            nachName: trim($data['nach_name'] ?? ''),
            email: trim($data['email'] ?? ''),
            telefon: $telefon !== '' ? $telefon : null,
            strasse: trim($data['strasse'] ?? ''),
            hausNum: (int) trim($data['haus_num'] ?? '0'),
            plz: (int) trim($data['plz'] ?? '0'),
            stadt: trim($data['stadt'] ?? ''),
            geschlecht: $data['geschlecht'] ?? '',
            position: $position !== '' ? $position : null,
            gehalt: $gehalt !== '' ? (float) $gehalt : null,
            eintrittsdatum: $eintrittsdatum !== '' ? $eintrittsdatum : null,
            rolle: $data['rolle'] ?? ($data['role'] ?? 'mitarbeiter'),
            status: $data['status'] ?? 'aktiv',
            photoUrl: $photoUrl,
            sollStundenWoche: $sollStunden !== '' ? (float) $sollStunden : null,
            urlaubstageJahr: $urlaubstage !== '' ? (int) $urlaubstage : 30,
        );
    }
}
