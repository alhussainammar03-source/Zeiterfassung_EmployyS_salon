<?php

declare(strict_types=1);

/**
 * Validiert Mitarbeiter-Formulardaten aus $_POST.
 *
 * Wird sowohl beim Anlegen (employee_create.php, Passwort Pflicht)
 * als auch beim Bearbeiten (employee_edit.php, Passwort optional)
 * verwendet, damit die Regeln nur an einer Stelle gepflegt werden.
 */
class EmployeeValidator
{
    private array $errors = [];

    /**
     * @param array $data            Rohdaten aus $_POST
     * @param bool  $passwordRequired true = Passwort ist Pflicht (Neuanlage),
     *                                 false = Passwort optional (Bearbeitung)
     */
    public function validate(array $data, bool $passwordRequired): bool
    {
        $this->errors = [];

        $vorName = trim($data['vor_name'] ?? '');
        $nachName = trim($data['nach_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $strasse = trim($data['strasse'] ?? '');
        $hausNum = trim($data['haus_num'] ?? '');
        $plz = trim($data['plz'] ?? '');
        $stadt = trim($data['stadt'] ?? '');
        $geschlecht = $data['geschlecht'] ?? '';

        $password = $data['password'] ?? '';
        $passwordWiederholen = $data['password_wiederholen'] ?? '';

        $gehalt = trim($data['gehalt'] ?? '');
        $eintrittsdatum = trim($data['eintrittsdatum'] ?? '');

        $rolle = $data['rolle'] ?? ($data['role'] ?? '');
        $status = $data['status'] ?? '';

        $pflichtfelderLeer =
            $vorName === '' ||
            $nachName === '' ||
            $email === '' ||
            $strasse === '' ||
            $hausNum === '' ||
            $plz === '' ||
            $stadt === '' ||
            $geschlecht === '' ||
            ($passwordRequired && ($password === '' || $passwordWiederholen === ''));

        if ($pflichtfelderLeer) {
            $this->errors[] = 'Bitte alle Pflichtfelder ausfüllen.';
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Bitte eine gültige E-Mail-Adresse eingeben.';
        }

        if (!ctype_digit($hausNum) || (int) $hausNum <= 0) {
            $this->errors[] = 'Bitte eine gültige Hausnummer eingeben.';
        }

        if (!ctype_digit($plz) || strlen($plz) !== 5) {
            $this->errors[] = 'Die Postleitzahl muss aus genau 5 Ziffern bestehen.';
        }

        if (!in_array($geschlecht, ['männlich', 'weiblich', 'divers'], true)) {
            $this->errors[] = 'Bitte ein gültiges Geschlecht auswählen.';
        }

        if ($passwordRequired || $password !== '') {
            if (strlen($password) < 8) {
                $this->errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
            } elseif ($password !== $passwordWiederholen) {
                $this->errors[] = 'Die eingegebenen Passwörter stimmen nicht überein.';
            }
        }

        if ($gehalt !== '' && (!is_numeric($gehalt) || (float) $gehalt < 0)) {
            $this->errors[] = 'Bitte ein gültiges Gehalt eingeben.';
        }

        if ($eintrittsdatum !== '' && !self::isValidDate($eintrittsdatum)) {
            $this->errors[] = 'Bitte ein gültiges Eintrittsdatum eingeben.';
        }

        if (!in_array($rolle, ['admin', 'mitarbeiter'], true)) {
            $this->errors[] = 'Die ausgewählte Rolle ist ungültig.';
        }

        if (!in_array($status, ['aktiv', 'inaktiv'], true)) {
            $this->errors[] = 'Der ausgewählte Status ist ungültig.';
        }

        return $this->errors === [];
    }

    public static function isValidDate(string $date): bool
    {
        $dateObject = DateTime::createFromFormat('Y-m-d', $date);

        return $dateObject !== false
            && $dateObject->format('Y-m-d') === $date;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }
}
