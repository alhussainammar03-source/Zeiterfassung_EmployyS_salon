<?php

session_start();

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/Csrf.php';

$fehler = '';
$erfolg = false;
$tokenGueltig = false;

$tokenRoh = $_GET['token'] ?? $_POST['token'] ?? '';
$typ = $_GET['typ'] ?? $_POST['typ'] ?? '';

if ($tokenRoh === '' || !in_array($typ, ['employees', 'user'], true)) {
    $fehler = 'Dieser Link ist ungültig.';
} else {
    $tokenHash = hash('sha256', $tokenRoh);
    $tabelleSicher = $typ === 'employees' ? 'employees' : '`user`';

    try {
        $db = Database::getInstance()->getConnection();

        $statement = $db->prepare(
            "SELECT id, vor_name, reset_token_ablauf
             FROM {$tabelleSicher}
             WHERE reset_token = :token
             LIMIT 1"
        );
        $statement->execute(['token' => $tokenHash]);
        $konto = $statement->fetch();

        if ($konto === false) {
            $fehler = 'Dieser Link ist ungültig oder wurde bereits verwendet.';
        } elseif (new DateTime($konto['reset_token_ablauf']) < new DateTime()) {
            $fehler = 'Dieser Link ist abgelaufen. Bitte fordere einen neuen an.';
        } else {
            $tokenGueltig = true;

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $neuesPasswort = $_POST['password'] ?? '';
                $passwortWiederholen = $_POST['password_wiederholen'] ?? '';

                if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                    $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
                } elseif (strlen($neuesPasswort) < 8) {
                    $fehler = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
                } elseif ($neuesPasswort !== $passwortWiederholen) {
                    $fehler = 'Die Passwörter stimmen nicht überein.';
                } else {
                    $passwortHash = password_hash($neuesPasswort, PASSWORD_DEFAULT);

                    $updateStatement = $db->prepare(
                        "UPDATE {$tabelleSicher}
                         SET password = :password, reset_token = NULL, reset_token_ablauf = NULL
                         WHERE id = :id"
                    );
                    $updateStatement->execute([
                        'password' => $passwortHash,
                        'id' => $konto['id'],
                    ]);

                    $erfolg = true;
                    $tokenGueltig = false;
                }
            }
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $fehler = 'Es ist ein technischer Fehler aufgetreten.';
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

    <title>Neues Passwort vergeben - Bella Beauty</title>
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="auth-main">
        <div class="auth-card">

            <div class="auth-card__icon">🌸</div>

            <h1>Neues Passwort vergeben</h1>

            <?php if ($erfolg): ?>

                <p class="auth-card__subtitle">
                    Dein Passwort wurde erfolgreich geändert.
                </p>

                <div class="auth-card__bottom">
                    <a href="login.php">Jetzt einloggen</a>
                </div>

            <?php elseif (!$tokenGueltig): ?>

                <p class="auth-card__subtitle"><?= htmlspecialchars($fehler, ENT_QUOTES, 'UTF-8') ?></p>

                <div class="auth-card__bottom">
                    <a href="forgot_password.php">Neuen Link anfordern</a>
                </div>

            <?php else: ?>

                <p class="auth-card__subtitle">Bitte vergib ein neues Passwort für dein Konto.</p>

                <form method="post" class="auth-form" autocomplete="off">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($tokenRoh, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="typ" value="<?= htmlspecialchars($typ, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="input-box">
                        <label for="password">Neues Passwort</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="••••••••"
                            autocomplete="new-password"
                            required>
                        <p class="input-hint">Mindestens 8 Zeichen.</p>
                    </div>

                    <div class="input-box">
                        <label for="password_wiederholen">Passwort wiederholen</label>
                        <input
                            id="password_wiederholen"
                            type="password"
                            name="password_wiederholen"
                            placeholder="••••••••"
                            autocomplete="new-password"
                            required>
                    </div>

                    <button type="submit" class="auth-submit-btn">
                        Passwort speichern
                    </button>
                </form>

                <?php if ($fehler !== ''): ?>
                    <div class="auth-message error"><?= htmlspecialchars($fehler, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>