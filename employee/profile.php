<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/employeeRepository.php';
require_once __DIR__ . '/../services/CloudinaryUploader.php';
require_once __DIR__ . '/../services/PasswordResetService.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('mitarbeiter');

$mitarbeiterId = (int) $_SESSION['user_id'];
$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $employeeRepository = new employeeRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'profil_speichern') {
            $vorName = trim($_POST['vor_name'] ?? '');
            $nachName = trim($_POST['nach_name'] ?? '');
            $geschlecht = $_POST['geschlecht'] ?? '';
            $telefon = trim($_POST['telefon'] ?? '');
            $strasse = trim($_POST['strasse'] ?? '');
            $hausNum = trim($_POST['haus_num'] ?? '');
            $plz = trim($_POST['plz'] ?? '');
            $stadt = trim($_POST['stadt'] ?? '');

            if ($vorName === '' || $nachName === '' || $strasse === '' || $hausNum === '' || $plz === '' || $stadt === '') {
                $fehler = 'Bitte alle Pflichtfelder ausfüllen.';
            } elseif (!in_array($geschlecht, ['männlich', 'weiblich', 'divers'], true)) {
                $fehler = 'Bitte ein gültiges Geschlecht auswählen.';
            } elseif (!ctype_digit($hausNum) || !ctype_digit($plz) || strlen($plz) !== 5) {
                $fehler = 'Bitte eine gültige Hausnummer und 5-stellige Postleitzahl angeben.';
            } else {
                $employeeRepository->updateEigeneKontaktdaten(
                    $mitarbeiterId,
                    $vorName,
                    $nachName,
                    $geschlecht,
                    $telefon !== '' ? $telefon : null,
                    $strasse,
                    (int) $hausNum,
                    (int) $plz,
                    $stadt
                );

                if (
                    isset($_FILES['photo']) &&
                    $_FILES['photo']['error'] === UPLOAD_ERR_OK
                ) {
                    try {
                        $uploader = new CloudinaryUploader();
                        $url = $uploader->uploadEmployeePhoto($_FILES['photo']['tmp_name'], $mitarbeiterId);
                        $employeeRepository->updatePhotoUrl($mitarbeiterId, $url);
                    } catch (RuntimeException $uploadException) {
                        error_log($uploadException->getMessage());
                    }
                }

                // Session-Anzeigenamen aktualisieren, damit Header/Dashboard
                // sofort den neuen Namen zeigen, ohne neu einzuloggen.
                $_SESSION['vor_name'] = $vorName;
                $_SESSION['nach_name'] = $nachName;

                header('Location: profile.php?gespeichert=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'passwort_aendern') {
            $aktuelles = $_POST['aktuelles_passwort'] ?? '';
            $neues = $_POST['neues_passwort'] ?? '';
            $wiederholen = $_POST['neues_passwort_wiederholen'] ?? '';

            $mitarbeiterDaten = $employeeRepository->getEmployeeById($mitarbeiterId);

            if ($mitarbeiterDaten === false || !password_verify($aktuelles, $mitarbeiterDaten['password'])) {
                $fehler = 'Das aktuelle Passwort ist nicht korrekt.';
            } elseif (strlen($neues) < 8) {
                $fehler = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
            } elseif ($neues !== $wiederholen) {
                $fehler = 'Die neuen Passwörter stimmen nicht überein.';
            } else {
                $employeeRepository->updatePassword($mitarbeiterId, password_hash($neues, PASSWORD_DEFAULT));
                header('Location: profile.php?passwort_geaendert=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'reset_link_senden') {
            $mitarbeiterDaten = $employeeRepository->getEmployeeById($mitarbeiterId);

            if ($mitarbeiterDaten !== false) {
                try {
                    $resetService = new PasswordResetService($pdo);
                    $resetService->sendResetLink(
                        'employees',
                        $mitarbeiterId,
                        $mitarbeiterDaten['email'],
                        $mitarbeiterDaten['vor_name']
                    );
                } catch (Throwable $emailException) {
                    error_log('Reset-Link fehlgeschlagen: ' . $emailException->getMessage());
                }
            }

            header('Location: profile.php?reset_link_gesendet=1');
            exit;
        }
    }

    $mitarbeiter = $employeeRepository->getEmployeeById($mitarbeiterId);

    if (isset($_GET['gespeichert'])) {
        $meldung = 'Dein Profil wurde gespeichert.';
    }
    if (isset($_GET['passwort_geaendert'])) {
        $meldung = 'Dein Passwort wurde geändert.';
    }
    if (isset($_GET['reset_link_gesendet'])) {
        $meldung = 'Ein Link zum Zurücksetzen deines Passworts wurde an deine E-Mail-Adresse gesendet.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $mitarbeiter = false;
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mein Profil</title>

    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/form.css">
    <link rel="stylesheet" href="../style/button.css">
    <link rel="stylesheet" href="../style/nav.css">
    <link rel="stylesheet" href="../style/header.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/profile.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <?php if ($mitarbeiter !== false): ?>

            <div class="profile-header">
                <?php if (!empty($mitarbeiter['photo_url'])): ?>
                    <img src="<?= htmlspecialchars($mitarbeiter['photo_url']) ?>" alt="Profilfoto" class="profile-photo">
                <?php else: ?>
                    <div class="profile-photo--placeholder">
                        <?= htmlspecialchars(mb_substr($mitarbeiter['vor_name'], 0, 1) . mb_substr($mitarbeiter['nach_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <h1>
                        <?= htmlspecialchars($mitarbeiter['vor_name'] . ' ' . $mitarbeiter['nach_name']) ?>
                        <span class="profile-badge"><?= $mitarbeiter['status'] === 'aktiv' ? 'Aktiv' : 'Inaktiv' ?></span>
                    </h1>
                    <p><?= htmlspecialchars($mitarbeiter['position'] ?? ucfirst($mitarbeiter['role'])) ?></p>
                    <p>
                        📧 <?= htmlspecialchars($mitarbeiter['email']) ?>
                        &nbsp;·&nbsp;
                        📞 <?= htmlspecialchars($mitarbeiter['telefon'] ?? '–') ?>
                    </p>
                    <?php if (!empty($mitarbeiter['eintrittsdatum'])): ?>
                        <p>
                            Seit <?= date('d.m.Y', strtotime($mitarbeiter['eintrittsdatum'])) ?>
                            (<?php
                                $diff = (new DateTime($mitarbeiter['eintrittsdatum']))->diff(new DateTime());
                                echo $diff->y . ' Jahre, ' . $diff->m . ' Monate';
                                ?>)
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-grid">

                <div class="profile-section">
                    <h2>💼 Berufsdetails</h2>
                    <div class="profile-info-row"><span>Position &amp; Rolle</span><span><?= htmlspecialchars(($mitarbeiter['position'] ?? '–') . ' · ' . ucfirst($mitarbeiter['role'])) ?></span></div>
                    <div class="profile-info-row"><span>Jährliches Gehalt</span><span><?= $mitarbeiter['gehalt'] !== null ? number_format((float) $mitarbeiter['gehalt'], 2, ',', '.') . ' €' : '–' ?></span></div>
                    <div class="profile-info-row"><span>Arbeitsstunden / Woche</span><span><?= $mitarbeiter['soll_stunden_woche'] !== null ? $mitarbeiter['soll_stunden_woche'] . ' Std.' : '–' ?></span></div>
                    <div class="profile-info-row"><span>Urlaubstage / Jahr</span><span><?= (int) $mitarbeiter['urlaubstage_jahr'] ?> Tage</span></div>
                    <p><small>Diese Angaben kann nur der Admin ändern.</small></p>
                </div>

                <div class="profile-section">
                    <h2>👤 Persönliche Daten &amp; Kontakt</h2>

                    <form method="post" class="admin-form" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="profil_speichern">

                        <div class="form-group">
                            <label>Profilfoto</label>
                            <input type="file" name="photo" accept="image/png, image/jpeg, image/webp">
                        </div>

                        <div class="form-group">
                            <label>Vorname</label>
                            <input type="text" name="vor_name" value="<?= htmlspecialchars($mitarbeiter['vor_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Nachname</label>
                            <input type="text" name="nach_name" value="<?= htmlspecialchars($mitarbeiter['nach_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Geschlecht</label>
                            <select name="geschlecht" required>
                                <option value="männlich" <?= $mitarbeiter['geschlecht'] === 'männlich' ? 'selected' : '' ?>>Männlich</option>
                                <option value="weiblich" <?= $mitarbeiter['geschlecht'] === 'weiblich' ? 'selected' : '' ?>>Weiblich</option>
                                <option value="divers" <?= $mitarbeiter['geschlecht'] === 'divers' ? 'selected' : '' ?>>Divers</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Telefon</label>
                            <input type="text" name="telefon" value="<?= htmlspecialchars($mitarbeiter['telefon'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Straße</label>
                            <input type="text" name="strasse" value="<?= htmlspecialchars($mitarbeiter['strasse']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Hausnummer</label>
                            <input type="text" name="haus_num" value="<?= htmlspecialchars((string) $mitarbeiter['haus_num']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>PLZ</label>
                            <input type="text" name="plz" value="<?= htmlspecialchars((string) $mitarbeiter['plz']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Stadt</label>
                            <input type="text" name="stadt" value="<?= htmlspecialchars($mitarbeiter['stadt']) ?>" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit">Speichern</button>
                        </div>
                    </form>
                </div>

                <div class="profile-section">
                    <h2>🔐 Account &amp; Sicherheit</h2>

                    <form method="post" class="admin-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="passwort_aendern">

                        <div class="form-group">
                            <label>Aktuelles Passwort</label>
                            <input type="password" name="aktuelles_passwort" required>
                        </div>

                        <div class="form-group">
                            <label>Neues Passwort</label>
                            <input type="password" name="neues_passwort" required>
                        </div>

                        <div class="form-group">
                            <label>Neues Passwort wiederholen</label>
                            <input type="password" name="neues_passwort_wiederholen" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit">Passwort ändern</button>
                        </div>
                    </form>

                    <hr style="margin: 20px 0;">

                    <p><small>Passwort vergessen oder lieber per E-Mail zurücksetzen?</small></p>

                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="reset_link_senden">
                        <button type="submit" class="btn">📧 Reset-Link per E-Mail senden</button>
                    </form>
                </div>

            </div>

        <?php endif; ?>

    </main>
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>