<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/VacationRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('mitarbeiter');

$mitarbeiterId = (int) $_SESSION['user_id'];
$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $vacationRepository = new VacationRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } else {
            $start = trim($_POST['start_datum'] ?? '');
            $end = trim($_POST['end_datum'] ?? '');
            $notiz = trim($_POST['notiz'] ?? '');

            if ($start === '' || $end === '') {
                $fehler = 'Bitte Start- und End-Datum angeben.';
            } elseif ($start > $end) {
                $fehler = 'Das Start-Datum muss vor dem End-Datum liegen.';
            } elseif ($vacationRepository->hatUeberschneidung($mitarbeiterId, $start, $end)) {
                $fehler = 'Für diesen Zeitraum hast du bereits einen Urlaubsantrag gestellt.';
            } else {
                $anzahlTage = VacationRepository::berechneWerktage($start, $end);

                if ($anzahlTage <= 0) {
                    $fehler = 'Der gewählte Zeitraum enthält keine Werktage.';
                } else {
                    $vacationRepository->beantragen($mitarbeiterId, $start, $end, $anzahlTage, $notiz !== '' ? $notiz : null);
                    header('Location: vacation.php?beantragt=1');
                    exit;
                }
            }
        }
    }

    $jahr = (int) date('Y');
    $urlaubstageGesamt = $vacationRepository->getUrlaubstageJahr($mitarbeiterId);
    $genommeneTage = $vacationRepository->getGenommeneTage($mitarbeiterId, $jahr);
    $restTage = $urlaubstageGesamt - $genommeneTage;

    $eigeneAntraege = $vacationRepository->getForEmployee($mitarbeiterId);

    if (isset($_GET['beantragt'])) {
        $meldung = 'Dein Urlaubsantrag wurde eingereicht und wartet auf Genehmigung.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $eigeneAntraege = [];
    $urlaubstageGesamt = 0;
    $genommeneTage = 0;
    $restTage = 0;
}

function urlaubStatusLabel(string $status): string
{
    return match ($status) {
        'beantragt' => 'Beantragt',
        'genehmigt' => 'Genehmigt',
        'abgelehnt' => 'Abgelehnt',
        default => $status,
    };
}

function urlaubStatusKlasse(string $status): string
{
    return match ($status) {
        'genehmigt' => 'aktiv',
        default => 'inaktiv',
    };
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urlaub - Bella Beauty</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/vacation.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Urlaubsverwaltung</h1>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="vac-stats">
            <div class="vac-stat-card">
                <div class="vac-stat-card__label">🏖️ Urlaubstage gesamt</div>
                <div class="vac-stat-card__value"><?= $urlaubstageGesamt ?></div>
            </div>
            <div class="vac-stat-card">
                <div class="vac-stat-card__label">✓ Genommen</div>
                <div class="vac-stat-card__value"><?= $genommeneTage ?></div>
            </div>
            <div class="vac-stat-card vac-stat-card--rest">
                <div class="vac-stat-card__label">🎁 Rest</div>
                <div class="vac-stat-card__value"><?= $restTage ?></div>
            </div>
        </div>

        <div class="vac-layout">

            <div class="vac-form-card">
                <h2>➕ Neuen Urlaub beantragen</h2>

                <form method="post">
                    <?= Csrf::field() ?>

                    <div class="vac-form-row">
                        <div>
                            <label for="start_datum">Start</label>
                            <input id="start_datum" type="date" name="start_datum" required>
                        </div>
                        <div>
                            <label for="end_datum">Ende</label>
                            <input id="end_datum" type="date" name="end_datum" required>
                        </div>
                    </div>

                    <label for="notiz">Bemerkung (optional)</label>
                    <textarea id="notiz" name="notiz" rows="3" placeholder="z.B. Sommerurlaub"></textarea>

                    <p><small>Es zählen nur Werktage (Mo-Fr). Wochenenden werden automatisch nicht mitgerechnet.</small></p>

                    <div class="vac-form-actions">
                        <button type="reset" class="vac-btn-cancel">Abbrechen</button>
                        <button type="submit" class="vac-btn-submit">Beantragen</button>
                    </div>
                </form>
            </div>

            <div class="vac-promo-card">
                <div class="vac-promo-card__text">
                    <strong>Auszeit planen</strong>
                    <span>Rechtzeitig einplanen, damit wir den Salonbetrieb optimal für unsere Kunden koordinieren können.</span>
                </div>
            </div>

        </div>

        <div class="vac-history-header">
            <h2>Historie</h2>
        </div>

        <div class="vac-table-wrapper">
            <table class="vac-table">
                <thead>
                    <tr>
                        <th>Zeitraum</th>
                        <th>Bemerkung</th>
                        <th>Tage</th>
                        <th>Status</th>
                        <th>Kommentar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($eigeneAntraege === []): ?>
                        <tr>
                            <td colspan="5">Noch keine Urlaubsanträge gestellt.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($eigeneAntraege as $antrag): ?>
                            <tr>
                                <td>
                                    <?= date('d.m.Y', strtotime($antrag['start_datum'])) ?>
                                    – <?= date('d.m.Y', strtotime($antrag['end_datum'])) ?>
                                </td>
                                <td><?= htmlspecialchars($antrag['mitarbeiter_notiz'] ?? '–') ?></td>
                                <td><?= $antrag['anzahl_tage'] ?></td>
                                <td>
                                    <span class="status <?= urlaubStatusKlasse($antrag['status']) ?>">
                                        <?= urlaubStatusLabel($antrag['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($antrag['admin_kommentar'] ?? '–') ?></td>
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