<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TimeTrackingRepository.php';
require_once __DIR__ . '/../repositories/employeeRepository.php';

Auth::requireAdmin();

$fehler = '';

function formatStd(float $stunden): string
{
    $std = (int) floor($stunden);
    $min = (int) round(($stunden - $std) * 60);

    return $std . ' Std. ' . $min . ' Min.';
}

function diffBadge(float $ist, ?float $soll): string
{
    if ($soll === null) {
        return '<span>–</span>';
    }

    $diff = round($ist - $soll, 2);

    if ($diff >= 0) {
        return '<span style="color:#166534;font-weight:600;">+' . formatStd($diff) . '</span>';
    }

    return '<span style="color:#991b1b;font-weight:600;">-' . formatStd(abs($diff)) . '</span>';
}

try {
    $pdo = Database::getInstance()->getConnection();
    $timeTrackingRepository = new TimeTrackingRepository($pdo);
    $employeeRepository = new employeeRepository($pdo);

    $mitarbeiterListe = $employeeRepository->getAllActiveMitarbeiterMitSollstunden();
    $aktuellEingestempelt = $timeTrackingRepository->getAktuellEingestempelt();

    /*
    |--------------------------------------------------------------------------
    | Zeiträume: heute, diese Woche (Mo-So), dieser Monat
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

    $stundenHeute = $timeTrackingRepository->summeStundenAlleMitarbeiter(
        $heuteStart->format('Y-m-d H:i:s'),
        $heuteEnde->format('Y-m-d H:i:s')
    );
    $stundenWoche = $timeTrackingRepository->summeStundenAlleMitarbeiter(
        $wochenStart->format('Y-m-d H:i:s'),
        $wochenEnde->format('Y-m-d H:i:s')
    );
    $stundenMonat = $timeTrackingRepository->summeStundenAlleMitarbeiter(
        $monatsStart->format('Y-m-d H:i:s'),
        $monatsEnde->format('Y-m-d H:i:s')
    );

    /*
    |--------------------------------------------------------------------------
    | Freier Zeitraum-Filter
    |--------------------------------------------------------------------------
    */

    $von = trim($_GET['von'] ?? '');
    $bis = trim($_GET['bis'] ?? '');
    $stundenZeitraum = [];
    $zeitraumGesamt = 0.0;

    if ($von !== '' && $bis !== '' && $von <= $bis) {
        $bisExklusiv = (new DateTime($bis))->modify('+1 day')->format('Y-m-d');

        $stundenZeitraum = $timeTrackingRepository->summeStundenAlleMitarbeiter(
            $von . ' 00:00:00',
            $bisExklusiv . ' 00:00:00'
        );

        $zeitraumGesamt = array_sum($stundenZeitraum);
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $mitarbeiterListe = [];
    $aktuellEingestempelt = [];
    $stundenHeute = $stundenWoche = $stundenMonat = $stundenZeitraum = [];
    $zeitraumGesamt = 0.0;
}

// Chart-Daten (Wochenstunden pro Mitarbeiter) als JSON für Chart.js
$chartLabels = [];
$chartIstWerte = [];
$chartSollWerte = [];

foreach ($mitarbeiterListe as $mitarbeiter) {
    $id = (int) $mitarbeiter['id'];
    $chartLabels[] = $mitarbeiter['vor_name'] . ' ' . $mitarbeiter['nach_name'];
    $chartIstWerte[] = $stundenWoche[$id] ?? 0.0;
    $chartSollWerte[] = $mitarbeiter['soll_stunden_woche'] !== null
        ? (float) $mitarbeiter['soll_stunden_woche']
        : 0.0;
}

$eingestempelteIds = array_column($aktuellEingestempelt, 'employee_id');
$avatarFarben = ['#d63384', '#a92a6c', '#655974', '#22c55e', '#0ea5e9', '#f59e0b'];

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeiterfassung-Statistik - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/time_tracking_stats_admin.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Zeiterfassung Statistik</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Überblick über Arbeitszeiten, Überstunden und Anwesenheiten.
                </p>
            </div>
        </div>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="tts-toolbar">
            <form method="get">
                <input id="von" type="date" name="von" value="<?= htmlspecialchars($von) ?>" placeholder="Von">
                <input id="bis" type="date" name="bis" value="<?= htmlspecialchars($bis) ?>" placeholder="Bis">
                <button type="submit">Anzeigen</button>
            </form>

            <?php if ($von !== '' && $bis !== ''): ?>
                <a href="time_tracking_export.php?von=<?= urlencode($von) ?>&bis=<?= urlencode($bis) ?>" class="tts-export-btn">
                    ⬇ CSV exportieren
                </a>
            <?php else: ?>
                <a href="time_tracking_export.php?von=<?= date('Y-m-01') ?>&bis=<?= date('Y-m-t') ?>" class="tts-export-btn">
                    ⬇ CSV exportieren (dieser Monat)
                </a>
            <?php endif; ?>
        </div>

        <div class="tts-top-grid">

            <div class="tts-card">
                <div class="tts-card__header">
                    <h2>Aktuell eingestempelt</h2>
                    <span class="tts-live-badge"><?= count($aktuellEingestempelt) ?> Live</span>
                </div>

                <?php if ($aktuellEingestempelt === []): ?>
                    <p class="tts-empty">Aktuell ist niemand eingestempelt.</p>
                <?php else: ?>
                    <?php foreach ($aktuellEingestempelt as $eintrag): ?>
                        <?php $ttsFarbe = $avatarFarben[((int) $eintrag['employee_id']) % count($avatarFarben)]; ?>
                        <div class="tts-live-row">
                            <div class="tts-live-avatar" style="background: <?= $ttsFarbe ?>;">
                                <?= htmlspecialchars(mb_strtoupper(mb_substr($eintrag['mitarbeiter_name'], 0, 1))) ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($eintrag['mitarbeiter_name']) ?></strong>
                                <span>Seit <?= (new DateTime($eintrag['start_zeit']))->format('H:i') ?> Uhr</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tts-card">
                <div class="tts-card__header">
                    <h2>Wochenstunden vs. Soll</h2>
                </div>
                <canvas id="stundenChart" height="90"></canvas>
            </div>

        </div>

        <div class="tts-table-card">
            <div class="tts-table-header">
                <h2>Mitarbeiter Übersicht (Stunden)</h2>
            </div>

            <div style="overflow-x: auto;">
                <table class="tts-table">
                    <thead>
                        <tr>
                            <th>Mitarbeiter</th>
                            <th>Status</th>
                            <th>Heute</th>
                            <th>Woche</th>
                            <th>Monat (Bisher)</th>
                            <th>Differenz (Soll)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($mitarbeiterListe === []): ?>
                            <tr>
                                <td colspan="6">Keine aktiven Mitarbeiter gefunden.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mitarbeiterListe as $mitarbeiter): ?>
                                <?php
                                $id = (int) $mitarbeiter['id'];
                                $sollWoche = $mitarbeiter['soll_stunden_woche'] !== null
                                    ? (float) $mitarbeiter['soll_stunden_woche']
                                    : null;

                                $istHeute = $stundenHeute[$id] ?? 0.0;
                                $istWoche = $stundenWoche[$id] ?? 0.0;
                                $istMonat = $stundenMonat[$id] ?? 0.0;

                                $diffWoche = $sollWoche !== null ? round($istWoche - $sollWoche, 2) : null;
                                $istLive = in_array($id, $eingestempelteIds, true);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($mitarbeiter['vor_name'] . ' ' . $mitarbeiter['nach_name']) ?></strong></td>
                                    <td>
                                        <?php if ($istLive): ?>
                                            <span class="tts-status-live">Live</span>
                                        <?php else: ?>
                                            <span class="tts-status-offline">Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatStd($istHeute) ?></td>
                                    <td><?= formatStd($istWoche) ?></td>
                                    <td><?= formatStd($istMonat) ?></td>
                                    <td>
                                        <?php if ($diffWoche === null): ?>
                                            –
                                        <?php elseif ($diffWoche >= 0): ?>
                                            <span class="tts-diff-positive">+<?= formatStd($diffWoche) ?></span>
                                        <?php else: ?>
                                            <span class="tts-diff-negative">-<?= formatStd(abs($diffWoche)) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($von !== '' && $bis !== '' && $von <= $bis): ?>

            <div class="tts-range-section">
                <h2>Ergebnis für <?= htmlspecialchars($von) ?> – <?= htmlspecialchars($bis) ?></h2>

                <div class="tts-table-card">
                    <table class="tts-table">
                        <thead>
                            <tr>
                                <th>Mitarbeiter</th>
                                <th>Stunden im Zeitraum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($mitarbeiterListe === []): ?>
                                <tr>
                                    <td colspan="2">Keine Daten.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mitarbeiterListe as $mitarbeiter): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mitarbeiter['vor_name'] . ' ' . $mitarbeiter['nach_name']) ?></td>
                                        <td><?= formatStd($stundenZeitraum[(int) $mitarbeiter['id']] ?? 0.0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td><strong>Gesamt (alle Mitarbeiter)</strong></td>
                                    <td><strong><?= formatStd($zeitraumGesamt) ?></strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        const ctx = document.getElementById('stundenChart');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                        label: 'Ist-Stunden',
                        data: <?= json_encode($chartIstWerte) ?>,
                        backgroundColor: '#d63384',
                        borderRadius: 4
                    },
                    {
                        label: 'Soll-Stunden',
                        data: <?= json_encode($chartSollWerte) ?>,
                        backgroundColor: '#e5e0da',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Stunden'
                        }
                    }
                }
            }
        });
    </script>

</body>

</html>