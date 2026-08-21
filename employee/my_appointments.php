<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('mitarbeiter');

$fehler = '';
$meldung = '';
$termine = [];

$mitarbeiterId = (int) $_SESSION['user_id'];

$view = in_array($_GET['view'] ?? '', ['day', 'week', 'month'], true)
    ? $_GET['view']
    : 'week';

$bezugsdatum = DateTime::createFromFormat('Y-m-d', $_GET['datum'] ?? '')
    ?: new DateTime();

try {
    $pdo = Database::getInstance()->getConnection();
    $terminwunschRepository = new TerminwunschRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            header('Location: my_appointments.php?fehler=csrf');
            exit;
        }

        if (($_POST['action'] ?? '') === 'stornieren') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $zurueck = $_POST['zurueck'] ?? 'my_appointments.php';

            if ($id && $terminwunschRepository->gehoertZuMitarbeiter($id, $mitarbeiterId)) {
                $termin = $terminwunschRepository->getTerminwunschById($id);

                $terminwunschRepository->changeStatus($id, 'storniert');

                if ($termin !== false) {
                    try {
                        $emailService = new EmailService();

                        $emailService->sendStornoBenachrichtigungAdmin(
                            [
                                'mitarbeiter_name' => $termin['mitarbeiter_name'],
                                'dienstleistung_name' => $termin['dienstleistung_name'],
                                'start' => $termin['terminwunsche_start'],
                            ],
                            'Mitarbeiter: ' . $termin['mitarbeiter_name']
                        );
                    } catch (Throwable $emailException) {
                        error_log(
                            'Storno-Benachrichtigung konnte nicht gesendet werden: '
                                . $emailException->getMessage()
                        );
                    }
                }
            }

            header('Location: ' . $zurueck . (str_contains($zurueck, '?') ? '&' : '?') . 'storniert=1');
            exit;
        }

        if (($_POST['action'] ?? '') === 'notiz_speichern') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $notiz = trim($_POST['notiz'] ?? '');
            $zurueck = $_POST['zurueck'] ?? 'my_appointments.php';

            if ($id && $terminwunschRepository->gehoertZuMitarbeiter($id, $mitarbeiterId)) {
                $terminwunschRepository->updateMitarbeiterNotiz($id, $notiz);
            }

            header('Location: ' . $zurueck . (str_contains($zurueck, '?') ? '&' : '?') . 'notiert=1');
            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Zeitraum je nach Ansicht (Tag/Woche/Monat) berechnen
    |--------------------------------------------------------------------------
    */

    $von = clone $bezugsdatum;
    $von->setTime(0, 0);

    if ($view === 'day') {
        $bis = (clone $von)->modify('+1 day');
        $titelZeitraum = $von->format('d.m.Y');
    } elseif ($view === 'month') {
        $von->modify('first day of this month');
        $bis = (clone $von)->modify('+1 month');
        $titelZeitraum = $von->format('F Y');
    } else { // week
        $wochentag = (int) $von->format('N'); // 1=Mo ... 7=So
        $von->modify('-' . ($wochentag - 1) . ' days');
        $bis = (clone $von)->modify('+7 days');
        $titelZeitraum = $von->format('d.m.Y') . ' – ' . (clone $bis)->modify('-1 day')->format('d.m.Y');
    }

    $termine = $terminwunschRepository->getByEmployeeId(
        $mitarbeiterId,
        $von->format('Y-m-d H:i:s'),
        $bis->format('Y-m-d H:i:s')
    );

    if (isset($_GET['storniert'])) {
        $meldung = 'Der Termin wurde storniert.';
    }

    if (isset($_GET['notiert'])) {
        $meldung = 'Notiz gespeichert.';
    }

    if (isset($_GET['fehler']) && $_GET['fehler'] === 'csrf') {
        $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
}

function ewStatusLabel(string $status): string
{
    return match ($status) {
        'angefragt' => 'Angefragt',
        'bestaetigt' => 'Bestätigt',
        'abgelehnt' => 'Abgelehnt',
        'abgeschlossen' => 'Abgeschlossen',
        'storniert' => 'Storniert',
        default => $status,
    };
}

function ewStatusKlasse(string $status): string
{
    return match ($status) {
        'bestaetigt', 'abgeschlossen' => 'aktiv',
        default => 'inaktiv',
    };
}

// Navigation: vorheriger/nächster Zeitraum
$intervall = match ($view) {
    'day' => '1 day',
    'month' => '1 month',
    default => '7 days',
};

$vorherDatum = (clone $bezugsdatum)->modify('-' . $intervall)->format('Y-m-d');
$naechsterDatum = (clone $bezugsdatum)->modify('+' . $intervall)->format('Y-m-d');
$heuteDatum = (new DateTime())->format('Y-m-d');

