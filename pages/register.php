<?php

session_start();

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/Csrf.php';

$isLoggedIn =
    isset($_SESSION['logged_in']) &&
    $_SESSION['logged_in'] === true;

$rolle = $_SESSION['rolle'] ?? '';
$activeNav = '';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vorName = trim($_POST['vor_name'] ?? '');
    $nachName = trim($_POST['nach_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $agbAkzeptiert = isset($_POST['agb']);

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $message = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
    } elseif (
        $vorName === '' ||
        $nachName === '' ||
        $email === '' ||
        $password === ''
    ) {
        $message = 'Bitte alle Felder ausfüllen.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    } elseif (strlen($password) < 8) {
        $message = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif (!$agbAkzeptiert) {
        $message = 'Bitte akzeptiere die AGB und die Datenschutzerklärung.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            $checkStatement = $db->prepare(
                'SELECT id FROM `user` WHERE email = :email LIMIT 1'
            );

            $checkStatement->execute([
                ':email' => $email
            ]);

            if ($checkStatement->fetch()) {
                $message = 'Diese E-Mail-Adresse ist bereits registriert.';
            } else {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $sql = '
                    INSERT INTO `user`
                    (
                        vor_name,
                        nach_name,
                        telefon1,
                        telefon2,
                        email,
                        strasse,
                        haus_num,
                        plz,
                        stadt,
                        geschlecht,
                        password,rolle
                    )
                    VALUES
                    (
                        :vor_name,
                        :nach_name,
                        :telefon1,
                        :telefon2,
                        :email,
                        :strasse,
                        :haus_num,
                        :plz,
                        :stadt,
                        :geschlecht,
                        :password,
                        :rolle
                    )
                ';

                $statement = $db->prepare($sql);

                $statement->execute([
                    ':vor_name' => $vorName,
                    ':nach_name' => $nachName,
                    ':telefon1' => 0,
                    ':telefon2' => 0,
                    ':email' => $email,
                    ':strasse' => '',
                    ':haus_num' => 0,
                    ':plz' => 0,
                    ':stadt' => '',
                    ':geschlecht' => '',
                    ':password' => $passwordHash,
                    ':rolle' => 'kunde'
                ]);

                $message = 'Registrierung erfolgreich. Jetzt kannst du dich anmelden.';
                $success = true;
            }
        } catch (PDOException $e) {
            $message = 'Datenbankfehler: ' . $e->getMessage();
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

    <title>Registrieren - Bella Beauty</title>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="auth-main">
        <div class="auth-card">

            <div class="auth-card__icon">👑</div>

            <h1>Neues Konto erstellen</h1>
            <p class="auth-card__subtitle">Werden Sie Teil unserer Beauty-Community</p>

            <form method="post" class="auth-form">

                <?= Csrf::field() ?>

                <div class="auth-form__row">
                    <div class="input-box">
                        <label for="vor_name">Vorname</label>
                        <input
                            id="vor_name"
                            type="text"
                            name="vor_name"
                            placeholder="Anna"
                            value="<?= htmlspecialchars($_POST['vor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required>
                    </div>

                    <div class="input-box">
                        <label for="nach_name">Nachname</label>
                        <input
                            id="nach_name"
                            type="text"
                            name="nach_name"
                            placeholder="Müller"
                            value="<?= htmlspecialchars($_POST['nach_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required>
                    </div>
                </div>

                <div class="input-box">
                    <label for="email">E-Mail</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        placeholder="beispiel@email.de"
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>

                <div class="input-box">
                    <label for="password">Passwort</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="••••••••"
                        autocomplete="new-password"
                        required>
                    <p class="input-hint">Mindestens 8 Zeichen, Sonderzeichen empfohlen.</p>
                </div>

                <label class="auth-checkbox">
                    <input type="checkbox" name="agb" required>
                    <span>Ich akzeptiere die <a href="#">AGB</a> und die <a href="#">Datenschutzerklärung</a>.</span>
                </label>

                <button type="submit" class="auth-submit-btn">
                    Konto erstellen
                </button>

            </form>

            <?php if ($message !== ''): ?>
                <div class="auth-message <?= $success ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="auth-card__bottom">
                Bereits registriert? <a href="login.php">Login</a>
            </div>

        </div>

        <div class="auth-images">
            <div style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAKavlIL2PPaFJvdL8xZJWBE2iw6fWF91iSVJJMBQuOs-Wf3C5zRNtpHUPWI4b6j_4yQ3quvWKLv47y0wvWA-u1QQEnZTkKSqdyWEBl2qQwrQXZH3rinC8-9c5_XfWfzyGeK01jqLQ_i-7XtapqL6CF5EpNQnblzirOWacNyBQTtEkXTObltEKMxmsRA6b3w6HBaTXUqodIPP_MkiqsuOvNwc5hmbA13Dh8af7IXFCaCrsKpQhBeD4x')"></div>
            <div style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAVvuO_JlWLhcQ3ebrRfzlFWEuzHjFzCeVXKg3R3kL3TXPrpyAGPNFgaNirrqOvE47fFgJ5ktsd5NNYxZkS7TxSLwCDALoLlrCUi5YTCmaobntAXYgX2GgeIKm73VGezBL221LMafz7_UDb4E74Su8tysCOb1U3gpM3UrPSnFHbqRHtFe589S_c9NSfDslEsBrtlsnlI00TFhybG_aA6Yv7EzKBl9CLRw8-7IdCv6dXZU-8Y0ALPEbQ')"></div>
        </div>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>