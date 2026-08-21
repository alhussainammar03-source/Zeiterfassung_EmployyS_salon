<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/VacationRepository.php';
require_once __DIR__ . '/../repositories/employeeRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $vacationRepository = new VacationRepository($pdo);
    $employeeRepository = new employeeRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'admin_antrag_erstellen') {
            $mitarbeiterId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
            $start = trim($_POST['start_datum'] ?? '');
            $end = trim($_POST['end_datum'] ?? '');
            $notiz = trim($_POST['notiz'] ?? '');

            if (!$mitarbeiterId || $start === '' || $end === '') {
                $fehler = 'Bitte Mitarbeiter, Start- und End-Datum angeben.';
            } elseif ($start > $end) {
                $fehler = 'Das Start-Datum muss vor dem End-Datum liegen.';
            } else {
                $anzahlTage = VacationRepository::berechneWerktage($start, $end);

                if ($anzahlTage <= 0) {
                    $fehler = 'Der gewählte Zeitraum enthält keine Werktage.';
                } else {
                    $vacationRepository->beantragen(
                        $mitarbeiterId,
                        $start,
                        $end,
                        $anzahlTage,
                        $notiz !== '' ? $notiz : 'Vom Admin erfasst'
                    );
                    header('Location: vacation_requests.php?admin_erstellt=1');
                    exit;
                }
            }
        } else {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $neuerStatus = $_POST['status'] ?? '';
            $adminKommentar = trim($_POST['admin_kommentar'] ?? '');

            if ($id && in_array($neuerStatus, ['genehmigt', 'abgelehnt'], true)) {
                $vacationRepository->changeStatus($id, $neuerStatus, $adminKommentar !== '' ? $adminKommentar : null);
            }

            header('Location: vacation_requests.php?status_geaendert=1');
            exit;
        }
    }

    $statusFilter = $_GET['status'] ?? 'alle';
    $suche = trim($_GET['suche'] ?? '');

    $antraege = $vacationRepository->getAllRequests();

    if (in_array($statusFilter, ['beantragt', 'genehmigt', 'abgelehnt'], true)) {
        $antraege = array_values(array_filter(
            $antraege,
            fn($a) => $a['status'] === $statusFilter
        ));
    }

    if ($suche !== '') {
        $antraege = array_values(array_filter(
            $antraege,
            fn($a) => stripos($a['mitarbeiter_name'], $suche) !== false
        ));
    }

    $aktiveMitarbeiter = $employeeRepository->getAllActiveMitarbeiterMitSollstunden();

    if (isset($_GET['status_geaendert'])) {
        $meldung = 'Der Status wurde erfolgreich geändert.';
    }

    if (isset($_GET['admin_erstellt'])) {
        $meldung = 'Der Urlaubsantrag wurde erfasst.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $antraege = [];
    $aktiveMitarbeiter = [];
    $statusFilter = 'alle';
    $suche = '';
}

function urlaubStatusLabel(string $status): string
{
    return match ($status) {
        'beantragt' => 'Beantragt',
        'genehmigt' => 'Genehmigt',
        'abgelehnt' => 'Abgelehnt',
        default => $status,
    };
}

