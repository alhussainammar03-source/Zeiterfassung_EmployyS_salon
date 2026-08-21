<?php

$rolle = $_SESSION['rolle'] ?? '';

$offeneUrlaubsantraege = 0;
$ungeleseneKrankmeldungen = 0;
$offeneTerminwuensche = 0;

if ($rolle === 'admin') {
    try {
        require_once __DIR__ . '/../config/Database.php';
        require_once __DIR__ . '/../repositories/VacationRepository.php';
        require_once __DIR__ . '/../repositories/SickLeaveRepository.php';
        require_once __DIR__ . '/../repositories/TerminwunschRepository.php';

        $sidebarPdo = Database::getInstance()->getConnection();
        $offeneUrlaubsantraege = (new VacationRepository($sidebarPdo))->countOffen();
        $ungeleseneKrankmeldungen = (new SickLeaveRepository($sidebarPdo))->countUngelesen();
        $offeneTerminwuensche = (new TerminwunschRepository($sidebarPdo))->countOffen();
    } catch (Throwable $exception) {
        // Sidebar bleibt trotzdem nutzbar, nur ohne Badge-Zahlen
    }
}

function sidebarBadge(int $anzahl): string
{
    if ($anzahl <= 0) {
        return '';
    }

    return '<span class="sidebar-badge">' . $anzahl . '</span>';
}

?>

<aside class="sidebar">

    <?php if ($rolle === 'admin'): ?>

        <nav class="sidebar-nav">
            <a href="<?= Auth::baseUrl() ?>/pages/admin_dashboard.php">
                📊 Dashboard
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/employees.php">
                👥 Mitarbeiter
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/services.php">
                💇 Dienstleistungen
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/users.php">
                🧑‍🤝‍🧑 Kunden
            </a>
            <a href="<?= Auth::baseUrl() ?>/termin/termin_nach_gefragt.php">
                📅 Terminwünsche <?= sidebarBadge($offeneTerminwuensche) ?>
            </a>
            <a href="<?= Auth::baseUrl() ?>/termin/zeitslots.php">
                🕒 Termin-Slots
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/vacation_requests.php">
                🏖️ Urlaubsanträge <?= sidebarBadge($offeneUrlaubsantraege) ?>
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/sick_leaves.php">
                🤒 Krankmeldungen <?= sidebarBadge($ungeleseneKrankmeldungen) ?>
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/time_tracking_stats.php">
                ⏱️ Zeiterfassung
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/reports.php">
                📈 Berichte
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/loyalty.php">
                🎁 Treuepunkte
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/news.php">
                📰 Salon News
            </a>
            <a href="<?= Auth::baseUrl() ?>/admin/promotions.php">
                🏷️ Rabatt-Aktionen
            </a>
        </nav>

    <?php elseif ($rolle === 'mitarbeiter'): ?>

        <nav class="sidebar-nav">
            <a href="<?= Auth::baseUrl() ?>/pages/employee_dashboard.php">
                📊 Dashboard
            </a>
            <a href="<?= Auth::baseUrl() ?>/employee/profile.php">
                👤 Mein Profil
            </a>
            <a href="<?= Auth::baseUrl() ?>/employee/my_appointments.php">
                📅 Meine Termine
            </a>
            <a href="<?= Auth::baseUrl() ?>/employee/working_hours.php">
                🕒 Meine Arbeitszeiten
            </a>
            <a href="<?= Auth::baseUrl() ?>/employee/vacation.php">
                🏖️ Urlaub
            </a>
            <a href="<?= Auth::baseUrl() ?>/employee/sick_leave.php">
                🤒 Krank melden
            </a>
        </nav>

    <?php elseif ($rolle === 'kunde'): ?>

        <nav class="sidebar-nav">
            <a href="<?= Auth::baseUrl() ?>/pages/kunden_dashbord.php">
                📊 Dashboard
            </a>
            <a href="<?= Auth::baseUrl() ?>/customer/book_appointment.php">
                📅 Termin buchen
            </a>
            <a href="<?= Auth::baseUrl() ?>/customer/my_appointments.php">
                🗓️ Meine Termine
            </a>
            <a href="<?= Auth::baseUrl() ?>/customer/loyalty.php">
                🎁 Treuepunkte
            </a>
            <a href="<?= Auth::baseUrl() ?>/customer/profile.php">
                👤 Mein Profil
            </a>
        </nav>

    <?php endif; ?>

</aside>