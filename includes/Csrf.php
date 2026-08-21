<?php

declare(strict_types=1);

/**
 * Schutz gegen Cross-Site-Request-Forgery (CSRF).
 *
 * Nutzung im Formular:
 *   <form method="post">
 *       <?= Csrf::field() ?>
 *       ...
 *   </form>
 *
 * Nutzung bei der Verarbeitung:
 *   if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
 *       // Anfrage ablehnen
 *   }
 */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Gibt den aktuellen Token zurück (erzeugt bei Bedarf einen neuen).
     */
    public static function token(): string
    {
        self::ensureSessionStarted();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Gibt ein fertiges verstecktes Input-Feld für Formulare zurück.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    /**
     * Prüft einen übermittelten Token gegen den in der Session gespeicherten.
     */
    public static function verify(?string $token): bool
    {
        self::ensureSessionStarted();

        if (
            $token === null ||
            $token === '' ||
            empty($_SESSION[self::SESSION_KEY])
        ) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
