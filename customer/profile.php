<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/CustomerRepository.php';
require_once __DIR__ . '/../repositories/LoyaltyRepository.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../services/CloudinaryUploader.php';
require_once __DIR__ . '/../services/PasswordResetService.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('kunde');

$kundeId = (int) $_SESSION['user_id'];
$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $customerRepository = new CustomerRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'profil_speichern') {
            $vorName = trim($_POST['vor_name'] ?? '');
            $nachName = trim($_POST['nach_name'] ?? '');
            $geschlecht = $_POST['geschlecht'] ?? '';
            $telefon1 = trim($_POST['telefon1'] ?? '');
            $telefon2 = trim($_POST['telefon2'] ?? '');
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
                $customerRepository->updateEigeneKontaktdaten(
                    $kundeId,
                    $vorName,
                    $nachName,
                    $geschlecht,
                    $telefon1 !== '' ? $telefon1 : null,
                    $telefon2 !== '' ? $telefon2 : null,
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
                        $url = $uploader->uploadCustomerPhoto($_FILES['photo']['tmp_name'], $kundeId);
                        $customerRepository->updatePhotoUrl($kundeId, $url);
                    } catch (RuntimeException $uploadException) {
                        error_log($uploadException->getMessage());
                    }
                }

                $_SESSION['vor_name'] = $vorName;
                $_SESSION['nach_name'] = $nachName;

                header('Location: profile.php?gespeichert=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'passwort_aendern') {
            $aktuelles = $_POST['aktuelles_passwort'] ?? '';
            $neues = $_POST['neues_passwort'] ?? '';
            $wiederholen = $_POST['neues_passwort_wiederholen'] ?? '';

            $kundeDaten = $customerRepository->getCustomerById($kundeId);

            if ($kundeDaten === false || !password_verify($aktuelles, $kundeDaten['password'])) {
                $fehler = 'Das aktuelle Passwort ist nicht korrekt.';
            } elseif (strlen($neues) < 8) {
                $fehler = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
            } elseif ($neues !== $wiederholen) {
                $fehler = 'Die neuen Passwörter stimmen nicht überein.';
            } else {
                $customerRepository->updatePassword($kundeId, password_hash($neues, PASSWORD_DEFAULT));
                header('Location: profile.php?passwort_geaendert=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'reset_link_senden') {
            $kundeDaten = $customerRepository->getCustomerById($kundeId);

            if ($kundeDaten !== false) {
                try {
                    $resetService = new PasswordResetService($pdo);
                    $resetService->sendResetLink(
                        'user',
                        $kundeId,
                        $kundeDaten['email'],
                        $kundeDaten['vor_name']
                    );
                } catch (Throwable $emailException) {
                    error_log('Reset-Link fehlgeschlagen: ' . $emailException->getMessage());
                }
            }

            header('Location: profile.php?reset_link_gesendet=1');
            exit;
        }
    }

    $kunde = $customerRepository->getCustomerById($kundeId);
    $anzahlTermine = $customerRepository->countTerminwuenscheVonKunde($kundeId);

    $loyaltyRepository = new LoyaltyRepository($pdo);
    $punkte = $loyaltyRepository->getPunkte($kundeId);

    $terminwunschRepository = new TerminwunschRepository($pdo);
    $naechsterTermin = $terminwunschRepository->getNaechsterTerminFuerKunde($kundeId);

    // Letzter abgeschlossener Besuch
    $letzterBesuchText = 'Noch kein Besuch';
    $alleTermineKunde = $terminwunschRepository->getByCustomerId($kundeId);
    foreach ($alleTermineKunde as $t) {
        if ($t['status'] === 'abgeschlossen') {
            $tage = (new DateTime($t['terminwunsche_start']))->diff(new DateTime())->days;
            $letzterBesuchText = 'Vor ' . $tage . ' Tagen';
            break; // Liste ist bereits absteigend sortiert -> erster Treffer ist der letzte Besuch
        }
    }

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
    $kunde = false;
    $anzahlTermine = 0;
    $punkte = 0;
    $naechsterTermin = false;
    $letzterBesuchText = '–';
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mein Profil - Bella Beauty</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/form.css">
    <link rel="stylesheet" href="../style/button.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/profile.css">
    <link rel="stylesheet" href="../style/customer_profile.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <?php if ($kunde !== false): ?>

            <div class="profile-header">
                <?php if (!empty($kunde['photo_url'])): ?>
                    <img src="<?= htmlspecialchars($kunde['photo_url']) ?>" alt="Profilfoto" class="profile-photo">
                <?php else: ?>
                    <div class="profile-photo--placeholder">
                        <?= htmlspecialchars(mb_substr($kunde['vor_name'], 0, 1) . mb_substr($kunde['nach_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <h1>
                        <?= htmlspecialchars($kunde['vor_name'] . ' ' . $kunde['nach_name']) ?>
                        <span class="profile-badge"><?= $kunde['status'] === 'aktiv' ? 'Aktiv' : 'Inaktiv' ?></span>
                    </h1>
                    <p>Kundin/Kunde seit <?= date('d.m.Y', strtotime($kunde['created_at'])) ?></p>
                    <p>
                        📧 <?= htmlspecialchars($kunde['email']) ?>
                        &nbsp;·&nbsp;
                        📞 <?= htmlspecialchars($kunde['telefon1'] ?? '–') ?>
                    </p>
                </div>
            </div>

            <div class="cprofile-grid">

                <div class="profile-section">
                    <h2>👤 Persönliche Daten &amp; Kontakt</h2>

                    <form method="post" class="admin-form" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="profil_speichern">

                        <div class="cprofile-photo-upload">
                            <div class="cprofile-photo-upload__icon">
                                <?php if (!empty($kunde['photo_url'])): ?>
                                    <img src="<?= htmlspecialchars($kunde['photo_url']) ?>" alt="">
                                <?php else: ?>
                                    📷
                                <?php endif; ?>
                            </div>
                            <div>
                                <label>Profilbild ändern</label>
                                <input type="file" name="photo" accept="image/png, image/jpeg, image/webp">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Vorname</label>
                            <input type="text" name="vor_name" value="<?= htmlspecialchars($kunde['vor_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Nachname</label>
                            <input type="text" name="nach_name" value="<?= htmlspecialchars($kunde['nach_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Geschlecht</label>
                            <select name="geschlecht" required>
                                <option value="männlich" <?= ($kunde['geschlecht'] ?? '') === 'männlich' ? 'selected' : '' ?>>Männlich</option>
                                <option value="weiblich" <?= ($kunde['geschlecht'] ?? '') === 'weiblich' ? 'selected' : '' ?>>Weiblich</option>
                                <option value="divers" <?= ($kunde['geschlecht'] ?? '') === 'divers' ? 'selected' : '' ?>>Divers</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Telefon 1</label>
                            <input type="text" name="telefon1" value="<?= htmlspecialchars($kunde['telefon1'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Telefon 2 (optional)</label>
                            <input type="text" name="telefon2" value="<?= htmlspecialchars($kunde['telefon2'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Straße</label>
                            <input type="text" name="strasse" value="<?= htmlspecialchars($kunde['strasse']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Hausnummer</label>
                            <input type="text" name="haus_num" value="<?= htmlspecialchars((string) $kunde['haus_num']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>PLZ</label>
                            <input type="text" name="plz" value="<?= htmlspecialchars((string) $kunde['plz']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Stadt</label>
                            <input type="text" name="stadt" value="<?= htmlspecialchars($kunde['stadt']) ?>" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit">Änderungen speichern</button>
                        </div>
                    </form>
                </div>

                <div class="cprofile-grid__side">

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
                                <button type="submit">Passwort aktualisieren</button>
                            </div>
                        </form>

                        <div class="cprofile-divider"><span>Oder</span></div>

                        <form method="post">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="reset_link_senden">
                            <button type="submit" class="btn-outline" style="width:100%; cursor:pointer;">
                                Reset-Link per E-Mail senden
                            </button>
                        </form>
                    </div>

                    <?php if ($naechsterTermin !== false): ?>
                        <div class="cprofile-next-card">
                            <h4>Nächster Termin</h4>
                            <p class="cprofile-next-card__desc">
                                <?= htmlspecialchars($naechsterTermin['dienstleistung_name']) ?> mit <?= htmlspecialchars($naechsterTermin['mitarbeiter_name']) ?>
                            </p>
                            <div class="cprofile-next-card__row">
                                <div class="cprofile-next-card__day">
                                    <strong><?= date('d', strtotime($naechsterTermin['terminwunsche_start'])) ?></strong>
                                    <span><?= date('M', strtotime($naechsterTermin['terminwunsche_start'])) ?></span>
                                </div>
                                <div class="cprofile-next-card__divider"></div>
                                <div class="cprofile-next-card__time">
                                    <strong><?= date('H:i', strtotime($naechsterTermin['terminwunsche_start'])) ?> Uhr</strong>
                                    <span><?= date('H:i', strtotime($naechsterTermin['terminwunsche_ende'])) ?> Ende</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

            </div>

            <div class="cprofile-stats">
                <div class="cprofile-stat-card">
                    <div class="cprofile-stat-card__icon cprofile-stat-card__icon--loyalty">🎁</div>
                    <p>
                        <span class="cprofile-stat-card__label" style="display:block;">Treuepunkte</span>
                        <span class="cprofile-stat-card__value"><?= $punkte ?> pts</span>
                    </p>
                </div>

                <div class="cprofile-stat-card">
                    <div class="cprofile-stat-card__icon cprofile-stat-card__icon--visit">🕐</div>
                    <p>
                        <span class="cprofile-stat-card__label" style="display:block;">Letzter Besuch</span>
                        <span class="cprofile-stat-card__value"><?= htmlspecialchars($letzterBesuchText) ?></span>
                    </p>
                </div>

                <div class="cprofile-stat-card">
                    <div class="cprofile-stat-card__icon cprofile-stat-card__icon--count">📅</div>
                    <p>
                        <span class="cprofile-stat-card__label" style="display:block;">Termine insgesamt</span>
                        <span class="cprofile-stat-card__value"><?= $anzahlTermine ?></span>
                    </p>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>