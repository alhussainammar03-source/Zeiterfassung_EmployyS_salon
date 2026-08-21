/**
* SMTP-Zugangsdaten für den E-Mail-Versand (z. B. Gmail).
*
* WICHTIG: Diese Datei enthält Geheimnisse (smtp_password) - nicht ins
* öffentliche Git-Repository committen! In .gitignore aufnehmen.
*
* Für Gmail:
* - smtp_username = deine volle Gmail-Adresse
* - smtp_password = ein App-Passwort (NICHT dein normales Gmail-Passwort!)
* Erstellen unter: https://myaccount.google.com/apppasswords
* (setzt "Bestätigung in zwei Schritten" voraus)
*/

<!-- return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'alhussainammar03@gmail.com',
    'smtp_password' => 'chnc gzgd bjbd hhne',
    'from_email' => 'alhussainammar03@gmail.com',
    'from_name' => 'Bella Beauty',
    'admin_email' => 'alhussainammar03@gmail.com',
];
 -->


<?php

require_once __DIR__ . '/../includes/Env.php';

Env::load(__DIR__ . '/../.env');

// Zugangsdaten kommen jetzt aus der .env-Datei (siehe .env.example).
// WICHTIG: .env niemals ins Git-Repository committen!

return [
    'smtp_host' => Env::get('MAIL_SMTP_HOST', 'smtp.gmail.com'),
    'smtp_port' => (int) Env::get('MAIL_SMTP_PORT', '587'),
    'smtp_username' => Env::get('MAIL_SMTP_USERNAME', ''),
    'smtp_password' => Env::get('MAIL_SMTP_PASSWORD', ''),
    'from_email' => Env::get('MAIL_FROM_EMAIL', ''),
    'from_name' => Env::get('MAIL_FROM_NAME', 'Bella Beauty'),
    'admin_email' => Env::get('MAIL_ADMIN_EMAIL', ''),
];
