<?php

/**
 * Gemeinsame Navigation für das gesamte Bella-Beauty-Projekt.
 *
 * Eigenständig: liest Login-Status/Rolle direkt aus der Session,
 * die aufrufende Seite muss nichts vorbereiten außer optional
 * $activeNav ('home' | 'services' | 'about') vor dem include zu setzen.
 */

require_once __DIR__ . '/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn =
    isset($_SESSION['logged_in']) &&
    $_SESSION['logged_in'] === true;

$rolle = $_SESSION['rolle'] ?? '';
$activeNav = $activeNav ?? '';

function navbarLinkClass(string $key, string $activeNav): string
{
    return $key === $activeNav
        ? 'site-nav__link site-nav__link--active'
        : 'site-nav__link';
}
?>

<nav class="site-nav">
    <div class="site-nav__bar">
        <div class="site-nav__logo">..... Beauty</div>

        <div class="site-nav__links">
            <a class="<?= navbarLinkClass('home', $activeNav) ?>" href="<?= Auth::baseUrl() ?>/pages/home_page.php">Home</a>
            <a class="<?= navbarLinkClass('services', $activeNav) ?>" href="<?= Auth::baseUrl() ?>/pages/services.php">Services</a>
            <a class="<?= navbarLinkClass('about', $activeNav) ?>" href="<?= Auth::baseUrl() ?>/pages/about.php">Über uns</a>
            <a class="<?= navbarLinkClass('news', $activeNav) ?>" href="<?= Auth::baseUrl() ?>/pages/news.php">News</a>

            <?php if (!$isLoggedIn): ?>
                <a class="site-nav__link" href="<?= Auth::baseUrl() ?>/pages/login.php">Login</a>
                <a class="site-nav__cta" href="<?= Auth::baseUrl() ?>/pages/register.php">Termin buchen</a>
            <?php elseif ($rolle === 'kunde'): ?>
                <a class="site-nav__link" href="<?= Auth::baseUrl() ?>/pages/kunden_dashbord.php">Mein Dashboard</a>
                <a class="site-nav__link" href="<?= Auth::baseUrl() ?>/customer/my_appointments.php">Meine Termine</a>
                <a class="site-nav__link" href="<?= Auth::baseUrl() ?>/customer/loyalty.php">🎁 Punkte</a>
                <a class="site-nav__cta" href="<?= Auth::baseUrl() ?>/customer/book_appointment.php">Termin buchen</a>
                <a class="site-nav__link" href="<?= Auth::baseUrl() ?>/pages/logout.php">Logout</a>
            <?php elseif ($rolle === 'mitarbeiter'): ?>
                <a class="site-nav__link" href="<?= Auth::baseUrl() ?>/pages/employee_dashboard.php">Mein Dashboard</a>
                <a class="site-nav__cta" href="<?= Auth::baseUrl() ?>/pages/logout.php">Logout</a>
            <?php elseif ($rolle === 'admin'): ?>
                <a class="site-nav__link" href="<?= Auth::baseUrl() ?>/pages/admin_dashboard.php">Admin Dashboard</a>
                <a class="site-nav__cta" href="<?= Auth::baseUrl() ?>/pages/logout.php">Logout</a>
            <?php endif; ?>
        </div>

        <button id="mobile-menu-btn" class="site-nav__menu-btn" aria-label="Menü öffnen">☰</button>
    </div>

    <div id="mobile-menu" class="site-nav__mobile">
        <a class="<?= $activeNav === 'home' ? 'site-nav__link--active' : '' ?>" href="<?= Auth::baseUrl() ?>/pages/home_page.php">Home</a>
        <a class="<?= $activeNav === 'services' ? 'site-nav__link--active' : '' ?>" href="<?= Auth::baseUrl() ?>/pages/services.php">Services</a>
        <a class="<?= $activeNav === 'about' ? 'site-nav__link--active' : '' ?>" href="<?= Auth::baseUrl() ?>/pages/about.php">Über uns</a>
        <a class="<?= $activeNav === 'news' ? 'site-nav__link--active' : '' ?>" href="<?= Auth::baseUrl() ?>/pages/news.php">News</a>

        <?php if (!$isLoggedIn): ?>
            <a href="<?= Auth::baseUrl() ?>/pages/login.php">Login</a>
            <a class="site-nav__cta" href="<?= Auth::baseUrl() ?>/pages/register.php">Termin buchen</a>
        <?php elseif ($rolle === 'kunde'): ?>
            <a href="<?= Auth::baseUrl() ?>/pages/kunden_dashbord.php">Mein Dashboard</a>
            <a href="<?= Auth::baseUrl() ?>/customer/my_appointments.php">Meine Termine</a>
            <a href="<?= Auth::baseUrl() ?>/customer/loyalty.php">🎁 Punkte</a>
            <a class="site-nav__cta" href="<?= Auth::baseUrl() ?>/customer/book_appointment.php">Termin buchen</a>
            <a href="<?= Auth::baseUrl() ?>/pages/logout.php">Logout</a>
        <?php elseif ($rolle === 'mitarbeiter'): ?>
            <a href="<?= Auth::baseUrl() ?>/pages/employee_dashboard.php">Mein Dashboard</a>
            <a href="<?= Auth::baseUrl() ?>/pages/logout.php">Logout</a>
        <?php elseif ($rolle === 'admin'): ?>
            <a href="<?= Auth::baseUrl() ?>/pages/admin_dashboard.php">Admin Dashboard</a>
            <a href="<?= Auth::baseUrl() ?>/pages/logout.php">Logout</a>
        <?php endif; ?>
    </div>
</nav>