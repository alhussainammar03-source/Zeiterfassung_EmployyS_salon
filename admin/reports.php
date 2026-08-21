<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/ReportRepository.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$fehler = '';
$meldung = '';

function formatEuro(float $wert): string
{
    return number_format($wert, 0, ',', '.') . ' €';
}

function formatStd(float $stunden): string
{
    $std = (int) floor($stunden);
    $min = (int) round(($stunden - $std) * 60);

    return $std . ' Std. ' . $min . ' Min.';
}

try {
    $pdo = Database::getInstance()->getConnection();
    $reportRepository = new ReportRepository($pdo);
    $employeeRepository = new employeeRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ziel_speichern') {
        if (Csrf::verify($_POST['csrf_token'] ?? null)) {
            $neuesZiel = (float) ($_POST['ziel'] ?? 0);

            if ($neuesZiel > 0) {
                $reportRepository->setUmsatzZiel($neuesZiel);
                $meldung = 'Umsatzziel wurde aktualisiert.';
            }
        }
    }

    $von = trim($_GET['von'] ?? '');
    $bis = trim($_GET['bis'] ?? '');
    $sortierung = ($_GET['sort'] ?? 'menge') === 'umsatz' ? 'umsatz' : 'menge';

    if ($von === '' || $bis === '') {
        $von = (new DateTime('first day of this month'))->format('Y-m-d');
        $bis = (new DateTime('last day of this month'))->format('Y-m-d');
    }

    $bisExklusiv = (new DateTime($bis))->modify('+1 day')->format('Y-m-d');

    $umsatzGesamt = $reportRepository->getUmsatzGesamt($von . ' 00:00:00', $bisExklusiv . ' 00:00:00');
    $umsatzProTag = $reportRepository->getUmsatzProTag($von . ' 00:00:00', $bisExklusiv . ' 00:00:00');
    $meistgebucht = $reportRepository->getMeistgebuchteDienstleistungenSortiert($von . ' 00:00:00', $bisExklusiv . ' 00:00:00', $sortierung, 10);

    $gebuchteStunden = $reportRepository->getGebuchteStundenProMitarbeiter($von . ' 00:00:00', $bisExklusiv . ' 00:00:00');
    $mitarbeiterListe = $employeeRepository->getAllActiveMitarbeiterMitSollstunden();

    $umsatzZiel = $reportRepository->getUmsatzZiel();
    $zielProzent = $umsatzZiel > 0 ? min(100, round(($umsatzGesamt / $umsatzZiel) * 100)) : 0;

    // Vergleich mit der direkt vorangegangenen, gleich langen Periode
    $periodeLaengeTage = (new DateTime($von))->diff(new DateTime($bisExklusiv))->days;
    $vorherigeBis = $von;
    $vorherigeVon = (new DateTime($von))->modify('-' . $periodeLaengeTage . ' days')->format('Y-m-d');
    $umsatzVorherigePeriode = $reportRepository->getUmsatzGesamt($vorherigeVon . ' 00:00:00', $vorherigeBis . ' 00:00:00');

    $umsatzVeraenderungProzent = $umsatzVorherigePeriode > 0
        ? round((($umsatzGesamt - $umsatzVorherigePeriode) / $umsatzVorherigePeriode) * 100, 1)
        : null;

    // Anzahl Wochen im Zeitraum, für die Soll-Stunden-Hochrechnung
    $wochenImZeitraum = max(0.1, $periodeLaengeTage / 7);
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $umsatzGesamt = 0.0;
    $umsatzProTag = [];
    $meistgebucht = [];
    $gebuchteStunden = [];
    $mitarbeiterListe = [];
    $wochenImZeitraum = 1;
    $umsatzZiel = 30000.0;
    $zielProzent = 0;
    $umsatzVeraenderungProzent = null;
    $sortierung = 'menge';
}

$chartLabels = array_map(
    fn($zeile) => date('D', strtotime($zeile['tag'])),
    $umsatzProTag
);
$chartWerte = array_map(fn($zeile) => (float) $zeile['umsatz'], $umsatzProTag);

