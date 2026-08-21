<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../repositories/LoyaltyRepository.php';
require_once __DIR__ . '/../repositories/NewsRepository.php';
require_once __DIR__ . '/../repositories/PromotionRepository.php';

Auth::requireRole('kunde');

$kundeId = (int) $_SESSION['user_id'];
$vorName = $_SESSION['vor_name'] ?? '';
$fehler = '';

$naechsterTermin = false;
$punkte = 0;
$fortschrittProzent = 0;
$fehlendePunkte = 0;
$rewardText = '';

try {
    $pdo = Database::getInstance()->getConnection();

    $terminwunschRepository = new TerminwunschRepository($pdo);
    $naechsterTermin = $terminwunschRepository->getNaechsterTerminFuerKunde($kundeId);

    $loyaltyRepository = new LoyaltyRepository($pdo);
    $settings = $loyaltyRepository->getSettings();
    $punkte = $loyaltyRepository->getPunkte($kundeId);
    $fehlendePunkte = max(0, (int) $settings['threshold_points'] - $punkte);
    $fortschrittProzent = min(100, (int) round(($punkte / max(1, (int) $settings['threshold_points'])) * 100));
    $rewardText = $settings['reward_text'];

    $newsRepository = new NewsRepository($pdo);
    $aktuelleNews = $newsRepository->getVeroeffentlicht(3);

    $promotionRepository = new PromotionRepository($pdo);
    $aktionen = $promotionRepository->getAktiv(4);
} catch (Throwable $exception) {
    $fehler = 'Einige Daten konnten momentan nicht geladen werden.';
    $aktuelleNews = [];
    $aktionen = [];
}

