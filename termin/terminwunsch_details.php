<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$meldung = '';
$fehler = '';
$terminwunsch = false;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: termin_nach_gefragt.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();

    $terminwunschRepository = new TerminwunschRepository($pdo);

    $terminwunsch = $terminwunschRepository->getTerminwunschById($id);

    if ($terminwunsch === false) {
        $fehler = 'Der Terminwunsch wurde nicht gefunden.';
    }

    if (isset($_GET['status_geaendert'])) {
        $meldung = 'Der Status wurde erfolgreich geändert.';
    }

    if (isset($_GET['fehler'])) {
        $fehler = match ($_GET['fehler']) {
            'status' => 'Der Status konnte nicht geändert werden.',
            'datenbank' => 'Es ist ein Datenbankfehler aufgetreten.',
            default => 'Es ist ein unbekannter Fehler aufgetreten.',
        };
    }
} catch (Throwable $exception) {
    $fehler = $exception->getMessage();
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminwunsch Details - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/termin_details_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Terminwunsch Details</h1>
                <a href="termin_nach_gefragt.php">← Zurück zur Übersicht</a>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <?php if ($terminwunsch !== false): ?>

            <?php
            $twdStatusLabels = [
                'angefragt' => 'Angefragt',
                'bestaetigt' => 'Bestätigt',
                'abgeschlossen' => 'Abgeschlossen',
                'abgelehnt' => 'Abgelehnt',
                'storniert' => 'Storniert',
            ];
            ?>

            <div class="twd-layout">

                <div class="twd-card">
                    <span class="twd-current-status twd-current-status--<?= htmlspecialchars($terminwunsch['status']) ?>">
                        <?= htmlspecialchars($twdStatusLabels[$terminwunsch['status']] ?? $terminwunsch['status']) ?>
                    </span>

                    <h2>#REQ-<?= (int) $terminwunsch['id'] ?></h2>

                    <div class="twd-info-row">
                        <span>Kunde</span>
                        <span><?= htmlspecialchars($terminwunsch['kunden_name']) ?></span>
                    </div>
                    <div class="twd-info-row">
                        <span>E-Mail</span>
                        <span><?= htmlspecialchars($terminwunsch['kunden_email']) ?></span>
                    </div>
                    <div class="twd-info-row">
                        <span>Mitarbeiter</span>
                        <span><?= htmlspecialchars($terminwunsch['mitarbeiter_name']) ?></span>
                    </div>
                    <div class="twd-info-row">
                        <span>Dienstleistung</span>
                        <span><?= htmlspecialchars($terminwunsch['dienstleistung_name']) ?></span>
                    </div>
                    <div class="twd-info-row">
                        <span>Start</span>
                        <span><?= date('d.m.Y H:i', strtotime($terminwunsch['terminwunsche_start'])) ?> Uhr</span>
                    </div>
                    <div class="twd-info-row">
                        <span>Ende</span>
                        <span><?= date('d.m.Y H:i', strtotime($terminwunsch['terminwunsche_ende'])) ?> Uhr</span>
                    </div>
                    <div class="twd-info-row">
                        <span>Erstellt am</span>
                        <span><?= date('d.m.Y H:i', strtotime($terminwunsch['created_at'])) ?> Uhr</span>
                    </div>

                    <?php if (!empty($terminwunsch['customer_note'])): ?>
                        <div class="twd-note-box">
                            <strong>Kundennotiz:</strong><br>
                            <?= nl2br(htmlspecialchars($terminwunsch['customer_note'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="twd-card">
                    <h2>Status ändern</h2>

                    <form method="post" action="terminwunsch_status.php" class="twd-status-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $terminwunsch['id'] ?>">

                        <label for="status">Neuer Status</label>
                        <select id="status" name="status" required>
                            <option value="angefragt" <?= $terminwunsch['status'] === 'angefragt' ? 'selected' : '' ?>>Angefragt</option>
                            <option value="bestaetigt" <?= $terminwunsch['status'] === 'bestaetigt' ? 'selected' : '' ?>>Bestätigt</option>
                            <option value="abgelehnt" <?= $terminwunsch['status'] === 'abgelehnt' ? 'selected' : '' ?>>Abgelehnt</option>
                            <option value="abgeschlossen" <?= $terminwunsch['status'] === 'abgeschlossen' ? 'selected' : '' ?>>Abgeschlossen</option>
                        </select>

                        <button type="submit">Status speichern</button>
                    </form>
                </div>

            </div>

        <?php endif; ?>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>