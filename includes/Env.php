<?php

declare(strict_types=1);

/**
 * Einfacher .env-Datei-Loader (ohne Composer-Abhängigkeit).
 *
 * Liest KEY=WERT-Zeilen aus einer .env-Datei und macht sie über
 * Env::get() verfügbar. Zeilen mit # am Anfang gelten als Kommentar.
 *
 * Nutzung:
 *   Env::load(__DIR__ . '/../.env');
 *   $wert = Env::get('DB_HOST', 'localhost'); // zweites Argument = Fallback
 */
class Env
{
    private static array $werte = [];
    private static bool $geladen = false;

    public static function load(string $pfad): void
    {
        if (self::$geladen) {
            return;
        }

        if (!file_exists($pfad)) {
            throw new RuntimeException(
                "Die Datei .env wurde nicht gefunden unter: {$pfad}\n"
                    . "Kopiere .env.example zu .env und trage deine echten Zugangsdaten ein."
            );
        }

        $zeilen = file($pfad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($zeilen as $zeile) {
            $zeile = trim($zeile);

            if ($zeile === '' || str_starts_with($zeile, '#')) {
                continue;
            }

            if (!str_contains($zeile, '=')) {
                continue;
            }

            [$schluessel, $wert] = explode('=', $zeile, 2);
            $schluessel = trim($schluessel);
            $wert = trim($wert);

            // Anführungszeichen um den Wert entfernen, falls vorhanden
            if (
                (str_starts_with($wert, '"') && str_ends_with($wert, '"')) ||
                (str_starts_with($wert, "'") && str_ends_with($wert, "'"))
            ) {
                $wert = substr($wert, 1, -1);
            }

            self::$werte[$schluessel] = $wert;
        }

        self::$geladen = true;
    }

    public static function get(string $schluessel, ?string $fallback = null): ?string
    {
        return self::$werte[$schluessel] ?? $fallback;
    }
}
