

// Zugangsdaten aus deinem Cloudinary-Dashboard (cloudinary.com/console).
// WICHTIG: Diese Datei enthält Geheimnisse (api_secret) - nicht ins
// öffentliche Git-Repository committen! Trag sie in .gitignore ein,
// so wie es idealerweise auch mit database_config.php gehandhabt wird.

/* return [
    'cloud_name' => 'ncvlilbs',
    'api_key' => '443417536944854',
    'api_secret' => 'pQ_SdkPMzS3tUjaqyVk74E7qDNs',
    'upload_folder' => 'bella_beauty/employees',
];
 */

<?php

require_once __DIR__ . '/../includes/Env.php';

Env::load(__DIR__ . '/../.env');

// Zugangsdaten kommen jetzt aus der .env-Datei (siehe .env.example).
// WICHTIG: .env niemals ins Git-Repository committen!

return [
    'cloud_name' => Env::get('CLOUDINARY_CLOUD_NAME', ''),
    'api_key' => Env::get('CLOUDINARY_API_KEY', ''),
    'api_secret' => Env::get('CLOUDINARY_API_SECRET', ''),
    'upload_folder' => Env::get('CLOUDINARY_UPLOAD_FOLDER', 'bella_beauty'),
];