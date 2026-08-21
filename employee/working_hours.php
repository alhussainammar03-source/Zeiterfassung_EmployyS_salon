<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TimeTrackingRepository.php';
require_once __DIR__ . '/../repositories/employeeRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('mitarbeiter');

$mitarbeiterId = (int) $_SESSION['user_id'];
$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $timeTrackingRepository = new TimeTrackingRepository($pdo);
    $employeeRepository = new employeeRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'einstempeln') {
            if ($timeTrackingRepository->einstempeln($mitarbeiterId)) {
                header('Location: working_hours.php?eingestempelt=1');
                exit;
            }
            $fehler = 'Du bist bereits eingestempelt.';
        } elseif (($_POST['action'] ?? '') === 'ausstempeln') {
            if ($timeTrackingRepository->ausstempeln($mitarbeiterId)) {
                header('Location: working_hours.php?ausgestempelt=1');
                exit;
            }
            $fehler = 'Du bist aktuell nicht eingestempelt.';
        }
    }

    $mitarbeiter = $employeeRepository->getEmployeeById($mitarbeiterId);
    $sollStundenWoche = ($mitarbeiter !== false && $mitarbeiter['soll_stunden_woche'] !== null)
        ? (float) $mitarbeiter['soll_stunden_woche']
        : null;

    $sollTaeglich = $sollStundenWoche !== null ? round($sollStundenWoche / 5, 2) : null;
    $sollMonatlich = $sollStundenWoche !== null ? round($sollStundenWoche * 4.33, 2) : null;

    $offenerEintrag = $timeTrackingRepository->getOffenerEintrag($mitarbeiterId);

    /*
    |--------------------------------------------------------------------------
    | Zeiträume berechnen: heute, diese Woche (Mo-So), dieser Monat
    |--------------------------------------------------------------------------
    */

    $heuteStart = new DateTime('today');
    $heuteEnde = (clone $heuteStart)->modify('+1 day');

    $wochenStart = new DateTime('today');
    $wochentag = (int) $wochenStart->format('N');
    $wochenStart->modify('-' . ($wochentag - 1) . ' days');
    $wochenEnde = (clone $wochenStart)->modify('+7 days');

    $monatsStart = new DateTime('first day of this month');
    $monatsEnde = (clone $monatsStart)->modify('+1 month');

    $stundenHeute = $timeTrackingRepository->summeStunden(
        $mitarbeiterId,
        $heuteStart->format('Y-m-d H:i:s'),
        $heuteEnde->format('Y-m-d H:i:s')
    );

    $stundenWoche = $timeTrackingRepository->summeStunden(
        $mitarbeiterId,
        $wochenStart->format('Y-m-d H:i:s'),
        $wochenEnde->format('Y-m-d H:i:s')
    );

    $stundenMonat = $timeTrackingRepository->summeStunden(
        $mitarbeiterId,
        $monatsStart->format('Y-m-d H:i:s'),
        $monatsEnde->format('Y-m-d H:i:s')
    );

    $letzteEintraege = $timeTrackingRepository->letzteEintraege($mitarbeiterId, 15);

    if (isset($_GET['eingestempelt'])) {
        $meldung = 'Du bist jetzt eingestempelt.';
    }

    if (isset($_GET['ausgestempelt'])) {
        $meldung = 'Du wurdest ausgestempelt.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
}

function formatStunden(?float $stunden): string
{
    if ($stunden === null) {
        return '–';
    }

    $std = (int) floor($stunden);
    $min = (int) round(($stunden - $std) * 60);

    return $std . ' Std. ' . $min . ' Min.';
}