function dashStatusLabel(string $status): string
{
    return match ($status) {
        'angefragt' => 'Angefragt',
        'bestaetigt' => 'Bestätigt',
        default => $status,
    };
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mein Dashboard - Bella Beauty</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/button.css">
    <link rel="stylesheet" href="../style/sidebar.css">
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <h1>Hallo, <?= htmlspecialchars($vorName, ENT_QUOTES, 'UTF-8') ?>!</h1>
        <p>Schön, dass du wieder da bist. Dein nächster Glow-up wartet schon auf dich.</p>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="profile-grid" style="margin: 24px 0;">

            <a href="../customer/book_appointment.php" class="profile-section" style="background: linear-gradient(135deg, #d63384, #a8296b); color: white; text-decoration: none;">
                <h2 style="color: white; border-bottom-color: rgba(255,255,255,0.2);">📅 Termin buchen</h2>
                <p style="margin:0;">Wähle deinen Service und finde den perfekten Zeitpunkt für dich.</p>
            </a>

            <a href="../customer/my_appointments.php" class="profile-section" style="text-decoration: none;">
                <h2>🗓️ Meine Termine</h2>
                <p style="margin:0; color: var(--bella-on-surface-variant, #584048);">Verwalte deine bestehenden Buchungen und sieh dir deine Historie an.</p>
            </a>

            <a href="../customer/loyalty.php" class="profile-section" style="text-decoration: none;">
                <h2>🎁 Loyalty Club</h2>
                <p style="font-size: 22px; font-weight: 700; color: var(--bella-primary, #d63384); margin: 0 0 8px 0;">
                    <?= $punkte ?> Punkte
                </p>
                <div style="background:#f1ecf5; border-radius: 999px; height: 8px; overflow: hidden; margin-bottom: 6px;">
                    <div style="background: linear-gradient(135deg, #d63384, #a8296b); height: 100%; width: <?= $fortschrittProzent ?>%;"></div>
                </div>
                <p style="margin:0; font-size: 13px; color: var(--bella-on-surface-variant, #584048);">
                    Noch <?= $fehlendePunkte ?> Punkte bis zu deinem <?= htmlspecialchars($rewardText) ?>
                </p>
            </a>

        </div>

        <h2>Dein nächster Termin</h2>

        <?php if ($naechsterTermin === false): ?>

            <div class="profile-section">
                <p style="margin:0;">Du hast aktuell keinen bevorstehenden Termin. <a href="../customer/book_appointment.php">Jetzt buchen →</a></p>
            </div>

        <?php else: ?>

            <div class="profile-section" style="display:flex; gap:20px; align-items:center; flex-wrap:wrap;">

                <?php if (!empty($naechsterTermin['dienstleistung_foto'])): ?>
                    <img src="<?= htmlspecialchars($naechsterTermin['dienstleistung_foto']) ?>" alt="" style="width:120px; height:90px; object-fit:cover; border-radius:10px;">
                <?php endif; ?>

                <div style="flex:1; min-width:200px;">
                    <span class="status <?= $naechsterTermin['status'] === 'bestaetigt' ? 'aktiv' : 'inaktiv' ?>">
                        <?= dashStatusLabel($naechsterTermin['status']) ?>
                    </span>

                    <h3 style="margin: 8px 0 4px 0;"><?= htmlspecialchars($naechsterTermin['dienstleistung_name']) ?></h3>

                    <p style="margin:0; color: var(--bella-on-surface-variant, #584048);">
                        📅 <?= date('d.m.Y', strtotime($naechsterTermin['terminwunsche_start'])) ?>
                        &nbsp;·&nbsp;
                        🕒 <?= date('H:i', strtotime($naechsterTermin['terminwunsche_start'])) ?>
                        - <?= date('H:i', strtotime($naechsterTermin['terminwunsche_ende'])) ?>
                        &nbsp;·&nbsp;
                        💇 <?= htmlspecialchars($naechsterTermin['mitarbeiter_name']) ?>
                    </p>
                </div>

                <div style="font-size:20px; font-weight:700; color: var(--bella-primary, #d63384);">
                    <?= number_format((float) $naechsterTermin['dienstleistung_preis'], 2, ',', '.') ?> €
                </div>

            </div>

        <?php endif; ?>

        <?php if ($aktionen !== []): ?>
            <h2 style="margin-top: 40px;">Exklusiv für dich</h2>

            <div class="profile-grid">
                <?php foreach ($aktionen as $aktion): ?>
                    <div class="profile-section" style="background: var(--bella-primary-light, #fce7f3);">
                        <div style="font-size:28px; margin-bottom:8px;"><?= htmlspecialchars($aktion['icon']) ?></div>
                        <h3 style="margin: 0 0 6px 0;"><?= htmlspecialchars($aktion['title']) ?></h3>
                        <p style="margin:0; font-size:14px; color: var(--bella-on-surface-variant, #584048);">
                            <?= htmlspecialchars($aktion['description']) ?>
                        </p>
                        <?php if ($aktion['valid_until'] !== null): ?>
                            <p style="margin: 8px 0 0 0; font-size:12px; color: var(--bella-primary, #d63384); font-weight:600;">
                                Gültig bis <?= date('d.m.Y', strtotime($aktion['valid_until'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2 style="margin-top: 40px;">Salon News</h2>
            <a href="../pages/news.php">Alle ansehen →</a>
        </div>

        <div class="profile-grid">
            <?php if ($aktuelleNews === []): ?>
                <div class="profile-section">
                    <p style="margin:0;">Aktuell keine News vorhanden.</p>
                </div>
            <?php else: ?>
                <?php foreach ($aktuelleNews as $news): ?>
                    <div class="profile-section">
                        <?php if (!empty($news['photo_url'])): ?>
                            <img src="<?= htmlspecialchars($news['photo_url']) ?>" alt="" style="width:100%; height:120px; object-fit:cover; border-radius:10px; margin-bottom:12px;">
                        <?php endif; ?>
                        <h3 style="margin: 0 0 6px 0;"><?= htmlspecialchars($news['title']) ?></h3>
                        <p style="margin:0; font-size:14px; color: var(--bella-on-surface-variant, #584048);">
                            <?= nl2br(htmlspecialchars($news['content'])) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="profile-section" style="background: var(--bella-inverse-surface, #2b2138); color: white; text-align: center; margin-top: 30px;">
            <h3 style="margin: 0 0 6px 0; color: white;">Hilfe benötigt?</h3>
            <p style="margin: 0 0 16px 0; opacity: 0.85; font-size: 14px;">
                Wir sind für dich da, wenn du Fragen zu deiner Buchung hast.
            </p>
            <button
                type="button"
                onclick="if (window.Tawk_API) { Tawk_API.maximize(); } else { alert('Chat wird geladen, bitte kurz warten und erneut klicken.'); }"
                class="btn-primary"
                style="border: none; cursor: pointer;">
                💬 Jetzt chatten
            </button>
        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>