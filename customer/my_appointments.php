<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireRole('kunde');

$fehler = '';
$meldung = '';
$bevorstehend = [];
$vergangen = [];
$storniert = [];
$naechsterTermin = null;
$zuletztStorniert = null;

try {
    $pdo = Database::getInstance()->getConnection();
    $terminwunschRepository = new TerminwunschRepository($pdo);

    $alleTermine = $terminwunschRepository->getByCustomerId(
        (int) $_SESSION['user_id']
    );

    $jetzt = new DateTime();

    foreach ($alleTermine as $termin) {
        $start = new DateTime($termin['terminwunsche_start']);

        if ($termin['status'] === 'storniert') {
            $storniert[] = $termin;
        } elseif ($start >= $jetzt && in_array($termin['status'], ['angefragt', 'bestaetigt'], true)) {
            $bevorstehend[] = $termin;
        } else {
            $vergangen[] = $termin;
        }
    }

    usort($bevorstehend, fn($a, $b) => strcmp($a['terminwunsche_start'], $b['terminwunsche_start']));

    if ($bevorstehend !== []) {
        $naechsterTermin = $bevorstehend[0];
    }

    if ($storniert !== []) {
        $zuletztStorniert = $storniert[0]; // bereits DESC sortiert aus dem Repository
    }

    if (isset($_GET['storniert'])) {
        $meldung = 'Dein Termin wurde storniert.';
    }

    if (isset($_GET['fehler'])) {
        $fehler = match ($_GET['fehler']) {
            'nicht_gefunden' => 'Dieser Termin wurde nicht gefunden.',
            'nicht_stornierbar' => 'Dieser Termin kann nicht mehr storniert werden.',
            default => 'Es ist ein Fehler aufgetreten.',
        };
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
}

function apptDayLabel(string $datetime): string
{
    $datum = new DateTime($datetime);
    $heute = new DateTime('today');
    $morgen = (clone $heute)->modify('+1 day');

    if ($datum->format('Y-m-d') === $heute->format('Y-m-d')) {
        return 'Heute';
    }
    if ($datum->format('Y-m-d') === $morgen->format('Y-m-d')) {
        return 'Morgen';
    }

    $wochentage = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    return $wochentage[(int) $datum->format('N') - 1] . ', ' . $datum->format('d.m.Y');
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meine Termine - Bella Beauty</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/button.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/my_appointments.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Meine Termine</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Verwalte deine Schönheitsbehandlungen und sieh dir deine Termin-Historie an.
                </p>
            </div>

            <div>
                <a href="book_appointment.php" class="btn-primary">Neuen Termin buchen</a>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="appt-success-banner" id="success-banner">
                <div class="appt-success-banner__left">
                    <span class="appt-success-banner__icon">✓</span>
                    <span class="appt-success-banner__text"><?= htmlspecialchars($meldung) ?></span>
                </div>
                <button type="button" class="appt-success-banner__close" onclick="document.getElementById('success-banner').style.display='none'">✕</button>
            </div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <div class="appt-tabs">
            <button type="button" class="appt-tab is-active" data-tab="bevorstehend">
                Anstehend (<?= count($bevorstehend) ?>)
            </button>
            <button type="button" class="appt-tab" data-tab="vergangen">
                Vergangene (<?= count($vergangen) ?>)
            </button>
            <button type="button" class="appt-tab" data-tab="storniert">
                Storniert (<?= count($storniert) ?>)
            </button>
        </div>

        <div class="appt-grid">

            <div class="appt-grid__main">

                <!-- Anstehend -->
                <div class="appt-tab-panel" data-panel="bevorstehend">
                    <?php if ($bevorstehend === []): ?>
                        <p class="appt-empty">Keine bevorstehenden Termine. <a href="book_appointment.php">Jetzt buchen →</a></p>
                    <?php else: ?>
                        <?php foreach ($bevorstehend as $termin): ?>
                            <div class="appt-card" style="margin-bottom: 20px;">
                                <?php if (!empty($termin['dienstleistung_foto'])): ?>
                                    <div class="appt-card__image" style="background-image: url('<?= htmlspecialchars($termin['dienstleistung_foto']) ?>')"></div>
                                <?php else: ?>
                                    <div class="appt-card__image appt-card__image--placeholder">💇</div>
                                <?php endif; ?>

                                <div class="appt-card__body">
                                    <div>
                                        <div class="appt-card__top">
                                            <span class="appt-card__tag"><?= htmlspecialchars($termin['dienstleistung_name']) ?></span>
                                            <span class="appt-card__duration">🕒 <?= (int) $termin['dienstleistung_dauer'] ?> Min</span>
                                        </div>
                                        <h3><?= htmlspecialchars($termin['dienstleistung_name']) ?></h3>
                                        <p class="appt-card__stylist">Mit <?= htmlspecialchars($termin['mitarbeiter_name']) ?></p>

                                        <div class="appt-card__meta">
                                            <span>📅 <?= apptDayLabel($termin['terminwunsche_start']) ?></span>
                                            <span>⏰ <?= date('H:i', strtotime($termin['terminwunsche_start'])) ?> Uhr</span>
                                        </div>
                                    </div>

                                    <div class="appt-card__actions">
                                        <button
                                            type="button"
                                            class="btn-outline"
                                            style="border: 1.5px solid var(--bella-primary); cursor:pointer; background:none;"
                                            onclick="openCancelModal(<?= (int) $termin['id'] ?>, '<?= htmlspecialchars(addslashes($termin['dienstleistung_name'])) ?>', '<?= apptDayLabel($termin['terminwunsche_start']) ?>')">
                                            Stornieren
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Vergangen -->
                <div class="appt-tab-panel" data-panel="vergangen" style="display:none;">
                    <?php if ($vergangen === []): ?>
                        <p class="appt-empty">Keine vergangenen Termine.</p>
                    <?php else: ?>
                        <?php foreach ($vergangen as $termin): ?>
                            <div class="appt-card appt-card--dimmed" style="margin-bottom: 20px;">
                                <?php if (!empty($termin['dienstleistung_foto'])): ?>
                                    <div class="appt-card__image" style="background-image: url('<?= htmlspecialchars($termin['dienstleistung_foto']) ?>')"></div>
                                <?php else: ?>
                                    <div class="appt-card__image appt-card__image--placeholder">💇</div>
                                <?php endif; ?>
                                <div class="appt-card__body">
                                    <div>
                                        <span class="appt-card__tag"><?= htmlspecialchars($termin['dienstleistung_name']) ?></span>
                                        <h3><?= htmlspecialchars($termin['dienstleistung_name']) ?></h3>
                                        <p class="appt-card__stylist">Mit <?= htmlspecialchars($termin['mitarbeiter_name']) ?></p>
                                        <div class="appt-card__meta">
                                            <span>📅 <?= date('d.m.Y', strtotime($termin['terminwunsche_start'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Storniert -->
                <div class="appt-tab-panel" data-panel="storniert" style="display:none;">
                    <?php if ($storniert === []): ?>
                        <p class="appt-empty">Keine stornierten Termine.</p>
                    <?php else: ?>
                        <?php foreach ($storniert as $termin): ?>
                            <div class="appt-card appt-card--dimmed" style="margin-bottom: 20px;">
                                <?php if (!empty($termin['dienstleistung_foto'])): ?>
                                    <div class="appt-card__image" style="background-image: url('<?= htmlspecialchars($termin['dienstleistung_foto']) ?>')"></div>
                                <?php else: ?>
                                    <div class="appt-card__image appt-card__image--placeholder">💇</div>
                                <?php endif; ?>
                                <div class="appt-card__body">
                                    <div>
                                        <span class="appt-card__tag"><?= htmlspecialchars($termin['dienstleistung_name']) ?></span>
                                        <h3><?= htmlspecialchars($termin['dienstleistung_name']) ?></h3>
                                        <p class="appt-card__stylist">Mit <?= htmlspecialchars($termin['mitarbeiter_name']) ?></p>
                                        <div class="appt-card__meta">
                                            <span>📅 War geplant: <?= date('d.m.Y', strtotime($termin['terminwunsche_start'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <div class="appt-grid__side">

                <?php if ($naechsterTermin !== null): ?>
                    <div class="appt-next-card">
                        <h4>Nächster Termin</h4>
                        <p class="appt-next-card__day"><?= apptDayLabel($naechsterTermin['terminwunsche_start']) ?></p>
                        <p class="appt-next-card__time"><?= date('H:i', strtotime($naechsterTermin['terminwunsche_start'])) ?> Uhr</p>
                        <p class="appt-next-card__note">Wir freuen uns auf dich! Bitte erscheine 5 Minuten früher.</p>
                    </div>
                <?php endif; ?>

                <?php if ($zuletztStorniert !== null): ?>
                    <div class="appt-side-card">
                        <div class="appt-side-card__header">
                            Zuletzt Storniert
                            <span class="appt-side-card__badge">STORNIERT</span>
                        </div>
                        <div class="appt-side-card__row">
                            <div class="appt-side-card__icon">💇</div>
                            <div>
                                <p><?= htmlspecialchars($zuletztStorniert['dienstleistung_name']) ?></p>
                                <p>War geplant: <?= date('d.m.Y', strtotime($zuletztStorniert['terminwunsche_start'])) ?></p>
                            </div>
                        </div>
                        <a href="book_appointment.php" style="text-decoration:none;">
                            <button type="button" class="appt-side-card__btn">Neu buchen</button>
                        </a>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </main>

    <!-- Storno-Bestätigungs-Modal -->
    <div class="appt-modal-overlay" id="cancel-modal">
        <div class="appt-modal">
            <h3>Termin stornieren?</h3>
            <p id="cancel-modal-text">Bist du sicher, dass du diesen Termin stornieren möchtest? Diese Aktion kann nicht rückgängig gemacht werden.</p>

            <form method="post" action="cancel_appointment.php" id="cancel-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" id="cancel-form-id" value="">

                <div class="appt-modal-actions">
                    <button type="button" class="appt-modal-cancel" onclick="closeCancelModal()">Abbrechen</button>
                    <button type="submit" class="appt-modal-confirm">Ja, Stornieren</button>
                </div>
            </form>
        </div>
    </div>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function openCancelModal(id, serviceName, tagLabel) {
            document.getElementById('cancel-form-id').value = id;
            document.getElementById('cancel-modal-text').textContent =
                'Bist du sicher, dass du deinen Termin für "' + serviceName + '" (' + tagLabel + ') stornieren möchtest? Diese Aktion kann nicht rückgängig gemacht werden.';
            document.getElementById('cancel-modal').classList.add('is-open');
        }

        function closeCancelModal() {
            document.getElementById('cancel-modal').classList.remove('is-open');
        }

        // Tabs
        document.querySelectorAll('.appt-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.appt-tab').forEach(function(t) {
                    t.classList.remove('is-active');
                });
                tab.classList.add('is-active');

                var ziel = tab.getAttribute('data-tab');
                document.querySelectorAll('.appt-tab-panel').forEach(function(panel) {
                    panel.style.display = panel.getAttribute('data-panel') === ziel ? '' : 'none';
                });
            });
        });

        // Erfolgs-Banner nach 10 Sekunden automatisch ausblenden
        var banner = document.getElementById('success-banner');
        if (banner) {
            setTimeout(function() {
                banner.style.transition = 'opacity 0.5s ease-out';
                banner.style.opacity = '0';
                setTimeout(function() {
                    banner.style.display = 'none';
                }, 500);
            }, 10000);
        }
    </script>

</body>

</html>