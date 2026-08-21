<?php

declare(strict_types=1);

/**
 * Environment Loader
 *
 * محلياً:
 * يقرأ القيم من ملف .env
 *
 * على Railway:
 * يقرأ Environment Variables مباشرة
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

        /*
         * إذا كان ملف .env موجوداً
         * نقرأه، وهذا مفيد على XAMPP
         */
        if (file_exists($pfad)) {

            $zeilen = file(
                $pfad,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

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

                // إزالة " أو '
                if (
                    (str_starts_with($wert, '"') && str_ends_with($wert, '"')) ||
                    (str_starts_with($wert, "'") && str_ends_with($wert, "'"))
                ) {
                    $wert = substr($wert, 1, -1);
                }

                self::$werte[$schluessel] = $wert;
            }
        }

        /*
         * إذا لم يوجد .env لا نرمي Error
         * لأن Railway يوفر Environment Variables
         */
        self::$geladen = true;
    }

    public static function get(
        string $schluessel,
        ?string $fallback = null
    ): ?string {

        /*
         * أولاً: Railway / Server Environment Variable
         */
        $serverWert = getenv($schluessel);

        if ($serverWert !== false && $serverWert !== '') {
            return $serverWert;
        }

        /*
         * ثانياً: قيمة .env المحلية
         */
        if (isset(self::$werte[$schluessel])) {
            return self::$werte[$schluessel];
        }

        /*
         * ثالثاً: fallback
         */
        return $fallback;
    }
}
