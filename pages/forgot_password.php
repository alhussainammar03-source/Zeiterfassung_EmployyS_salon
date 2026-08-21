<?php

session_start();

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../services/PasswordResetService.php';
require_once __DIR__ . '/../includes/Csrf.php';

$meldung = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $meldung = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $meldung = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $resetService = new PasswordResetService($db);

            $konto = $resetService->findKontoPerEmail($email);

            if ($konto !== null) {
                try {
                    $resetService->sendResetLink(
                        $konto['typ'],
                        (int) $konto['id'],
                        $konto['email'],
                        $konto['vor_name']
                    );
                } catch (Throwable $emailException) {
                    error_log('Passwort-Reset-E-Mail fehlgeschlagen: ' . $emailException->getMessage());
                }
            }

            // Immer dieselbe Meldung, egal ob die E-Mail existiert
            // (verhindert, dass jemand herausfindet, welche E-Mails registriert sind)
            $meldung = 'Falls diese E-Mail-Adresse bei uns registriert ist, '
                . 'haben wir dir einen Link zum Zurücksetzen geschickt.';
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $meldung = 'Es ist ein technischer Fehler aufgetreten.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/register.css">
    <link rel="stylesheet" href="../style/login.css">

    <title>Passwort vergessen - Bella Beauty</title>
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="auth-main">
        <div class="auth-card">

            <div class="auth-card__icon">🌸</div>

            <h1>Passwort vergessen</h1>
            <p class="auth-card__subtitle">
                Geben Sie Ihre E-Mail-Adresse ein, um einen Link zum Zurücksetzen Ihres Passworts zu erhalten.
            </p>

            <form method="post" class="auth-form" autocomplete="off">
                <?= Csrf::field() ?>

                <div class="input-box">
                    <label for="email">E-Mail Adresse</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        placeholder="name@beispiel.de"
                        autocomplete="email"
                        required>
                </div>

                <button type="submit" class="auth-submit-btn">
                    Link anfordern →
                </button>
            </form>

            <?php if ($meldung !== ''): ?>
                <div class="auth-message success"><?= htmlspecialchars($meldung, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="auth-card__bottom">
                <a href="login.php">← Zurück zum Login</a>
            </div>

        </div>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>