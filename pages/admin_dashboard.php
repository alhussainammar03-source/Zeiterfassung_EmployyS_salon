<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';
require_once __DIR__ . '/../repositories/VacationRepository.php';
require_once __DIR__ . '/../repositories/SickLeaveRepository.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';

Auth::requireAdmin();

$databaseError = null;
$offeneUrlaubsantraege = 0;
$ungeleseneKrankmeldungen = 0;
$offeneTerminwuensche = 0;
//Sss
try {
    $pdo = Database::getInstance()->getConnection();

    $employeeRepository = new employeeRepository($pdo);
    $employeeCount = $employeeRepository->countAllEmployees();
    $activeEmployeeCount = $employeeRepository->countActiveEmployees();

    $offeneUrlaubsantraege = (new VacationRepository($pdo))->countOffen();
    $ungeleseneKrankmeldungen = (new SickLeaveRepository($pdo))->countUngelesen();
    $offeneTerminwuensche = (new TerminwunschRepository($pdo))->countOffen();
} catch (PDOException $exception) {
    $employeeCount = 0;
    $activeEmployeeCount = 0;
    $databaseError = 'Die Datenbank ist momentan nicht erreichbar.';
}

$adminName = $_SESSION['vor_name'] ?? 'Administrator';

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/admin_dashboard.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="adash-header">
            <h1>Willkommen zurück, <?= htmlspecialchars($adminName) ?></h1>
            <p>Wählen Sie einen Bereich, um Bella Beauty zu verwalten.</p>
        </div>

        <?php if ($databaseError !== null): ?>
            <div class="message error"><?= htmlspecialchars($databaseError) ?></div>
        <?php endif; ?>

        <div class="adash-stats">
            <div class="adash-stat-card">
                <div class="adash-stat-card__value"><?= $employeeCount ?></div>
                <div class="adash-stat-card__label">Mitarbeiter insgesamt</div>
            </div>
            <div class="adash-stat-card">
                <div class="adash-stat-card__value"><?= $activeEmployeeCount ?></div>
                <div class="adash-stat-card__label">Aktive Mitarbeiter</div>
            </div>
        </div>

        <div class="adash-grid">

            <a class="adash-card" href="../admin/employees.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">👥</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Mitarbeiter</h2>
                <p>Verwalten Sie Ihr Team und Profile</p>
            </a>

            <a class="adash-card" href="../admin/users.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">🧑‍🤝‍🧑</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Kunden</h2>
                <p>Kundenstamm pflegen &amp; Historie ansehen</p>
            </a>

            <a class="adash-card" href="../admin/services.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">💇</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Dienstleistungen</h2>
                <p>Services, Preise &amp; Dauer konfigurieren</p>
            </a>

            <a class="adash-card" href="../termin/termin_nach_gefragt.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">📅</div>
                    <?php if ($offeneTerminwuensche > 0): ?>
                        <span class="adash-card__badge"><?= $offeneTerminwuensche ?> Neu</span>
                    <?php else: ?>
                        <span class="adash-card__arrow">→</span>
                    <?php endif; ?>
                </div>
                <h2>Terminwünsche</h2>
                <p>Online-Anfragen prüfen &amp; bestätigen</p>
            </a>

            <a class="adash-card" href="../termin/zeitslots.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">🗓️</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Termin-Slots</h2>
                <p>Verfügbarkeiten &amp; Kalender verwalten</p>
            </a>

            <a class="adash-card" href="../admin/vacation_requests.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">🏖️</div>
                    <?php if ($offeneUrlaubsantraege > 0): ?>
                        <span class="adash-card__badge"><?= $offeneUrlaubsantraege ?> Neu</span>
                    <?php else: ?>
                        <span class="adash-card__arrow">→</span>
                    <?php endif; ?>
                </div>
                <h2>Urlaubsanträge</h2>
                <p>Freistellungen der Mitarbeiter prüfen</p>
            </a>

            <a class="adash-card" href="../admin/sick_leaves.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">🤒</div>
                    <?php if ($ungeleseneKrankmeldungen > 0): ?>
                        <span class="adash-card__badge"><?= $ungeleseneKrankmeldungen ?> Neu</span>
                    <?php else: ?>
                        <span class="adash-card__arrow">→</span>
                    <?php endif; ?>
                </div>
                <h2>Krankmeldungen</h2>
                <p>Ausfälle erfassen &amp; Termine umplanen</p>
            </a>

            <a class="adash-card" href="../admin/time_tracking_stats.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">⏱️</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Zeiterfassung</h2>
                <p>Arbeitszeiten &amp; Pausen kontrollieren</p>
            </a>

            <a class="adash-card" href="../admin/reports.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">📊</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Berichte</h2>
                <p>Umsatz, Auslastung &amp; Statistiken</p>
            </a>

            <a class="adash-card" href="../admin/loyalty.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">🎁</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Treuepunkte</h2>
                <p>Programm verwalten, Prämien einlösen</p>
            </a>

            <a class="adash-card" href="../admin/news.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">📰</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Salon News</h2>
                <p>Neuigkeiten für Kunden veröffentlichen</p>
            </a>

            <a class="adash-card" href="../admin/promotions.php">
                <div class="adash-card__top">
                    <div class="adash-card__icon">🏷️</div>
                    <span class="adash-card__arrow">→</span>
                </div>
                <h2>Rabatt-Aktionen</h2>
                <p>Aktionen &amp; Angebote verwalten</p>
            </a>

        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>