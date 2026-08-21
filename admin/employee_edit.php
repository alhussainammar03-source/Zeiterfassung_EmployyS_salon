<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';
require_once __DIR__ . '/../validators/EmployeeValidator.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../services/CloudinaryUploader.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    exit('Ungültige Mitarbeiter-ID.');
}

$error = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $employeeRepository = new employeeRepository($pdo);

    $employee = $employeeRepository->getEmployeeById($id);

    if ($employee === false) {
        exit('Mitarbeiter wurde nicht gefunden.');
    }
} catch (PDOException $exception) {
    exit('Die Datenbank ist momentan nicht erreichbar.');
}

/*
|--------------------------------------------------------------------------
| Aktuelle Mitarbeiterdaten
|--------------------------------------------------------------------------
*/

$vorName = $employee['vor_name'] ?? '';
$nachName = $employee['nach_name'] ?? '';
$email = $employee['email'] ?? '';
$telefon = $employee['telefon'] ?? '';

$strasse = $employee['strasse'] ?? '';
$hausNum = $employee['haus_num'] ?? '';
$plz = $employee['plz'] ?? '';
$stadt = $employee['stadt'] ?? '';
$geschlecht = $employee['geschlecht'] ?? '';

$position = $employee['position'] ?? '';
$gehalt = $employee['gehalt'] ?? '';
$sollStundenWoche = $employee['soll_stunden_woche'] ?? '';
$urlaubstageJahr = $employee['urlaubstage_jahr'] ?? 30;
$eintrittsdatum = $employee['eintrittsdatum'] ?? '';

$role = $employee['role'] ?? 'mitarbeiter';
$status = $employee['status'] ?? 'aktiv';
$photoUrl = $employee['photo_url'] ?? null;