$aktuelleUrl = 'my_appointments.php?view=' . $view . '&datum=' . $bezugsdatum->format('Y-m-d');

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meine Termine - Bella Beauty</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/employee_appointments.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Meine Termine</h1>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="ea-toolbar">
            <div class="ea-date-nav">
                <a href="?view=<?= $view ?>&datum=<?= $vorherDatum ?>">‹</a>
                <a class="ea-date-nav__today" href="?view=<?= $view ?>&datum=<?= $heuteDatum ?>">Heute</a>
                <a href="?view=<?= $view ?>&datum=<?= $naechsterDatum ?>">›</a>
                <span class="ea-date-nav__label"><?= htmlspecialchars($titelZeitraum) ?></span>
            </div>

            <div class="ea-view-tabs">
                <a href="?view=day&datum=<?= $bezugsdatum->format('Y-m-d') ?>" class="<?= $view === 'day' ? 'is-active' : '' ?>">Tag</a>
                <a href="?view=week&datum=<?= $bezugsdatum->format('Y-m-d') ?>" class="<?= $view === 'week' ? 'is-active' : '' ?>">Woche</a>
                <a href="?view=month&datum=<?= $bezugsdatum->format('Y-m-d') ?>" class="<?= $view === 'month' ? 'is-active' : '' ?>">Monat</a>
            </div>
        </div>

        <div class="ea-list">
            <?php if ($termine === []): ?>
                <div class="ea-empty">Keine Termine in diesem Zeitraum.</div>
            <?php else: ?>
                <?php foreach ($termine as $termin): ?>
                    <?php
                    $istStornierbar = in_array($termin['status'], ['angefragt', 'bestaetigt'], true);
                    $istAbgeschlossen = $termin['status'] === 'abgeschlossen';
                    ?>
                    <div class="ea-row <?= !$istStornierbar ? 'ea-row--past' : '' ?>">

                        <div class="ea-row__time">
                            <strong><?= date('H:i', strtotime($termin['terminwunsche_start'])) ?></strong>
                            <span><?= date('H:i', strtotime($termin['terminwunsche_ende'])) ?> · <?= date('d.m.Y', strtotime($termin['terminwunsche_start'])) ?></span>
                        </div>

                        <div class="ea-row__customer">
                            <strong><?= htmlspecialchars($termin['kunden_name']) ?></strong>
                            <span>📞 <?= htmlspecialchars($termin['kunden_telefon'] ?? '–') ?></span>
                        </div>

                        <div class="ea-row__service">
                            <strong><?= htmlspecialchars($termin['dienstleistung_name']) ?></strong>
                            <span>
                                🕒 <?= (int) $termin['dienstleistung_dauer'] ?> Min
                                · <?= number_format((float) $termin['dienstleistung_preis'], 0, ',', '.') ?> €
                            </span>
                        </div>

                        <div>
                            <span class="status <?= ewStatusKlasse($termin['status']) ?>">
                                <?= ewStatusLabel($termin['status']) ?>
                            </span>
                        </div>

                        <div class="ea-row__actions">
                            <?php if ($istStornierbar): ?>
                                <details>
                                    <summary>📝 Notiz</summary>

                                    <form method="post" class="ea-notiz-form">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="notiz_speichern">
                                        <input type="hidden" name="id" value="<?= (int) $termin['id'] ?>">
                                        <input type="hidden" name="zurueck" value="<?= htmlspecialchars($aktuelleUrl) ?>">

                                        <textarea name="notiz" rows="2"><?= htmlspecialchars($termin['mitarbeiter_notiz'] ?? '') ?></textarea>
                                        <button type="submit">Speichern</button>
                                    </form>
                                </details>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Diesen Termin wirklich stornieren?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="stornieren">
                                    <input type="hidden" name="id" value="<?= (int) $termin['id'] ?>">
                                    <input type="hidden" name="zurueck" value="<?= htmlspecialchars($aktuelleUrl) ?>">

                                    <button class="ea-cancel-btn" type="submit">❌ Stornieren</button>
                                </form>
                            <?php elseif ($istAbgeschlossen): ?>
                                <details>
                                    <summary>👁️ Details</summary>
                                    <p style="font-size:13px; margin-top:8px; max-width:250px;">
                                        <?= $termin['mitarbeiter_notiz'] !== null && $termin['mitarbeiter_notiz'] !== ''
                                            ? nl2br(htmlspecialchars($termin['mitarbeiter_notiz']))
                                            : 'Keine Notiz vorhanden.' ?>
                                    </p>
                                </details>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>