<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/TerminSlotRepository.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$meldung = '';
$fehler = '';
$slots = [];
$warnungSlot = null;
$betroffeneTerminwuensche = [];

try {
    $pdo = Database::getInstance()->getConnection();
    $terminSlotRepository = new TerminSlotRepository($pdo);
    $terminwunschRepository = new TerminwunschRepository($pdo);
    $employeeRepository = new employeeRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'vorschlagen') {
            $datum = trim($_POST['datum'] ?? '');
            $uhrzeit = trim($_POST['uhrzeit'] ?? '');
            $dauer = (int) ($_POST['dauer_minuten'] ?? 30);

            if ($datum === '' || $uhrzeit === '' || $dauer <= 0) {
                $fehler = 'Bitte Datum, Uhrzeit und eine gültige Dauer angeben.';
            } else {
                $erfolgreich = $terminSlotRepository->proposeSlot(
                    $datum,
                    $uhrzeit,
                    $dauer
                );

                if ($erfolgreich) {
                    header('Location: zeitslots.php?vorgeschlagen=1');
                    exit;
                }

                $fehler = 'Für dieses Datum/Uhrzeit existiert bereits ein Termin-Slot.';
            }
        } elseif (($_POST['action'] ?? '') === 'stornieren') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id) {
                $slot = $terminSlotRepository->getSlotById($id);

                if ($slot) {
                    $slotStart = new DateTime(
                        $slot['datum'] . ' ' . $slot['uhrzeit']
                    );
                    $slotEnd = clone $slotStart;
                    $slotEnd->modify(
                        '+' . (int) $slot['dauer_minuten'] . ' minutes'
                    );

                    $betroffeneTerminwuensche = $terminwunschRepository->findByZeitraum(
                        $slotStart->format('Y-m-d H:i:s'),
                        $slotEnd->format('Y-m-d H:i:s')
                    );

                    if ($betroffeneTerminwuensche === []) {
                        // Keine Kundenanfrage/Bestätigung zu diesem Slot -> direkt löschen
                        $terminSlotRepository->cancelSlot($id);
                        header('Location: zeitslots.php?storniert=1');
                        exit;
                    }

                    // Es gibt Terminwünsche zu diesem Slot -> erst warnen,
                    // noch NICHT löschen. Wird unten im HTML angezeigt.
                    $warnungSlot = $slot;
                }
            }
        } elseif (($_POST['action'] ?? '') === 'stornieren_erzwingen') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id) {
                $terminSlotRepository->cancelSlot($id);
            }

            header('Location: zeitslots.php?storniert=1');
            exit;
        } elseif (($_POST['action'] ?? '') === 'zeitraum_stornieren') {
            $datumVon = trim($_POST['loeschen_von'] ?? '');
            $datumBis = trim($_POST['loeschen_bis'] ?? '');

            if ($datumVon === '' || $datumBis === '') {
                $fehler = 'Bitte Start- und End-Datum auswählen.';
            } elseif ($datumVon > $datumBis) {
                $fehler = 'Das Start-Datum muss vor dem End-Datum liegen.';
            } else {
                $geloescht = 0;
                $uebersprungen = 0;

                $laufendesDatum = new DateTime($datumVon);
                $endDatum = new DateTime($datumBis);

                while ($laufendesDatum <= $endDatum) {
                    $tagesSlots = $terminSlotRepository->getSlotsByDatum(
                        $laufendesDatum->format('Y-m-d')
                    );

                    foreach ($tagesSlots as $tagesSlot) {
                        $slotStart = new DateTime(
                            $tagesSlot['datum'] . ' ' . $tagesSlot['uhrzeit']
                        );
                        $slotEnd = clone $slotStart;
                        $slotEnd->modify(
                            '+' . (int) $tagesSlot['dauer_minuten'] . ' minutes'
                        );

                        $betroffene = $terminwunschRepository->findByZeitraum(
                            $slotStart->format('Y-m-d H:i:s'),
                            $slotEnd->format('Y-m-d H:i:s')
                        );

                        if ($betroffene === []) {
                            $terminSlotRepository->cancelSlot($tagesSlot['id']);
                            $geloescht++;
                        } else {
                            $uebersprungen++;
                        }
                    }

                    $laufendesDatum->modify('+1 day');
                }

                header(
                    'Location: zeitslots.php?tag_storniert=1'
                        . '&geloescht=' . $geloescht
                        . '&tag_uebersprungen=' . $uebersprungen
                );
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'bearbeiten') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $datum = trim($_POST['datum'] ?? '');
            $uhrzeit = trim($_POST['uhrzeit'] ?? '');
            $dauer = (int) ($_POST['dauer_minuten'] ?? 0);

            if (!$id || $datum === '' || $uhrzeit === '' || $dauer <= 0) {
                $fehler = 'Bitte Datum, Uhrzeit und eine gültige Dauer angeben.';
            } else {
                $terminSlotRepository->updateSlot($id, $datum, $uhrzeit, $dauer);
                header('Location: zeitslots.php?bearbeitet=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'tag_vorschlagen') {
            $datum = trim($_POST['tag_datum'] ?? '');
            $startZeit = trim($_POST['tag_start'] ?? '');
            $endZeit = trim($_POST['tag_ende'] ?? '');
            $dauer = (int) ($_POST['tag_dauer'] ?? 30);

            if ($datum === '' || $startZeit === '' || $endZeit === '' || $dauer <= 0) {
                $fehler = 'Bitte Datum, Start-/Endzeit und eine gültige Dauer angeben.';
            } else {
                $ergebnis = $terminSlotRepository->proposeDay(
                    $datum,
                    $startZeit,
                    $endZeit,
                    $dauer
                );

                header(
                    'Location: zeitslots.php?tag_vorgeschlagen=1'
                        . '&erstellt=' . $ergebnis['erstellt']
                        . '&uebersprungen=' . $ergebnis['uebersprungen']
                );
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'woche_vorschlagen') {
            $startDatum = trim($_POST['woche_start'] ?? '');
            $startZeit = trim($_POST['woche_zeit_start'] ?? '');
            $endZeit = trim($_POST['woche_zeit_ende'] ?? '');
            $dauer = (int) ($_POST['woche_dauer'] ?? 30);
            $wochentage = array_map(
                'intval',
                $_POST['wochentage'] ?? []
            );

            if (
                $startDatum === '' ||
                $startZeit === '' ||
                $endZeit === '' ||
                $dauer <= 0 ||
                $wochentage === []
            ) {
                $fehler = 'Bitte Start-Datum, Zeiten, Dauer und mindestens '
                    . 'einen Wochentag angeben.';
            } else {
                $ergebnis = $terminSlotRepository->proposeWeek(
                    $startDatum,
                    $wochentage,
                    $startZeit,
                    $endZeit,
                    $dauer
                );

                header(
                    'Location: zeitslots.php?woche_vorgeschlagen=1'
                        . '&erstellt=' . $ergebnis['erstellt']
                        . '&uebersprungen=' . $ergebnis['uebersprungen']
                );
                exit;
            }
        }
    }

    if (isset($_GET['vorgeschlagen'])) {
        $meldung = 'Der Termin-Slot wurde erfolgreich vorgeschlagen.';
    }

    if (isset($_GET['storniert'])) {
        $meldung = 'Der Termin-Slot wurde storniert.';
    }

    if (isset($_GET['bearbeitet'])) {
        $meldung = 'Der Termin-Slot wurde aktualisiert.';
    }

    if (isset($_GET['tag_vorgeschlagen'])) {
        $erstellt = (int) ($_GET['erstellt'] ?? 0);
        $uebersprungen = (int) ($_GET['uebersprungen'] ?? 0);
        $meldung = $erstellt . ' Termin-Slot(s) für den Tag erstellt.';

        if ($uebersprungen > 0) {
            $meldung .= ' (' . $uebersprungen . ' übersprungen, da bereits vorhanden.)';
        }
    }

    if (isset($_GET['woche_vorgeschlagen'])) {
        $erstellt = (int) ($_GET['erstellt'] ?? 0);
        $uebersprungen = (int) ($_GET['uebersprungen'] ?? 0);
        $meldung = $erstellt . ' Termin-Slot(s) für die Woche erstellt.';

        if ($uebersprungen > 0) {
            $meldung .= ' (' . $uebersprungen . ' übersprungen, da bereits vorhanden.)';
        }
    }

    if (isset($_GET['tag_storniert'])) {
        $geloescht = (int) ($_GET['geloescht'] ?? 0);
        $tagUebersprungen = (int) ($_GET['tag_uebersprungen'] ?? 0);
        $meldung = $geloescht . ' Termin-Slot(s) im gewählten Zeitraum storniert.';

        if ($tagUebersprungen > 0) {
            $meldung .= ' (' . $tagUebersprungen . ' übersprungen, da bereits '
                . 'Terminwünsche dazu existieren – diese bitte einzeln prüfen.)';
        }
    }

    $slots = $terminSlotRepository->getAllSlots();

    $aktiveMitarbeiterAnzahl = $employeeRepository->countActiveEmployees();

    foreach ($slots as $index => $slot) {
        $slotStart = new DateTime($slot['datum'] . ' ' . $slot['uhrzeit']);
        $slotEnd = clone $slotStart;
        $slotEnd->modify('+' . (int) $slot['dauer_minuten'] . ' minutes');

        $auslastung = $terminwunschRepository->getAuslastung(
            $slotStart->format('Y-m-d H:i:s'),
            $slotEnd->format('Y-m-d H:i:s')
        );

        if ($aktiveMitarbeiterAnzahl > 0 && $auslastung['bestaetigt'] >= $aktiveMitarbeiterAnzahl) {
            $slots[$index]['echter_status'] = 'gebucht';
        } elseif ($auslastung['angefragt'] > 0 || $auslastung['bestaetigt'] > 0) {
            $slots[$index]['echter_status'] = 'angefragt';
        } else {
            $slots[$index]['echter_status'] = 'frei';
        }
    }

    $statusFilterSlots = $_GET['status_filter'] ?? 'alle';

    if (in_array($statusFilterSlots, ['frei', 'angefragt', 'gebucht'], true)) {
        $slots = array_values(array_filter(
            $slots,
            fn($s) => $s['echter_status'] === $statusFilterSlots
        ));
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termin-Slots verwalten - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/zeitslots_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Termin-Slots Verwalten</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Erstellen und verwalten Sie die verfügbaren Buchungszeiten für Ihre Kunden.
                </p>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <?php if ($warnungSlot !== null): ?>
            <div class="message error">
                <p>
                    <strong>Achtung:</strong> Für den Termin-Slot am
                    <?= date('d.m.Y', strtotime($warnungSlot['datum'])) ?>
                    um <?= date('H:i', strtotime($warnungSlot['uhrzeit'])) ?> Uhr
                    gibt es bereits <?= count($betroffeneTerminwuensche) ?>
                    Terminwunsch/Terminwünsche:
                </p>

                <ul>
                    <?php foreach ($betroffeneTerminwuensche as $tw): ?>
                        <li>
                            <?= htmlspecialchars($tw['kunden_name']) ?>
                            bei <?= htmlspecialchars($tw['mitarbeiter_name']) ?>
                            –
                            <strong>
                                <?= $tw['status'] === 'bestaetigt' ? 'Bestätigt' : 'Angefragt' ?>
                            </strong>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p>
                    Der Termin-Slot wird dadurch NICHT automatisch storniert –
                    das musst du bei den betroffenen Terminwünschen separat
                    unter "Terminwünsche" erledigen.
                </p>

                <form method="post" style="display:inline;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="stornieren_erzwingen">
                    <input type="hidden" name="id" value="<?= (int) $warnungSlot['id'] ?>">

                    <button
                        class="delete-button"
                        type="submit"
                        onclick="return confirm('Termin-Slot trotzdem stornieren? Die Terminwünsche bleiben bestehen.');">
                        Trotzdem stornieren
                    </button>
                </form>

                <a href="zeitslots.php">Abbrechen</a>
            </div>
        <?php endif; ?>

        <div class="ts-layout">

            <div class="ts-form-col">

                <!-- Einzelnen Slot -->
                <div class="ts-form-card">
                    <h2>➕ Einzelnen Slot vorschlagen</h2>

                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="vorschlagen">

                        <div class="form-group">
                            <label for="datum">Datum</label>
                            <input id="datum" type="date" name="datum" required>
                        </div>

                        <div class="form-group">
                            <label for="uhrzeit">Von</label>
                            <input id="uhrzeit" type="time" name="uhrzeit" required>
                        </div>

                        <div class="form-group">
                            <label for="dauer_minuten">Dauer (Minuten)</label>
                            <input id="dauer_minuten" type="number" name="dauer_minuten" value="30" min="5" step="5" required>
                        </div>

                        <button type="submit" class="ts-submit-btn">Slot Erstellen</button>
                    </form>
                </div>

                <!-- Ganzer Tag -->
                <div class="ts-form-card">
                    <h2>📅 Ganzen Tag vorschlagen</h2>

                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="tag_vorschlagen">

                        <div class="form-group">
                            <label for="tag_datum">Datum</label>
                            <input id="tag_datum" type="date" name="tag_datum" required>
                        </div>

                        <div class="form-group">
                            <label for="tag_start">Startzeit</label>
                            <input id="tag_start" type="time" name="tag_start" value="09:00" required>
                        </div>

                        <div class="form-group">
                            <label for="tag_ende">Endzeit</label>
                            <input id="tag_ende" type="time" name="tag_ende" value="18:00" required>
                        </div>

                        <div class="form-group">
                            <label for="tag_dauer">Dauer pro Termin (Minuten)</label>
                            <input id="tag_dauer" type="number" name="tag_dauer" value="30" min="5" step="5" required>
                        </div>

                        <small>Erstellt fortlaufend Slots von Start- bis Endzeit für den gewählten Tag. Bereits vorhandene Slots werden übersprungen.</small>

                        <button type="submit" class="ts-submit-btn">Tag Erstellen</button>
                    </form>

                    <div class="ts-secondary-block">
                        <form
                            method="post"
                            onsubmit="return confirm('Alle Termin-Slots im gewählten Zeitraum stornieren? Slots mit bestehenden Terminwünschen werden dabei übersprungen.');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="zeitraum_stornieren">

                            <label>Zeitraum stornieren</label>

                            <div class="form-group">
                                <label for="loeschen_von">Von</label>
                                <input id="loeschen_von" type="date" name="loeschen_von" required>
                            </div>

                            <div class="form-group">
                                <label for="loeschen_bis">Bis</label>
                                <input id="loeschen_bis" type="date" name="loeschen_bis" required>
                            </div>

                            <small>Löscht alle Slots im gewählten Zeitraum (inklusive Start- und End-Tag). Slots mit bestehenden Terminwünschen werden übersprungen.</small>

                            <button type="submit" class="ts-danger-btn">Zeitraum stornieren</button>
                        </form>
                    </div>
                </div>

                <!-- Ganze Woche -->
                <div class="ts-form-card">
                    <h2>🗓️ Ganze Woche vorschlagen</h2>

                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="woche_vorschlagen">

                        <div class="form-group">
                            <label for="woche_start">Woche ab (Montag)</label>
                            <input id="woche_start" type="date" name="woche_start" required>
                        </div>

                        <label>Wochentage</label>
                        <div class="ts-weekday-row">
                            <label class="ts-weekday-chip"><input type="checkbox" name="wochentage[]" value="1" checked> Mo</label>
                            <label class="ts-weekday-chip"><input type="checkbox" name="wochentage[]" value="2" checked> Di</label>
                            <label class="ts-weekday-chip"><input type="checkbox" name="wochentage[]" value="3" checked> Mi</label>
                            <label class="ts-weekday-chip"><input type="checkbox" name="wochentage[]" value="4" checked> Do</label>
                            <label class="ts-weekday-chip"><input type="checkbox" name="wochentage[]" value="5" checked> Fr</label>
                            <label class="ts-weekday-chip"><input type="checkbox" name="wochentage[]" value="6"> Sa</label>
                            <label class="ts-weekday-chip"><input type="checkbox" name="wochentage[]" value="7"> So</label>
                        </div>

                        <div class="form-group">
                            <label for="woche_zeit_start">Startzeit</label>
                            <input id="woche_zeit_start" type="time" name="woche_zeit_start" value="09:00" required>
                        </div>

                        <div class="form-group">
                            <label for="woche_zeit_ende">Endzeit</label>
                            <input id="woche_zeit_ende" type="time" name="woche_zeit_ende" value="17:00" required>
                        </div>

                        <div class="form-group">
                            <label for="woche_dauer">Dauer pro Termin (Minuten)</label>
                            <input id="woche_dauer" type="number" name="woche_dauer" value="30" min="5" step="5" required>
                        </div>

                        <small>Erzeugt Slots für 7 Tage ab dem Start-Datum, nur an den angehakten Wochentagen.</small>

                        <button type="submit" class="ts-submit-btn">Woche Erstellen</button>
                    </form>
                </div>

            </div>

            <div class="ts-list-card">
                <div class="ts-list-header">
                    <h2>Aktuelle Termin-Slots</h2>

                    <form method="get">
                        <select name="status_filter" onchange="this.form.submit()">
                            <option value="alle" <?= $statusFilterSlots === 'alle' ? 'selected' : '' ?>>🔽 Filter: Alle</option>
                            <option value="frei" <?= $statusFilterSlots === 'frei' ? 'selected' : '' ?>>Nur Frei</option>
                            <option value="angefragt" <?= $statusFilterSlots === 'angefragt' ? 'selected' : '' ?>>Nur Angefragt</option>
                            <option value="gebucht" <?= $statusFilterSlots === 'gebucht' ? 'selected' : '' ?>>Nur Gebucht</option>
                        </select>
                    </form>
                </div>

                <div style="overflow-x: auto;">
                    <table class="ts-table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Uhrzeit</th>
                                <th>Dauer</th>
                                <th>Status</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($slots === []): ?>
                                <tr>
                                    <td colspan="5">Keine Termin-Slots gefunden.</td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $tsWochentage = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
                                ?>
                                <?php foreach ($slots as $slot): ?>
                                    <?php
                                    $slotTs = strtotime($slot['datum']);
                                    $tsTag = $tsWochentage[(int) date('N', $slotTs) - 1];
                                    ?>
                                    <tr>
                                        <td class="ts-date">
                                            <strong><?= $tsTag ?>, <?= date('d. M', $slotTs) ?></strong>
                                            <span><?= date('Y', $slotTs) ?></span>
                                        </td>

                                        <td><?= date('H:i', strtotime($slot['uhrzeit'])) ?></td>

                                        <td><?= (int) $slot['dauer_minuten'] ?> Min.</td>

                                        <td>
                                            <?php
                                            $tsLabel = match ($slot['echter_status']) {
                                                'frei' => 'Frei',
                                                'angefragt' => 'Angefragt',
                                                'gebucht' => 'Gebucht',
                                                default => '',
                                            };
                                            ?>
                                            <span class="ts-badge ts-badge--<?= $slot['echter_status'] ?>"><?= $tsLabel ?></span>
                                        </td>

                                        <td class="actions">
                                            <details>
                                                <summary>Bearbeiten</summary>

                                                <form method="post" class="admin-form">
                                                    <?= Csrf::field() ?>
                                                    <input type="hidden" name="action" value="bearbeiten">
                                                    <input type="hidden" name="id" value="<?= (int) $slot['id'] ?>">

                                                    <div class="form-group">
                                                        <label for="datum_<?= (int) $slot['id'] ?>">Datum</label>
                                                        <input id="datum_<?= (int) $slot['id'] ?>" type="date" name="datum" value="<?= htmlspecialchars($slot['datum']) ?>" required>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="uhrzeit_<?= (int) $slot['id'] ?>">Uhrzeit</label>
                                                        <input id="uhrzeit_<?= (int) $slot['id'] ?>" type="time" name="uhrzeit" value="<?= htmlspecialchars(substr($slot['uhrzeit'], 0, 5)) ?>" required>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="dauer_<?= (int) $slot['id'] ?>">Dauer (Minuten)</label>
                                                        <input id="dauer_<?= (int) $slot['id'] ?>" type="number" name="dauer_minuten" value="<?= (int) $slot['dauer_minuten'] ?>" min="5" step="5" required>
                                                    </div>

                                                    <div class="form-actions">
                                                        <button type="submit">Speichern</button>
                                                    </div>
                                                </form>
                                            </details>

                                            <form
                                                method="post"
                                                style="display:inline;"
                                                onsubmit="return confirm('Diesen Termin-Slot wirklich stornieren?');">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="stornieren">
                                                <input type="hidden" name="id" value="<?= (int) $slot['id'] ?>">

                                                <button class="delete-button" type="submit">Stornieren</button>
                                            </form>
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