/*
|--------------------------------------------------------------------------
| Formular verarbeiten
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vorName = trim($_POST['vor_name'] ?? '');
    $nachName = trim($_POST['nach_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');

    $strasse = trim($_POST['strasse'] ?? '');
    $hausNum = trim($_POST['haus_num'] ?? '');
    $plz = trim($_POST['plz'] ?? '');
    $stadt = trim($_POST['stadt'] ?? '');
    $geschlecht = $_POST['geschlecht'] ?? '';

    $position = trim($_POST['position'] ?? '');
    $gehalt = trim($_POST['gehalt'] ?? '');
    $sollStundenWoche = trim($_POST['soll_stunden_woche'] ?? '');
    $urlaubstageJahr = trim($_POST['urlaubstage_jahr'] ?? '30');
    $eintrittsdatum = trim($_POST['eintrittsdatum'] ?? '');

    $role = $_POST['role'] ?? 'mitarbeiter';
    $status = $_POST['status'] ?? 'aktiv';

    $password = $_POST['password'] ?? '';
    $passwordWiederholen = $_POST['password_wiederholen'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validierung
    |--------------------------------------------------------------------------
    */

    $validator = new EmployeeValidator();

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
    } elseif (!$validator->validate($_POST, passwordRequired: false)) {
        $error = $validator->getFirstError();
    } elseif ($employeeRepository->emailExists($email, $id)) {
        $error = 'Diese E-Mail-Adresse wird bereits verwendet.';
    } else {
        try {
            $passwordHash = null;

            if ($password !== '') {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );
            }

            $neueFotoUrl = null;

            if (
                isset($_FILES['photo']) &&
                $_FILES['photo']['error'] === UPLOAD_ERR_OK
            ) {
                $uploader = new CloudinaryUploader();
                $neueFotoUrl = $uploader->uploadEmployeePhoto(
                    $_FILES['photo']['tmp_name'],
                    $id
                );
            }

            $updatedEmployee = Employee::fromRequest(
                $_POST,
                $id,
                $neueFotoUrl
            );

            $updated = $employeeRepository->updateEmployee(
                $updatedEmployee,
                $passwordHash
            );

            if ($updated) {
                header('Location: employees.php?updated=1');
                exit;
            }

            $error = 'Der Mitarbeiter konnte nicht aktualisiert werden.';
        } catch (PDOException $exception) {
            $error = 'Die Änderungen konnten nicht gespeichert werden.';

            // Nur während der Entwicklung:
            // $error = $exception->getMessage();
        } catch (RuntimeException $exception) {
            $error = 'Das Foto konnte nicht hochgeladen werden. '
                . 'Die übrigen Änderungen wurden nicht gespeichert. '
                . 'Bitte erneut versuchen.';

            // Nur während der Entwicklung:
            // $error = $exception->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitarbeiter bearbeiten - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/employee_form.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="ef-header">
            <div>
                <h1>Mitarbeiter bearbeiten</h1>
                <p>Persönliche Daten, Adresse, Arbeitsdaten und Zugangsdaten des Mitarbeiters bearbeiten.</p>
            </div>
            <div class="ef-header__actions">
                <a href="employees.php" class="ef-btn-cancel">Abbrechen</a>
                <button type="submit" form="employee-form" class="ef-btn-save">Speichern</button>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="message error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" id="employee-form" enctype="multipart/form-data">
            <?= Csrf::field() ?>

            <!-- Persönliche Daten -->
            <div class="ef-card">
                <h2>👤 Persönliche Daten</h2>

                <div class="ef-photo-row">
                    <label class="ef-photo-circle">
                        <?php if ($photoUrl !== null): ?>
                            <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Aktuelles Profilfoto">
                        <?php else: ?>
                            <span>📷</span>
                        <?php endif; ?>
                        <input id="photo" type="file" name="photo" accept="image/png, image/jpeg, image/webp">
                    </label>
                    <div class="ef-photo-hint">Foto ändern<br>Leer lassen, um das aktuelle Foto zu behalten.</div>
                </div>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="vor_name">Vorname *</label>
                        <input id="vor_name" type="text" name="vor_name" value="<?= htmlspecialchars($vorName, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="ef-field">
                        <label for="nach_name">Nachname *</label>
                        <input id="nach_name" type="text" name="nach_name" value="<?= htmlspecialchars($nachName, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>

                <div class="ef-row">
                    <div class="ef-field">
                        <label for="geschlecht">Geschlecht *</label>
                        <select id="geschlecht" name="geschlecht" required>
                            <option value="">Bitte auswählen</option>
                            <option value="männlich" <?= $geschlecht === 'männlich' ? 'selected' : '' ?>>Männlich</option>
                            <option value="weiblich" <?= $geschlecht === 'weiblich' ? 'selected' : '' ?>>Weiblich</option>
                            <option value="divers" <?= $geschlecht === 'divers' ? 'selected' : '' ?>>Divers</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Kontaktdaten -->
            <div class="ef-card">
                <h2>✉️ Kontaktdaten</h2>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="strasse">Straße *</label>
                        <input id="strasse" type="text" name="strasse" value="<?= htmlspecialchars($strasse, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="ef-field">
                        <label for="haus_num">Hausnummer *</label>
                        <input id="haus_num" type="text" name="haus_num" value="<?= htmlspecialchars($hausNum, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="plz">PLZ *</label>
                        <input id="plz" type="text" name="plz" value="<?= htmlspecialchars($plz, ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required>
                    </div>
                    <div class="ef-field">
                        <label for="stadt">Stadt *</label>
                        <input id="stadt" type="text" name="stadt" value="<?= htmlspecialchars($stadt, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="telefon">Telefon</label>
                        <input id="telefon" type="tel" name="telefon" value="<?= htmlspecialchars($telefon, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="ef-field">
                        <label for="email">E-Mail *</label>
                        <input id="email" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>
            </div>

            <!-- Arbeitsdaten -->
            <div class="ef-card">
                <h2>💼 Arbeitsdaten</h2>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="position">Position / Titel</label>
                        <input id="position" type="text" name="position" value="<?= htmlspecialchars($position, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="ef-field">
                        <label for="gehalt">Gehalt (Monat)</label>
                        <input id="gehalt" type="number" name="gehalt" value="<?= htmlspecialchars($gehalt, ENT_QUOTES, 'UTF-8') ?>" min="0" step="0.01">
                    </div>
                </div>

                <div class="ef-row ef-row--3">
                    <div class="ef-field">
                        <label for="soll_stunden_woche">Sollstunden / Woche</label>
                        <input id="soll_stunden_woche" type="number" name="soll_stunden_woche" value="<?= htmlspecialchars((string) $sollStundenWoche, ENT_QUOTES, 'UTF-8') ?>" min="0" max="80" step="0.5">
                    </div>
                    <div class="ef-field">
                        <label for="urlaubstage_jahr">Urlaubstage / Jahr</label>
                        <input id="urlaubstage_jahr" type="number" name="urlaubstage_jahr" value="<?= htmlspecialchars((string) $urlaubstageJahr, ENT_QUOTES, 'UTF-8') ?>" min="0" max="60">
                    </div>
                    <div class="ef-field">
                        <label for="eintrittsdatum">Eintrittsdatum</label>
                        <input id="eintrittsdatum" type="date" name="eintrittsdatum" value="<?= htmlspecialchars($eintrittsdatum, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="role">System-Rolle *</label>
                        <select id="role" name="role" required>
                            <option value="mitarbeiter" <?= $role === 'mitarbeiter' ? 'selected' : '' ?>>Mitarbeiter (Eingeschränkter Zugriff)</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </div>
                    <div class="ef-field">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="aktiv" <?= $status === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                            <option value="inaktiv" <?= $status === 'inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Zugangsdaten -->
            <div class="ef-card">
                <h2>🔐 Zugangsdaten</h2>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="password">Neues Passwort</label>
                        <input id="password" type="password" name="password" minlength="8" autocomplete="new-password">
                        <small>Leer lassen, wenn das Passwort nicht geändert werden soll.</small>
                    </div>
                    <div class="ef-field">
                        <label for="password_wiederholen">Neues Passwort wiederholen</label>
                        <input id="password_wiederholen" type="password" name="password_wiederholen" minlength="8" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="ef-bottom-actions">
                <button type="submit" class="ef-btn-save">📥 Änderungen speichern</button>
            </div>

        </form>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>