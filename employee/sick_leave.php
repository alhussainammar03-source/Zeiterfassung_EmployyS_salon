<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/SickLeaveRepository.php';
require_once __DIR__ . '/../services/CloudinaryUploader.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('mitarbeiter');

$mitarbeiterId = (int) $_SESSION['user_id'];
$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $sickLeaveRepository = new SickLeaveRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } else {
            $start = trim($_POST['start_datum'] ?? '');
            $end = trim($_POST['end_datum'] ?? '');
            $end = $end !== '' ? $end : null;

            if ($start === '') {
                $fehler = 'Bitte ein Start-Datum angeben.';
            } elseif ($end !== null && $start > $end) {
                $fehler = 'Das Start-Datum muss vor dem End-Datum liegen.';
            } else {
                $auUrl = null;

                if (
                    isset($_FILES['au_datei']) &&
                    $_FILES['au_datei']['error'] === UPLOAD_ERR_OK
                ) {
                    try {
                        $uploader = new CloudinaryUploader();
                        $auUrl = $uploader->uploadKrankmeldung(
                            $_FILES['au_datei']['tmp_name'],
                            $mitarbeiterId
                        );
                    } catch (RuntimeException $uploadException) {
                        error_log($uploadException->getMessage());
                        $fehler = 'Die Krankmeldung wurde erfasst, aber die Datei '
                            . 'konnte nicht hochgeladen werden.';
                    }
                }

                $sickLeaveRepository->melden($mitarbeiterId, $start, $end, $auUrl);

                // Admin informieren (best effort)
                try {
                    $mailConfig = require __DIR__ . '/../config/mail_config.php';
                    $adminEmail = $mailConfig['admin_email'] ?? null;

                    if ($adminEmail !== null && $adminEmail !== '') {
                        $emailService = new EmailService();
                        $name = trim(($_SESSION['vor_name'] ?? '') . ' ' . ($_SESSION['nach_name'] ?? ''));
                        $zeitraum = $end !== null
                            ? date('d.m.Y', strtotime($start)) . ' bis ' . date('d.m.Y', strtotime($end))
                            : 'ab ' . date('d.m.Y', strtotime($start));

                        $emailService->sendKrankmeldungAdmin(
                            $name,
                            $zeitraum,
                            $auUrl !== null
                        );
                    }
                } catch (Throwable $emailException) {
                    error_log('Krankmeldungs-E-Mail fehlgeschlagen: ' . $emailException->getMessage());
                }

                header('Location: sick_leave.php?gemeldet=1');
                exit;
            }
        }
    }

    $eigeneMeldungen = $sickLeaveRepository->getForEmployee($mitarbeiterId);

    if (isset($_GET['gemeldet'])) {
        $meldung = 'Deine Krankmeldung wurde erfasst und der Admin wurde informiert.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $eigeneMeldungen = [];
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Krank melden - Bella Beauty</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/sick_leave.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Krank melden</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Reichen Sie hier Ihre Krankmeldung und Ihr ärztliches Attest ein.
                </p>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="sl-layout">

            <div class="sl-form-card">
                <form method="post" enctype="multipart/form-data">
                    <?= Csrf::field() ?>

                    <label for="start_datum">Erster Krankheitstag *</label>
                    <input id="start_datum" type="date" name="start_datum" required>

                    <label for="end_datum">Voraussichtliches Ende (Optional)</label>
                    <input id="end_datum" type="date" name="end_datum">

                    <label>Ärztliches Attest hochladen</label>
                    <div class="sl-upload-box" id="sl-upload-box">
                        <input
                            id="au_datei"
                            type="file"
                            name="au_datei"
                            accept="image/png, image/jpeg, image/webp, application/pdf"
                            onchange="document.getElementById('sl-filename').textContent = this.files[0] ? this.files[0].name : '';">
                        <div class="sl-upload-box__icon">📄</div>
                        <div class="sl-upload-box__label">Klicken oder Datei hierher ziehen</div>
                        <div class="sl-upload-box__hint">PDF, JPG oder PNG (max. 5MB)</div>
                        <div class="sl-upload-box__filename" id="sl-filename"></div>
                    </div>

                    <button type="submit" class="sl-submit-btn">Krankmeldung absenden</button>
                </form>
            </div>

            <div class="sl-history-card">
                <h2>Vergangene Krankmeldungen</h2>

                <?php if ($eigeneMeldungen === []): ?>
                    <p class="sl-empty">Noch keine Krankmeldungen erfasst.</p>
                <?php else: ?>
                    <?php foreach ($eigeneMeldungen as $meldungZeile): ?>
                        <?php
                        $tageAnzahl = $meldungZeile['end_datum'] !== null
                            ? (new DateTime($meldungZeile['start_datum']))->diff(new DateTime($meldungZeile['end_datum']))->days + 1
                            : null;
                        ?>
                        <div class="sl-history-row">
                            <div class="sl-history-row__icon">🤒</div>

                            <div class="sl-history-row__body">
                                <div class="sl-history-row__period">
                                    <?= date('d.m.Y', strtotime($meldungZeile['start_datum'])) ?>
                                    <?php if ($meldungZeile['end_datum'] !== null): ?>
                                        – <?= date('d.m.Y', strtotime($meldungZeile['end_datum'])) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="sl-history-row__duration">
                                    Gemeldet am <?= date('d.m.Y', strtotime($meldungZeile['erstellt_am'])) ?>
                                </div>
                            </div>

                            <div class="sl-history-row__days">
                                <?= $tageAnzahl !== null ? $tageAnzahl . ' Tage' : '–' ?>
                            </div>

                            <div class="sl-history-row__status">Erfasst</div>

                            <div class="sl-history-row__file">
                                <?php if (!empty($meldungZeile['au_datei_url'])): ?>
                                    <a href="<?= htmlspecialchars($meldungZeile['au_datei_url']) ?>" target="_blank" rel="noopener">📎 Ansehen</a>
                                <?php else: ?>
                                    <span>Kein Attest</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        var uploadBox = document.getElementById('sl-upload-box');
        ['dragenter', 'dragover'].forEach(function(evt) {
            uploadBox.addEventListener(evt, function(e) {
                e.preventDefault();
                uploadBox.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function(evt) {
            uploadBox.addEventListener(evt, function(e) {
                e.preventDefault();
                uploadBox.classList.remove('is-dragover');
            });
        });
        uploadBox.addEventListener('drop', function(e) {
            var files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('au_datei').files = files;
                document.getElementById('sl-filename').textContent = files[0].name;
            }
        });
    </script>

</body>

</html>