function differenzBadge(float $ist, ?float $soll): string
{
    if ($soll === null) {
        return '';
    }

    $diff = round($ist - $soll, 2);

    if ($diff >= 0) {
        return '<span style="color:#166534;font-weight:600;">+' . formatStunden($diff) . '</span>';
    }

    return '<span style="color:#991b1b;font-weight:600;">-' . formatStunden(abs($diff)) . '</span>';
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeiterfassung - Bella Beauty</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/button.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/working_hours.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Zeiterfassung</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Erfasse deine Arbeitsstunden und behalte deine Zeitbilanz im Blick.
                </p>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="wh-clock-card">

            <?php if ($offenerEintrag !== false): ?>

                <span class="wh-clock-card__status wh-clock-card__status--active">🟢 Eingestempelt</span>
                <div class="wh-clock-card__time"><?= (new DateTime($offenerEintrag['start_zeit']))->format('H:i') ?></div>
                <div class="wh-clock-card__date">Eingestempelt seit <?= (new DateTime($offenerEintrag['start_zeit']))->format('d.m.Y') ?></div>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="ausstempeln">
                    <button type="submit" class="wh-clock-btn wh-clock-btn--stop">
                        <span class="wh-clock-btn__icon">⏹</span>
                        Ende
                    </button>
                </form>

            <?php else: ?>

                <span class="wh-clock-card__status">⚪ Ausgestempelt</span>
                <div class="wh-clock-card__time"><?= (new DateTime())->format('H:i') ?></div>
                <div class="wh-clock-card__date"><?= (new DateTime())->format('l, d. F') ?></div>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="einstempeln">
                    <button type="submit" class="wh-clock-btn">
                        <span class="wh-clock-btn__icon">▶</span>
                        Start
                    </button>
                </form>

            <?php endif; ?>

        </div>

        <h2 class="wh-overview-title">Zeitübersicht</h2>

        <div class="wh-table-wrapper">
            <table class="wh-table">
                <thead>
                    <tr>
                        <th>Zeitraum</th>
                        <th>Gearbeitete Stunden</th>
                        <th>Soll-Stunden</th>
                        <th>Differenz</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Heute</td>
                        <td><?= formatStunden($stundenHeute) ?></td>
                        <td><?= formatStunden($sollTaeglich) ?></td>
                        <td><?= differenzBadge($stundenHeute, $sollTaeglich) ?></td>
                    </tr>
                    <tr>
                        <td>Diese Woche</td>
                        <td><?= formatStunden($stundenWoche) ?></td>
                        <td><?= formatStunden($sollStundenWoche) ?></td>
                        <td><?= differenzBadge($stundenWoche, $sollStundenWoche) ?></td>
                    </tr>
                    <tr>
                        <td>Diesen Monat</td>
                        <td><?= formatStunden($stundenMonat) ?></td>
                        <td><?= formatStunden($sollMonatlich) ?></td>
                        <td><?= differenzBadge($stundenMonat, $sollMonatlich) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if ($sollStundenWoche === null): ?>
            <p style="margin-top: -18px; margin-bottom: 20px;">
                <small>Für dich wurden noch keine Sollstunden hinterlegt – frag deinen Admin danach.</small>
            </p>
        <?php endif; ?>

        <h2 class="wh-overview-title">Letzte Buchungen</h2>

        <div class="wh-table-wrapper">
            <table class="wh-table">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Start</th>
                        <th>Ende</th>
                        <th>Dauer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($letzteEintraege === []): ?>
                        <tr>
                            <td colspan="4">Noch keine Buchungen vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($letzteEintraege as $eintrag): ?>
                            <?php
                            $start = new DateTime($eintrag['start_zeit']);
                            $ende = $eintrag['end_zeit'] !== null ? new DateTime($eintrag['end_zeit']) : null;
                            $dauer = $ende !== null
                                ? round(($ende->getTimestamp() - $start->getTimestamp()) / 3600, 2)
                                : null;
                            ?>
                            <tr>
                                <td><?= $start->format('d.m.Y') ?></td>
                                <td><?= $start->format('H:i') ?></td>
                                <td><?= $ende !== null ? $ende->format('H:i') : '– läuft noch –' ?></td>
                                <td><?= $dauer !== null ? formatStunden($dauer) : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>