function urlaubStatusKlasse(string $status): string
{
    return match ($status) {
        'genehmigt' => 'aktiv',
        default => 'inaktiv',
    };
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urlaubsanträge - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/vacation_requests_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Mitarbeiter Urlaubsverwaltung</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Übersicht und Bearbeitung aller eingehenden Urlaubsanträge.
                </p>
            </div>
            <div>
                <button type="button" class="vra-new-btn" onclick="document.getElementById('new-request-modal').classList.add('is-open')">
                    + Neuer Antrag (Admin)
                </button>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <form method="get" class="vra-toolbar">
            <input type="search" name="suche" value="<?= htmlspecialchars($suche) ?>" placeholder="🔍 Mitarbeiter suchen..." onchange="this.form.submit()">

            <div class="vra-tabs">
                <a href="?status=alle<?= $suche !== '' ? '&suche=' . urlencode($suche) : '' ?>" class="vra-tab <?= $statusFilter === 'alle' ? 'is-active' : '' ?>">Alle</a>
                <a href="?status=beantragt<?= $suche !== '' ? '&suche=' . urlencode($suche) : '' ?>" class="vra-tab <?= $statusFilter === 'beantragt' ? 'is-active' : '' ?>">Beantragt</a>
                <a href="?status=genehmigt<?= $suche !== '' ? '&suche=' . urlencode($suche) : '' ?>" class="vra-tab <?= $statusFilter === 'genehmigt' ? 'is-active' : '' ?>">Genehmigt</a>
                <a href="?status=abgelehnt<?= $suche !== '' ? '&suche=' . urlencode($suche) : '' ?>" class="vra-tab <?= $statusFilter === 'abgelehnt' ? 'is-active' : '' ?>">Abgelehnt</a>
            </div>
        </form>

        <div class="vra-table-wrapper">
            <table class="vra-table">
                <thead>
                    <tr>
                        <th>Mitarbeiter</th>
                        <th>Von</th>
                        <th>Bis</th>
                        <th>Tage</th>
                        <th>Bemerkung</th>
                        <th>Status</th>
                        <th>Beantragt am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($antraege === []): ?>
                        <tr>
                            <td colspan="8">Keine Urlaubsanträge gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $vraAvatarFarben = ['#d63384', '#a92a6c', '#655974', '#22c55e', '#0ea5e9', '#f59e0b'];
                        ?>
                        <?php foreach ($antraege as $antrag): ?>
                            <?php $vraFarbe = $vraAvatarFarben[((int) $antrag['id']) % count($vraAvatarFarben)]; ?>
                            <tr>
                                <td>
                                    <div class="vra-person-row">
                                        <div class="vra-avatar" style="background: <?= $vraFarbe ?>;">
                                            <?= htmlspecialchars(mb_strtoupper(mb_substr($antrag['mitarbeiter_name'], 0, 1))) ?>
                                        </div>
                                        <span class="vra-name"><?= htmlspecialchars($antrag['mitarbeiter_name']) ?></span>
                                    </div>
                                </td>
                                <td><?= date('d.m.Y', strtotime($antrag['start_datum'])) ?></td>
                                <td><?= date('d.m.Y', strtotime($antrag['end_datum'])) ?></td>
                                <td><?= $antrag['anzahl_tage'] ?></td>
                                <td><?= htmlspecialchars($antrag['mitarbeiter_notiz'] ?? '–') ?></td>
                                <td>
                                    <span class="vra-status vra-status--<?= $antrag['status'] ?>">
                                        <?= urlaubStatusLabel($antrag['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('d.m.Y', strtotime($antrag['erstellt_am'])) ?></td>
                                <td class="vra-actions">
                                    <?php if ($antrag['status'] === 'beantragt'): ?>
                                        <button type="button" class="vra-icon-btn vra-icon-btn--approve" onclick="document.getElementById('panel-<?= (int) $antrag['id'] ?>').classList.toggle('is-open')" title="Bearbeiten">✓</button>

                                        <div class="vra-details-panel" id="panel-<?= (int) $antrag['id'] ?>">
                                            <form method="post">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $antrag['id'] ?>">
                                                <label>Kommentar (optional)</label>
                                                <textarea name="admin_kommentar" rows="2" placeholder="z.B. Grund der Ablehnung"></textarea>
                                                <div class="form-actions">
                                                    <button type="submit" name="status" value="genehmigt">Genehmigen</button>
                                                    <button type="submit" name="status" value="abgelehnt" class="delete-button">Ablehnen</button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="vra-icon-btn" onclick="document.getElementById('view-<?= (int) $antrag['id'] ?>').classList.toggle('is-open')" title="Kommentar ansehen">👁️</button>

                                        <div class="vra-details-panel" id="view-<?= (int) $antrag['id'] ?>">
                                            <p style="margin:0; font-size:13px;">
                                                <?= htmlspecialchars($antrag['admin_kommentar'] ?? 'Kein Kommentar hinterlegt.') ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <!-- Modal: Neuen Antrag als Admin erfassen -->
    <div class="vra-modal-overlay" id="new-request-modal">
        <div class="vra-modal">
            <h3>Neuen Urlaubsantrag erfassen</h3>

            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="admin_antrag_erstellen">

                <label for="employee_id">Mitarbeiter</label>
                <select id="employee_id" name="employee_id" required>
                    <option value="">Bitte wählen</option>
                    <?php foreach ($aktiveMitarbeiter as $ma): ?>
                        <option value="<?= (int) $ma['id'] ?>"><?= htmlspecialchars($ma['vor_name'] . ' ' . $ma['nach_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="modal_start">Von</label>
                <input id="modal_start" type="date" name="start_datum" required>

                <label for="modal_end">Bis</label>
                <input id="modal_end" type="date" name="end_datum" required>

                <label for="modal_notiz">Bemerkung (optional)</label>
                <textarea id="modal_notiz" name="notiz" rows="2" placeholder="z.B. telefonisch abgestimmt"></textarea>

                <div class="vra-modal-actions">
                    <button type="button" class="vra-icon-btn" onclick="document.getElementById('new-request-modal').classList.remove('is-open')" style="width:auto; padding:9px 16px;">Abbrechen</button>
                    <button type="submit" class="vra-new-btn">Antrag erstellen</button>
                </div>
            </form>
        </div>
    </div>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>