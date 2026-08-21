<?php

declare(strict_types=1);

/** Einfache Ausgabe-Escape-Hilfe gegen XSS */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
