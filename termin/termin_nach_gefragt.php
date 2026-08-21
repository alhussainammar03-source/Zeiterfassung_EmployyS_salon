<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';

Auth::requireAdmin();

$meldung = '';
$fehler = '';
$terminwuensche = [];
$suche = '';
$statusFilter = '';
$datumVon = '';
$datumBis = '';
$sortRichtung = 'ASC';
try {
    $pdo = Database::getInstance()->getConnection();

    $terminwunschRepository = new TerminwunschRepository($pdo);

    $suche = trim($_GET['suche'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    $datumVon = trim($_GET['datum_von'] ?? '');
    $datumBis = trim($_GET['datum_bis'] ?? '');
    $sortRichtung = ($_GET['sort'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    if ($suche !== '' || $statusFilter !== '' || $datumVon !== '' || $datumBis !== '') {
        $terminwuensche = $terminwunschRepository->sucheTerminwuensche(
            $suche,
            $statusFilter,
            $datumVon,
            $datumBis,
            $sortRichtung
        );
    } else {
        $terminwuensche = $terminwunschRepository->getAllTerminwuensche($sortRichtung);
    }
    if (isset($_GET['status_geaendert'])) {
        $meldung = 'Der Status wurde erfolgreich geändert.';
    }

    if (isset($_GET['geloescht'])) {
        $meldung = 'Der Terminwunsch wurde erfolgreich gelöscht.';
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
    <title>Terminwünsche - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/termin_requests_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Terminwünsche</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Verwalten und bestätigen Sie eingehende Kundenanfragen.
                </p>
            </div>
            <div>
                <a href="zeitslots.php" class="tw-new-btn">+ Neuer Slot</a>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <form method="get" class="tw-filter-card">
            <input
                type="search"
                name="suche"
                value="<?= htmlspecialchars($suche) ?>"
                placeholder="🔍 Kunde, Dienstleistung suchen...">

            <input id="datum_von" type="date" name="datum_von" value="<?= htmlspecialchars($datumVon) ?>">
            <span style="color: var(--bella-on-surface-variant); font-size: 13px;">–</span>
            <input id="datum_bis" type="date" name="datum_bis" value="<?= htmlspecialchars($datumBis) ?>">

            <select name="status" onchange="this.form.submit()">
                <option value="">Alle Status</option>
                <option value="angefragt" <?= $statusFilter === 'angefragt' ? 'selected' : '' ?>>Neu (Angefragt)</option>
                <option value="bestaetigt" <?= $statusFilter === 'bestaetigt' ? 'selected' : '' ?>>Bestätigt</option>
                <option value="abgeschlossen" <?= $statusFilter === 'abgeschlossen' ? 'selected' : '' ?>>Abgeschlossen</option>
            </select>

            <select name="sort" onchange="this.form.submit()">
                <option value="ASC" <?= $sortRichtung === 'ASC' ? 'selected' : '' ?>>Datum (Aufsteigend)</option>
                <option value="DESC" <?= $sortRichtung === 'DESC' ? 'selected' : '' ?>>Datum (Absteigend)</option>
            </select>

            <button type="submit">Suchen</button>

            <?php if ($suche !== '' || $statusFilter !== '' || $datumVon !== '' || $datumBis !== ''): ?>
                <a href="termin_nach_gefragt.php">Zurücksetzen</a>
            <?php endif; ?>
        </form>

        <div class="tw-table-wrapper">
            <table class="tw-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kunde</th>
                        <th>Kontakt</th>
                        <th>Mitarbeiter</th>
                        <th>Dienstleistung</th>
                        <th>Zeitpunkt</th>
                        <th>Status</th>
                        <th>Notiz</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($terminwuensche === []): ?>
                        <tr>
                            <td colspan="9">Keine Terminwünsche gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $twAvatarFarben = ['#d63384', '#a92a6c', '#655974', '#22c55e', '#0ea5e9', '#f59e0b'];
                        $twStatusLabels = [
                            'angefragt' => 'Neu',
                            'bestaetigt' => 'Bestätigt',
                            'abgeschlossen' => 'Abgeschlossen',
                            'abgelehnt' => 'Abgelehnt',
                            'storniert' => 'Storniert',
                        ];
                        ?>
                        <?php foreach ($terminwuensche as $terminwunsch): ?>
                            <?php $twFarbe = $twAvatarFarben[(int) $terminwunsch['id'] % count($twAvatarFarben)]; ?>
                            <tr>
                                <td class="tw-id">#REQ-<?= (int) $terminwunsch['id'] ?></td>

                                <td>
                                    <div class="tw-person-row">
                                        <div class="tw-avatar" style="background: <?= $twFarbe ?>;">
                                            <?= htmlspecialchars(mb_strtoupper(mb_substr($terminwunsch['kunden_name'], 0, 1))) ?>
                                        </div>
                                        <span class="tw-name"><?= htmlspecialchars($terminwunsch['kunden_name']) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <span class="tw-contact__email"><?= htmlspecialchars($terminwunsch['kunden_email'] ?? '') ?></span>
                                    <span class="tw-contact__phone">
                                        <?= htmlspecialchars($terminwunsch['kunden_telefon1'] ?? '') ?>
                                        <?php if (!empty($terminwunsch['kunden_telefon2'])): ?>
                                            / <?= htmlspecialchars($terminwunsch['kunden_telefon2']) ?>
                                        <?php endif; ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($terminwunsch['mitarbeiter_name']) ?></td>

                                <td>
                                    <span class="tw-service"><?= htmlspecialchars($terminwunsch['dienstleistung_name']) ?></span>
                                </td>

                                <td>
                                    <span class="tw-time__date"><?= date('d.m.Y', strtotime($terminwunsch['terminwunsche_start'])) ?></span>
                                    <span class="tw-time__range">
                                        <?= date('H:i', strtotime($terminwunsch['terminwunsche_start'])) ?>
                                        - <?= date('H:i', strtotime($terminwunsch['terminwunsche_ende'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="tw-status tw-status--<?= htmlspecialchars($terminwunsch['status']) ?>">
                                        <?= htmlspecialchars($twStatusLabels[$terminwunsch['status']] ?? $terminwunsch['status']) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($terminwunsch['customer_note'] ?? '–') ?></td>

                                <td>
                                    <a href="terminwunsch_details.php?id=<?= (int) $terminwunsch['id'] ?>" class="tw-details-link">
                                        Details →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>