$avatarFarben = ['#d63384', '#a92a6c', '#655974', '#22c55e', '#0ea5e9', '#f59e0b'];

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berichte - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/reports_admin.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Umsatz &amp; Leistung</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Überblick der aktuellen Geschäftszahlen.
                </p>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="rep-toolbar">
            <form method="get">
                <input type="date" name="von" value="<?= htmlspecialchars($von) ?>">
                <input type="date" name="bis" value="<?= htmlspecialchars($bis) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortierung) ?>">
                <button type="submit">Anzeigen</button>
            </form>
            <a href="reports_export.php?von=<?= urlencode($von) ?>&bis=<?= urlencode($bis) ?>" class="rep-export-btn">⬇ Export</a>
        </div>

        <div class="rep-top-grid">

            <div class="rep-card">
                <div class="rep-card__label">💶 Gesamtumsatz</div>
                <div class="rep-card__value"><?= formatEuro($umsatzGesamt) ?></div>

                <?php if ($umsatzVeraenderungProzent !== null): ?>
                    <span class="rep-card__compare <?= $umsatzVeraenderungProzent >= 0 ? 'rep-card__compare--up' : 'rep-card__compare--down' ?>">
                        <?= $umsatzVeraenderungProzent >= 0 ? '↗' : '↘' ?> <?= abs($umsatzVeraenderungProzent) ?>% vs. Vorperiode
                    </span>
                <?php endif; ?>

                <div class="rep-goal-row">
                    <span>Ziel: <?= formatEuro($umsatzZiel) ?></span>
                    <span><?= $zielProzent ?>%</span>
                </div>
                <div class="rep-goal-bar">
                    <div class="rep-goal-bar__fill" style="width: <?= $zielProzent ?>%;"></div>
                </div>

                <details>
                    <summary style="font-size: 11px; color: var(--bella-on-surface-variant); cursor: pointer; margin-top: 8px;">Ziel ändern</summary>
                    <form method="post" class="rep-goal-edit">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="ziel_speichern">
                        <input type="number" name="ziel" value="<?= (int) $umsatzZiel ?>" min="0" step="100">
                        <button type="submit">Speichern</button>
                    </form>
                </details>
            </div>

            <div class="rep-card">
                <div class="rep-card__label">📈 Umsatzverlauf</div>
                <canvas id="umsatzChart" height="90"></canvas>
            </div>

        </div>

        <div class="rep-table-card">
            <div class="rep-table-card__header">
                <h2>👥 Mitarbeiterauslastung</h2>
            </div>

            <table class="rep-emp-table">
                <thead>
                    <tr>
                        <th>Mitarbeiter</th>
                        <th>Soll-Stunden</th>
                        <th>Gebucht</th>
                        <th>Auslastung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mitarbeiterListe === []): ?>
                        <tr>
                            <td colspan="4">Keine aktiven Mitarbeiter gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mitarbeiterListe as $index => $mitarbeiter): ?>
                            <?php
                            $id = (int) $mitarbeiter['id'];
                            $gebucht = $gebuchteStunden[$id] ?? 0.0;
                            $sollZeitraum = $mitarbeiter['soll_stunden_woche'] !== null
                                ? round((float) $mitarbeiter['soll_stunden_woche'] * $wochenImZeitraum, 1)
                                : null;
                            $auslastungProzent = ($sollZeitraum !== null && $sollZeitraum > 0)
                                ? min(100, round(($gebucht / $sollZeitraum) * 100))
                                : 0;
                            $repFarbe = $avatarFarben[$id % count($avatarFarben)];
                            $balkenFarbe = $auslastungProzent >= 90 ? '#22c55e' : ($auslastungProzent >= 60 ? '#f59e0b' : '#ef4444');
                            ?>
                            <tr>
                                <td>
                                    <div class="rep-emp-row">
                                        <div class="rep-emp-avatar" style="background: <?= $repFarbe ?>;">
                                            <?= htmlspecialchars(mb_strtoupper(mb_substr($mitarbeiter['vor_name'], 0, 1))) ?>
                                        </div>
                                        <div class="rep-emp-name">
                                            <strong><?= htmlspecialchars($mitarbeiter['vor_name'] . ' ' . $mitarbeiter['nach_name']) ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?= $sollZeitraum !== null ? formatStd($sollZeitraum) : '–' ?></td>
                                <td><?= formatStd($gebucht) ?></td>
                                <td>
                                    <div class="rep-auslastung-bar">
                                        <div class="rep-auslastung-bar__track">
                                            <div class="rep-auslastung-bar__fill" style="width: <?= $auslastungProzent ?>%; background: <?= $balkenFarbe ?>;"></div>
                                        </div>
                                        <span style="font-size:12px; font-weight:600;"><?= $auslastungProzent ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="rep-table-card">
            <div class="rep-table-card__header">
                <h2>⭐ Top 10 meistgebuchte Dienstleistungen</h2>

                <div class="rep-sort-toggle">
                    <a href="?von=<?= urlencode($von) ?>&bis=<?= urlencode($bis) ?>&sort=menge" class="<?= $sortierung === 'menge' ? 'is-active' : '' ?>">Nach Menge</a>
                    <a href="?von=<?= urlencode($von) ?>&bis=<?= urlencode($bis) ?>&sort=umsatz" class="<?= $sortierung === 'umsatz' ? 'is-active' : '' ?>">Nach Umsatz</a>
                </div>
            </div>

            <?php if ($meistgebucht === []): ?>
                <p style="color: var(--bella-on-surface-variant); font-size: 14px;">Keine Buchungen in diesem Zeitraum.</p>
            <?php else: ?>
                <div class="rep-top-services">
                    <?php foreach ($meistgebucht as $index => $dienstleistung): ?>
                        <div class="rep-service-row">
                            <div class="rep-service-rank"><?= $index + 1 ?></div>
                            <div class="rep-service-row__body">
                                <strong><?= htmlspecialchars($dienstleistung['name']) ?></strong>
                                <span><?= (int) $dienstleistung['anzahl'] ?> Buchungen</span>
                            </div>
                            <div class="rep-service-row__value"><?= formatEuro((float) $dienstleistung['umsatz']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        new Chart(document.getElementById('umsatzChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'Umsatz (€)',
                    data: <?= json_encode($chartWerte) ?>,
                    borderColor: '#d63384',
                    backgroundColor: 'rgba(214, 51, 132, 0.15)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

</body>

</html>