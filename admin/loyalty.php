<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/LoyaltyRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $loyaltyRepository = new LoyaltyRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'einstellungen_speichern') {
            $pointsPerEuro = (float) ($_POST['points_per_euro'] ?? 1);
            $threshold = (int) ($_POST['threshold_points'] ?? 1000);
            $rewardText = trim($_POST['reward_text'] ?? '');

            if ($rewardText === '' || $threshold <= 0 || $pointsPerEuro <= 0) {
                $fehler = 'Bitte gültige Werte angeben.';
            } else {
                $loyaltyRepository->updateSettings($pointsPerEuro, $threshold, $rewardText);
                header('Location: loyalty.php?gespeichert=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'eingeloest_markieren') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id) {
                $loyaltyRepository->markiereEingeloest($id);
            }
            header('Location: loyalty.php?markiert=1');
            exit;
        }
    }

    $settings = $loyaltyRepository->getSettings();
    $redemptions = $loyaltyRepository->getAllRedemptions();

    $offeneAnzahl = count(array_filter($redemptions, fn($r) => $r['status'] === 'offen'));
    $eingeloesteAnzahl = count(array_filter($redemptions, fn($r) => $r['status'] === 'eingeloest'));

    if (isset($_GET['gespeichert'])) {
        $meldung = 'Einstellungen gespeichert.';
    }
    if (isset($_GET['markiert'])) {
        $meldung = 'Als eingelöst markiert.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $settings = ['points_per_euro' => 1, 'threshold_points' => 1000, 'reward_text' => ''];
    $redemptions = [];
    $offeneAnzahl = 0;
    $eingeloesteAnzahl = 0;
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treuepunkte-Programm - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/loyalty_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Treuepunkte-Programm</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Punkte-Einstellungen verwalten und eingelöste Prämien bestätigen.
                </p>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="loy-stats">
            <div class="loy-stat-card">
                <div class="loy-stat-card__value"><?= $offeneAnzahl ?></div>
                <div class="loy-stat-card__label">🎁 Offene Einlösungen</div>
            </div>
            <div class="loy-stat-card">
                <div class="loy-stat-card__value"><?= $eingeloesteAnzahl ?></div>
                <div class="loy-stat-card__label">✅ Bereits eingelöst</div>
            </div>
            <div class="loy-stat-card">
                <div class="loy-stat-card__value"><?= (int) $settings['threshold_points'] ?></div>
                <div class="loy-stat-card__label">🎯 Punkte-Schwelle</div>
            </div>
        </div>

        <div class="loy-layout">

            <div class="loy-settings-card">
                <h2>⚙️ Einstellungen</h2>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="einstellungen_speichern">

                    <label>Punkte pro ausgegebenem Euro</label>
                    <input type="number" step="0.1" min="0.1" name="points_per_euro" value="<?= htmlspecialchars((string) $settings['points_per_euro']) ?>" required>

                    <label>Punkte-Schwelle für Prämie</label>
                    <input type="number" min="1" name="threshold_points" value="<?= (int) $settings['threshold_points'] ?>" required>

                    <label>Prämien-Text</label>
                    <input type="text" name="reward_text" value="<?= htmlspecialchars($settings['reward_text']) ?>" required>

                    <button type="submit">Speichern</button>
                </form>
            </div>

            <div class="loy-table-card">
                <div class="loy-table-card__header">
                    <h2>Eingelöste Prämien</h2>
                </div>

                <div style="overflow-x: auto;">
                    <table class="loy-table">
                        <thead>
                            <tr>
                                <th>Kunde</th>
                                <th>Code</th>
                                <th>Prämie</th>
                                <th>Punkte</th>
                                <th>Status</th>
                                <th>Erstellt am</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($redemptions === []): ?>
                                <tr>
                                    <td colspan="7">Keine Einlösungen vorhanden.</td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $loyAvatarFarben = ['#d63384', '#a92a6c', '#655974', '#22c55e', '#0ea5e9', '#f59e0b'];
                                ?>
                                <?php foreach ($redemptions as $r): ?>
                                    <?php $loyFarbe = $loyAvatarFarben[((int) $r['id']) % count($loyAvatarFarben)]; ?>
                                    <tr>
                                        <td>
                                            <div class="loy-person-row">
                                                <div class="loy-avatar" style="background: <?= $loyFarbe ?>;">
                                                    <?= htmlspecialchars(mb_strtoupper(mb_substr($r['kunden_name'], 0, 1))) ?>
                                                </div>
                                                <span><?= htmlspecialchars($r['kunden_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="loy-code"><?= htmlspecialchars($r['code']) ?></span></td>
                                        <td><?= htmlspecialchars($r['reward_text']) ?></td>
                                        <td><?= (int) $r['punkte'] ?></td>
                                        <td>
                                            <span class="loy-status loy-status--<?= $r['status'] ?>">
                                                <?= $r['status'] === 'eingeloest' ? 'Eingelöst' : 'Offen' ?>
                                            </span>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($r['erstellt_am'])) ?></td>
                                        <td>
                                            <?php if ($r['status'] === 'offen'): ?>
                                                <form method="post">
                                                    <?= Csrf::field() ?>
                                                    <input type="hidden" name="action" value="eingeloest_markieren">
                                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                    <button type="submit" class="loy-icon-btn" title="Als eingelöst markieren">✓</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>