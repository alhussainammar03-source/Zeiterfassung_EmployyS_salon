<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/LoyaltyRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('kunde');

$kundeId = (int) $_SESSION['user_id'];
$meldung = '';
$fehler = '';
$neuerCode = null;

try {
    $pdo = Database::getInstance()->getConnection();
    $loyaltyRepository = new LoyaltyRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'einloesen') {
            $code = $loyaltyRepository->praemieEinloesen($kundeId);

            if ($code !== null) {
                $neuerCode = $code;
                $meldung = 'Prämie eingelöst! Zeig diesen Code beim nächsten Besuch im Salon vor.';
            } else {
                $fehler = 'Du hast noch nicht genug Punkte für diese Prämie.';
            }
        }
    }

    $settings = $loyaltyRepository->getSettings();
    $punkte = $loyaltyRepository->getPunkte($kundeId);
    $fehlendePunkte = max(0, (int) $settings['threshold_points'] - $punkte);
    $fortschrittProzent = min(100, (int) round(($punkte / max(1, (int) $settings['threshold_points'])) * 100));
    $kannEinloesen = $punkte >= (int) $settings['threshold_points'];

    $redemptions = $loyaltyRepository->getRedemptionsForCustomer($kundeId);
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $settings = ['points_per_euro' => 1, 'threshold_points' => 1000, 'reward_text' => 'Gutschein'];
    $punkte = 0;
    $fehlendePunkte = 0;
    $fortschrittProzent = 0;
    $kannEinloesen = false;
    $redemptions = [];
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treuepunkte - Bella Beauty</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/customer_loyalty.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>🎁 Treuepunkte</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Sammle Punkte bei jedem Besuch und löse sie gegen Prämien ein.
                </p>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <?php if ($neuerCode !== null): ?>
            <div class="cl-code-banner">
                🎉 Dein Gutschein-Code: <?= htmlspecialchars($neuerCode) ?>
            </div>
        <?php endif; ?>

        <div class="cl-hero">
            <div class="cl-hero__label">Dein Punktestand</div>
            <div class="cl-hero__value"><?= $punkte ?> Punkte</div>

            <div class="cl-hero__bar-track">
                <div class="cl-hero__bar-fill" style="width: <?= $fortschrittProzent ?>%;"></div>
            </div>

            <?php if ($kannEinloesen): ?>
                <div class="cl-hero__sub">🎉 Du hast genug Punkte für: <strong><?= htmlspecialchars($settings['reward_text']) ?></strong></div>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="einloesen">
                    <button type="submit" class="cl-hero__redeem-btn">Prämie einlösen</button>
                </form>
            <?php else: ?>
                <div class="cl-hero__sub">Noch <?= $fehlendePunkte ?> Punkte bis zu deinem <?= htmlspecialchars($settings['reward_text']) ?></div>
            <?php endif; ?>
        </div>

        <div class="cl-info-note">
            💡 Du sammelst <?= htmlspecialchars((string) $settings['points_per_euro']) ?> Punkt(e) pro ausgegebenem Euro bei abgeschlossenen Terminen.
        </div>

        <h2 class="cl-history-title">Deine eingelösten Prämien</h2>

        <div class="cl-history-list">
            <?php if ($redemptions === []): ?>
                <div class="cl-empty">Noch keine Prämien eingelöst.</div>
            <?php else: ?>
                <?php foreach ($redemptions as $r): ?>
                    <div class="cl-history-row">
                        <div class="cl-history-row__icon">🎁</div>
                        <div class="cl-history-row__body">
                            <strong><?= htmlspecialchars($r['reward_text']) ?></strong>
                            <span><?= htmlspecialchars($r['code']) ?> · <?= (int) $r['punkte'] ?> Punkte · <?= date('d.m.Y', strtotime($r['erstellt_am'])) ?></span>
                        </div>
                        <span class="cl-history-status cl-history-status--<?= $r['status'] ?>">
                            <?= $r['status'] === 'eingeloest' ? 'Eingelöst' : 'Offen' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>