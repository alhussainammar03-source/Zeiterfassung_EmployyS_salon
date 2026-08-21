<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/SickLeaveRepository.php';

Auth::requireAdmin();

$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $sickLeaveRepository = new SickLeaveRepository($pdo);

    $meldungen = $sickLeaveRepository->getAllForAdmin();
    $sickLeaveRepository->alleAlsGelesenMarkieren();
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $meldungen = [];
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Krankmeldungen - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/sick_leaves_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Krankmeldungen</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Ausfälle deiner Mitarbeiter im Überblick.
                </p>
            </div>
        </div>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="sla-list">
            <?php if ($meldungen === []): ?>
                <div class="sla-empty">Keine Krankmeldungen vorhanden.</div>
            <?php else: ?>
                <?php
                $slaAvatarFarben = ['#d63384', '#a92a6c', '#655974', '#22c55e', '#0ea5e9', '#f59e0b'];
                $slaIndex = 0;
                ?>
                <?php foreach ($meldungen as $meldung): ?>
                    <?php $slaFarbe = $slaAvatarFarben[$slaIndex % count($slaAvatarFarben)];
                    $slaIndex++; ?>
                    <div class="sla-row">
                        <div class="sla-avatar" style="background: <?= $slaFarbe ?>;">
                            <?= htmlspecialchars(mb_strtoupper(mb_substr($meldung['mitarbeiter_name'], 0, 1))) ?>
                        </div>

                        <div class="sla-name-col">
                            <strong>
                                <?= htmlspecialchars($meldung['mitarbeiter_name']) ?>
                                <?php if (!$meldung['admin_gelesen']): ?>
                                    <span class="sla-new-badge">Neu</span>
                                <?php endif; ?>
                            </strong>
                            <span style="font-size:11px; color: var(--bella-on-surface-variant);">
                                Gemeldet am <?= date('d.m.Y H:i', strtotime($meldung['erstellt_am'])) ?>
                            </span>
                        </div>

                        <div class="sla-period-col">
                            <?= date('d.m.Y', strtotime($meldung['start_datum'])) ?>
                            <?php if ($meldung['end_datum'] !== null): ?>
                                – <?= date('d.m.Y', strtotime($meldung['end_datum'])) ?>
                            <?php endif; ?>
                            <span>Zeitraum</span>
                        </div>

                        <div class="sla-file-col">
                            <?php if (!empty($meldung['au_datei_url'])): ?>
                                <a href="<?= htmlspecialchars($meldung['au_datei_url']) ?>" target="_blank" rel="noopener" class="sla-file-link">
                                    📎 Attest ansehen
                                </a>
                            <?php else: ?>
                                <span class="sla-no-file">Kein Attest hochgeladen</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>