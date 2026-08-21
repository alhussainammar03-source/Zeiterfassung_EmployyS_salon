<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/employeeRepository.php';
require_once __DIR__ . '/../repositories/WorkingHoursRepository.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';

Auth::requireRole('mitarbeiter');

$mitarbeiterId = (int) $_SESSION['user_id'];
$vorName = $_SESSION['vor_name'] ?? '';
$nachName = $_SESSION['nach_name'] ?? '';

$mitarbeiter = false;
$schichtHeuteText = null;
$terminHeuteAnzahl = 0;
$offeneAnfragenAnzahl = 0;

try {
    $pdo = Database::getInstance()->getConnection();

    $employeeRepository = new employeeRepository($pdo);
    $mitarbeiter = $employeeRepository->getEmployeeById($mitarbeiterId);

    $workingHoursRepository = new WorkingHoursRepository($pdo);
    $arbeitszeiten = $workingHoursRepository->getForEmployee($mitarbeiterId);
    $heuteWochentag = (int) date('N');
    $heutigeSchicht = $arbeitszeiten[$heuteWochentag] ?? null;

    if ($heutigeSchicht !== null && !$heutigeSchicht['ist_frei'] && $heutigeSchicht['start_zeit'] !== null) {
        $schichtHeuteText = substr($heutigeSchicht['start_zeit'], 0, 5) . ' - ' . substr($heutigeSchicht['end_zeit'], 0, 5);
    }

    $terminwunschRepository = new TerminwunschRepository($pdo);
    $heuteStart = (new DateTime('today'))->format('Y-m-d H:i:s');
    $heuteEnde = (new DateTime('tomorrow'))->format('Y-m-d H:i:s');

    $terminwuenscheHeute = $terminwunschRepository->getByEmployeeId($mitarbeiterId, $heuteStart, $heuteEnde);
    $terminwuenscheHeute = array_filter(
        $terminwuenscheHeute,
        fn($t) => in_array($t['status'], ['angefragt', 'bestaetigt'], true)
    );
    $terminHeuteAnzahl = count($terminwuenscheHeute);

    // Offene (noch unbeantwortete) Anfragen als Hinweis-Punkt auf der "Termine"-Kachel
    $alleKommendenTermine = $terminwunschRepository->getByEmployeeId(
        $mitarbeiterId,
        (new DateTime())->format('Y-m-d H:i:s'),
        (new DateTime('+30 days'))->format('Y-m-d H:i:s')
    );
    $offeneAnfragenAnzahl = count(array_filter(
        $alleKommendenTermine,
        fn($t) => $t['status'] === 'angefragt'
    ));
} catch (Throwable $exception) {
    // Dashboard bleibt trotzdem nutzbar, nur ohne die Live-Zahlen
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mitarbeiter Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/employee_dashboard.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="edash-welcome">
            <?php if ($mitarbeiter !== false && !empty($mitarbeiter['photo_url'])): ?>
                <img src="<?= htmlspecialchars($mitarbeiter['photo_url']) ?>" alt="" class="edash-welcome__photo">
            <?php else: ?>
                <div class="edash-welcome__photo--placeholder">
                    <?= htmlspecialchars(mb_substr($vorName, 0, 1) . mb_substr($nachName, 0, 1)) ?>
                </div>
            <?php endif; ?>

            <div class="edash-welcome__body">
                <h2>Willkommen zurück, <?= htmlspecialchars($vorName) ?>!</h2>
                <p>Hier ist eine Übersicht über deinen Zeitplan und deine Aufgaben für heute.</p>

                <div class="edash-welcome__tags">
                    <?php if ($schichtHeuteText !== null): ?>
                        <span class="edash-welcome__tag">🕒 Schicht: <?= htmlspecialchars($schichtHeuteText) ?></span>
                    <?php else: ?>
                        <span class="edash-welcome__tag">🕒 Heute frei</span>
                    <?php endif; ?>
                    <span class="edash-welcome__tag">📅 <?= $terminHeuteAnzahl ?> Termine heute</span>
                </div>
            </div>
        </div>

        <div class="edash-quick-grid">

            <a href="../employee/profile.php" class="edash-quick-card">
                <div class="edash-quick-card__icon">👤</div>
                <div class="edash-quick-card__label">Profil</div>
            </a>

            <a href="../employee/my_appointments.php" class="edash-quick-card">
                <?php if ($offeneAnfragenAnzahl > 0): ?>
                    <span class="edash-quick-card__badge" title="<?= $offeneAnfragenAnzahl ?> offene Anfrage(n)"></span>
                <?php endif; ?>
                <div class="edash-quick-card__icon">📅</div>
                <div class="edash-quick-card__label">Termine</div>
            </a>

            <a href="../employee/working_hours.php" class="edash-quick-card">
                <div class="edash-quick-card__icon">🕒</div>
                <div class="edash-quick-card__label">Arbeitszeiten</div>
            </a>

            <a href="../employee/vacation.php" class="edash-quick-card">
                <div class="edash-quick-card__icon">🏖️</div>
                <div class="edash-quick-card__label">Urlaub</div>
            </a>

            <a href="../employee/sick_leave.php" class="edash-quick-card">
                <div class="edash-quick-card__icon">🤒</div>
                <div class="edash-quick-card__label">Krank melden</div>
            </a>

        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>