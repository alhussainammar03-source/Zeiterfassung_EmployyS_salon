<?php

declare(strict_types=1);

/**
 * Prüft Login-Status und Rolle des aktuellen Benutzers.
 *
 * Ersetzt die freien Funktionen requireRole()/requireAdmin()
 * aus dem früheren includes/role_check.php.
 */
class Auth
{
    private static ?string $baseUrl = null;

    public static function baseUrl(): string
    {
        if (self::$baseUrl === null) {
            require_once __DIR__ . '/Env.php';
            Env::load(__DIR__ . '/../.env');

            self::$baseUrl = Env::get(
                'APP_BASE_URL',
                '/accompanying_files/all/Bella_Project_V_2'
            );
        }

        return self::$baseUrl;
    }

    public static function requireRole(string $requiredRole): void
    {
        self::ensureSessionStarted();

        if (!self::isLoggedIn()) {
            header('Location: ' . self::baseUrl() . '/pages/login.php');
            exit;
        }

        if (($_SESSION['rolle'] ?? '') !== $requiredRole) {
            header('Location: ' . self::baseUrl() . '/pages/home_page.php');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireRole('admin');
    }

    public static function isLoggedIn(): bool
    {
        self::ensureSessionStarted();

        return !empty($_SESSION['logged_in'])
            && $_SESSION['logged_in'] === true;
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
