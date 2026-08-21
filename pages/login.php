<?php

session_start();

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/Csrf.php';
require_once __DIR__ . '/../includes/LoginRateLimiter.php';

$message = '';

/*
|--------------------------------------------------------------------------
| Bereits eingeloggte Benutzer weiterleiten
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['logged_in']) &&
    $_SESSION['logged_in'] === true
) {
    switch ($_SESSION['rolle'] ?? '') {
        case 'admin':
            header('Location: home_page.php');
            exit;

        case 'mitarbeiter':
            header('Location: home_page.php');
            exit;

        case 'kunde':
            header('Location: home_page.php');
            exit;

        default:
            session_unset();
            session_destroy();

            header('Location: login.php');
            exit;
    }
}

/*
|--------------------------------------------------------------------------
| Datenbankverbindung
|--------------------------------------------------------------------------
*/

try {
    $db = Database::getInstance()->getConnection();
} catch (RuntimeException $e) {
    error_log($e->getMessage());

    die('Die Datenbank ist momentan nicht erreichbar.');
}

/*
|--------------------------------------------------------------------------
| Login-Verarbeitung
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $message = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $rateLimiter = new LoginRateLimiter($db);
        $ipAdresse = $_SERVER['REMOTE_ADDR'] ?? 'unbekannt';
        $gesperrtMinuten = $email !== '' ? $rateLimiter->istGesperrt($email, $ipAdresse) : 0;

        if ($gesperrtMinuten > 0) {
            $message = 'Zu viele fehlgeschlagene Login-Versuche. Bitte warte noch '
                . $gesperrtMinuten . ' Minute(n) und versuche es erneut.';
        } elseif ($email === '' || $password === '') {
            $message = 'Bitte E-Mail und Passwort eingeben.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Bitte eine gültige E-Mail-Adresse eingeben.';
        } else {
            try {
                $account = null;
                $rolle = null;

                /*
            |----------------------------------------------------------------
            | 1. Zuerst in employees suchen (admin / mitarbeiter)
            |----------------------------------------------------------------
            */

                $statement = $db->prepare(
                    'SELECT id, vor_name, nach_name, email, password, role, status
                 FROM employees
                 WHERE email = :email
                 LIMIT 1'
                );
                $statement->execute(['email' => $email]);
                $mitarbeiter = $statement->fetch();

                if (
                    $mitarbeiter !== false &&
                    password_verify($password, $mitarbeiter['password'])
                ) {
                    if ($mitarbeiter['status'] !== 'aktiv') {
                        $message = 'Dieses Konto ist deaktiviert. Bitte wende dich an einen Admin.';
                    } else {
                        $account = $mitarbeiter;
                        $rolle = $mitarbeiter['role'];
                    }
                }

                /*
            |----------------------------------------------------------------
            | 2. Falls nicht gefunden, in user suchen (kunde)
            |----------------------------------------------------------------
            */

                if ($account === null && $message === '') {
                    $statement = $db->prepare(
                        'SELECT id, vor_name, nach_name, email, password, rolle, status
                     FROM `user`
                     WHERE email = :email
                     LIMIT 1'
                    );
                    $statement->execute(['email' => $email]);
                    $kunde = $statement->fetch();

                    if (
                        $kunde !== false &&
                        password_verify($password, $kunde['password'])
                    ) {
                        if ($kunde['status'] !== 'aktiv') {
                            $message = 'Dieses Konto ist deaktiviert. Bitte wende dich an uns.';
                        } else {
                            $account = $kunde;
                            $rolle = $kunde['rolle'];
                        }
                    }
                }

                /*
            |----------------------------------------------------------------
            | Login abschließen
            |----------------------------------------------------------------
            */

                if ($account !== null && $rolle !== null) {
                    $rateLimiter->zuruecksetzen($email);

                    session_regenerate_id(true);

                    if (isset($_POST['remember'])) {
                        // Session-Cookie auf 30 Tage verlängern statt "bis Browser schließt"
                        setcookie(
                            session_name(),
                            session_id(),
                            time() + 60 * 60 * 24 * 30,
                            '/'
                        );
                    }

                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $account['id'];
                    $_SESSION['vor_name'] = $account['vor_name'];
                    $_SESSION['nach_name'] = $account['nach_name'];
                    $_SESSION['email'] = $account['email'];
                    $_SESSION['rolle'] = $rolle;

                    switch ($rolle) {
                        case 'admin':
                            header('Location: home_page.php');
                            exit;

                        case 'mitarbeiter':
                            header('Location: home_page.php');
                            exit;

                        case 'kunde':
                            header('Location: home_page.php');
                            exit;

                        default:
                            session_unset();
                            session_destroy();

                            $message = 'Für dieses Benutzerkonto wurde keine gültige Rolle gefunden.';
                    }
                } elseif ($message === '') {
                    $rateLimiter->versuchProtokollieren($email, $ipAdresse);

                    $message = 'E-Mail oder Passwort ist falsch.';
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());

                $message = 'Beim Login ist ein technischer Fehler aufgetreten.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/register.css">
    <link rel="stylesheet" href="../style/login.css">

    <title>Login - Bella Beauty</title>
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="auth-main">
        <div class="auth-card">

            <div class="auth-card__icon">🌸</div>

            <h1>Willkommen zurück</h1>
            <p class="auth-card__subtitle">Melden Sie sich in Ihrem Account an</p>

            <form method="post" class="auth-form" autocomplete="off">

                <?= Csrf::field() ?>

                <div class="input-box">
                    <label for="email">E-Mail Adresse</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        placeholder="beispiel@mail.de"
                        value="<?= htmlspecialchars(
                                    $_POST['email'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        autocomplete="email"
                        required>
                </div>

                <div class="input-box">
                    <div class="auth-label-row">
                        <label for="password">Passwort</label>
                        <a href="forgot_password.php">Passwort vergessen?</a>
                    </div>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required>
                </div>

                <label class="auth-remember-row">
                    <input type="checkbox" name="remember">
                    <span>Angemeldet bleiben</span>
                </label>

                <button type="submit" class="auth-submit-btn">
                    Anmelden
                </button>

            </form>

            <?php if ($message !== ''): ?>
                <div class="auth-message error">
                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <div class="auth-card__bottom">
                Noch kein Konto? <a href="register.php">Jetzt registrieren</a>
            </div>

        </div>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>