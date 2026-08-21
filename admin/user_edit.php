<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/CustomerRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    exit('Ungültige Kunden-ID.');
}

$error = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $customerRepository = new CustomerRepository($pdo);

    $customer = $customerRepository->getCustomerById($id);

    if ($customer === false) {
        exit('Kunde wurde nicht gefunden.');
    }
} catch (PDOException $exception) {
    exit('Die Datenbank ist momentan nicht erreichbar.');
}

$vorName = $customer['vor_name'] ?? '';
$nachName = $customer['nach_name'] ?? '';
$geschlecht = $customer['geschlecht'] ?? '';
$email = $customer['email'] ?? '';
$telefon1 = $customer['telefon1'] ?? '';
$telefon2 = $customer['telefon2'] ?? '';
$strasse = $customer['strasse'] ?? '';
$hausNum = $customer['haus_num'] ?? '';
$plz = $customer['plz'] ?? '';
$stadt = $customer['stadt'] ?? '';
$status = $customer['status'] ?? 'aktiv';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vorName = trim($_POST['vor_name'] ?? '');
    $nachName = trim($_POST['nach_name'] ?? '');
    $geschlecht = $_POST['geschlecht'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $telefon1 = trim($_POST['telefon1'] ?? '');
    $telefon2 = trim($_POST['telefon2'] ?? '');
    $strasse = trim($_POST['strasse'] ?? '');
    $hausNum = trim($_POST['haus_num'] ?? '');
    $plz = trim($_POST['plz'] ?? '');
    $stadt = trim($_POST['stadt'] ?? '');

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
    } elseif ($vorName === '' || $nachName === '' || $email === '' || $strasse === '' || $hausNum === '' || $plz === '' || $stadt === '') {
        $error = 'Bitte alle Pflichtfelder ausfüllen.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte eine gültige E-Mail-Adresse angeben.';
    } elseif (!in_array($geschlecht, ['männlich', 'weiblich', 'divers'], true)) {
        $error = 'Bitte ein gültiges Geschlecht auswählen.';
    } elseif (!ctype_digit($hausNum) || !ctype_digit($plz) || strlen($plz) !== 5) {
        $error = 'Bitte eine gültige Hausnummer und 5-stellige Postleitzahl angeben.';
    } elseif ($customerRepository->emailExists($email, $id)) {
        $error = 'Diese E-Mail-Adresse wird bereits von einem anderen Kunden verwendet.';
    } else {
        try {
            $customerRepository->updateAlsAdmin(
                $id,
                $vorName,
                $nachName,
                $geschlecht,
                $email,
                $telefon1 !== '' ? $telefon1 : null,
                $telefon2 !== '' ? $telefon2 : null,
                $strasse,
                (int) $hausNum,
                (int) $plz,
                $stadt
            );

            header('Location: users.php?updated=1');
            exit;
        } catch (PDOException $exception) {
            $error = 'Die Änderungen konnten nicht gespeichert werden.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kunde bearbeiten - Admin Bereich</title>

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
                <h1>Kunde bearbeiten</h1>
                <p>Persönliche Daten und Kontaktdaten des Kunden bearbeiten.</p>
            </div>
            <div class="ef-header__actions">
                <a href="users.php" class="ef-btn-cancel">Abbrechen</a>
                <button type="submit" form="customer-form" class="ef-btn-save">Speichern</button>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" id="customer-form">
            <?= Csrf::field() ?>

            <div class="ef-card">
                <h2>👤 Persönliche Daten</h2>

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

            <div class="ef-card">
                <h2>✉️ Kontaktdaten</h2>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="strasse">Straße *</label>
                        <input id="strasse" type="text" name="strasse" value="<?= htmlspecialchars($strasse, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="ef-field">
                        <label for="haus_num">Hausnummer *</label>
                        <input id="haus_num" type="text" name="haus_num" value="<?= htmlspecialchars((string) $hausNum, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="plz">PLZ *</label>
                        <input id="plz" type="text" name="plz" value="<?= htmlspecialchars((string) $plz, ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required>
                    </div>
                    <div class="ef-field">
                        <label for="stadt">Stadt *</label>
                        <input id="stadt" type="text" name="stadt" value="<?= htmlspecialchars($stadt, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>

                <div class="ef-row ef-row--2">
                    <div class="ef-field">
                        <label for="telefon1">Telefon 1</label>
                        <input id="telefon1" type="tel" name="telefon1" value="<?= htmlspecialchars($telefon1, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="ef-field">
                        <label for="telefon2">Telefon 2 (optional)</label>
                        <input id="telefon2" type="tel" name="telefon2" value="<?= htmlspecialchars($telefon2, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="ef-row">
                    <div class="ef-field">
                        <label for="email">E-Mail *</label>
                        <input id="email" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                        <small>Achtung: Der Kunde meldet sich mit dieser E-Mail an.</small>
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