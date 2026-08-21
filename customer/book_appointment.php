<?php

session_start();


require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Csrf.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../repositories/TerminSlotRepository.php';
require_once __DIR__ . '/../repositories/ServiceRepository.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';
require_once __DIR__ . '/../repositories/TerminwunschRepository.php';

// WICHTIG: Die Session-Rolle wird NICHT hier gesetzt, sondern beim Login.
// Auth::requireRole() prüft die tatsächlich beim Login vergebene Rolle.

Auth::requireRole('kunde');

// Flash-Message aus der Session holen (falls wir gerade per Redirect
// von einer erfolgreichen Buchung hierher gekommen sind) und danach
// sofort wieder löschen, damit sie nicht bei jedem weiteren Reload
// erneut erscheint.
$message = $_SESSION['flash_message'] ?? '';
$success = $_SESSION['flash_success'] ?? false;
unset($_SESSION['flash_message'], $_SESSION['flash_success']);

/*
|--------------------------------------------------------------------------
| Dienstleistungen laden
|--------------------------------------------------------------------------
*/
$db = Database::getInstance()->getConnection();

$terminSlotRepository = new TerminSlotRepository($db);
$freieTermine = $terminSlotRepository->getFreieTermine();
$wochentage = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

$serviceStatement = new ServiceRepository($db);
$services = $serviceStatement->getAllServices();
$beliebtesteServiceIds = $serviceStatement->getBeliebtesteServiceIds(3);

/*
|--------------------------------------------------------------------------
| Mitarbeiter laden
|--------------------------------------------------------------------------
*/

$employeeStatement = new EmployeeRepository($db);
$employees = $employeeStatement->getAllEmployees();
$aktiveMitarbeiterAnzahl = $employeeStatement->countActiveEmployees();

$terminwunsche = new TerminwunschRepository($db);

/*
|--------------------------------------------------------------------------
| Auslastung pro Zeit-Slot berechnen (Frei / In Bearbeitung / Ausgebucht)
|--------------------------------------------------------------------------
| Zeigt dem Kunden einen Hinweis, ohne den Slot zu verstecken. Ein Slot
| gilt nur dann als "ausgebucht", wenn ALLE aktiven Mitarbeiter für
| diese Zeit bereits bestätigt sind.
*/

foreach ($freieTermine as $datum => $slotsAmTag) {
    foreach ($slotsAmTag as $index => $slot) {
        $slotStart = new DateTime($slot['datum'] . ' ' . $slot['uhrzeit']);
        $slotEnd = clone $slotStart;
        $slotEnd->modify('+' . (int) $slot['dauer_minuten'] . ' minutes');

        $auslastung = $terminwunsche->getAuslastung(
            $slotStart->format('Y-m-d H:i:s'),
            $slotEnd->format('Y-m-d H:i:s')
        );

        $freieMitarbeiter = $aktiveMitarbeiterAnzahl - $auslastung['bestaetigt'];

        if ($freieMitarbeiter <= 0) {
            $status = 'ausgebucht';
        } elseif ($auslastung['angefragt'] > 0) {
            $status = 'in_bearbeitung';
        } else {
            $status = 'frei';
        }

        $freieTermine[$datum][$index]['status'] = $status;
    }
}

/*
|--------------------------------------------------------------------------
| Termin speichern
|--------------------------------------------------------------------------
| Hinweis: Die gesamte Validierung inkl. Prüfung von zeitslot_id passiert
| ausschließlich hier innerhalb des POST-Blocks. Dadurch wird beim ersten
| (GET-)Aufruf der Seite keine fälschliche Fehlermeldung mehr angezeigt.
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $message = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        $success = false;
    } else {
        $serviceId = filter_input(
            INPUT_POST,
            'service_id',
            FILTER_VALIDATE_INT
        );

        $employeeId = filter_input(
            INPUT_POST,
            'employee_id',
            FILTER_VALIDATE_INT
        );

        $zeitslotId = filter_input(
            INPUT_POST,
            'zeitslot_id',
            FILTER_VALIDATE_INT
        );

        $appointmentDate = trim(
            $_POST['terminwunsche_date'] ?? ''
        );

        $appointmentTime = trim(
            $_POST['terminwunsche_time'] ?? ''
        );

        $customerNote = trim(
            $_POST['customer_note'] ?? ''
        );

        if (!$serviceId || !$employeeId) {
            $message = 'Bitte Dienstleistung und Mitarbeiter auswählen.';
        } elseif (
            !$zeitslotId &&
            ($appointmentDate === '' || $appointmentTime === '')
        ) {
            $message = 'Bitte einen freien Termin auswählen oder einen eigenen Terminwunsch eingeben.';
        } else {
            try {
                $service = $serviceStatement->serviceCheck($serviceId);

                if (!$service) {
                    throw new RuntimeException(
                        'Die ausgewählte Dienstleistung ist ungültig.'
                    );
                }

                /*
            |--------------------------------------------------------------------------
            | Weg 1: vorhandenen Zeitslot verwenden
            |--------------------------------------------------------------------------
            */

                if ($zeitslotId) {
                    $zeitslot = $terminwunsche->getFreierZeitslotById(
                        $zeitslotId
                    );

                    if (!$zeitslot) {
                        throw new RuntimeException(
                            'Der ausgewählte Termin ist nicht mehr verfügbar.'
                        );
                    }

                    $startDateTime = new DateTime(
                        $zeitslot['datum'] . ' ' . $zeitslot['uhrzeit']
                    );
                } else {
                    /*
                |--------------------------------------------------------------------------
                | Weg 2: eigenen Terminwunsch verwenden
                |--------------------------------------------------------------------------
                */

                    $startDateTime = DateTime::createFromFormat(
                        'Y-m-d H:i',
                        $appointmentDate . ' ' . $appointmentTime
                    );

                    if (!$startDateTime) {
                        throw new RuntimeException(
                            'Datum oder Uhrzeit ist ungültig.'
                        );
                    }
                }

                if ($startDateTime <= new DateTime()) {
                    throw new RuntimeException(
                        'Der Termin muss in der Zukunft liegen.'
                    );
                }

                /*
            |--------------------------------------------------------------------------
            | Ende anhand der Dienstleistungsdauer berechnen
            |--------------------------------------------------------------------------
            */

                $endDateTime = clone $startDateTime;

                $endDateTime->modify(
                    '+' . (int) $service['duration_minutes'] . ' minutes'
                );

                $terminwunscheStart = $startDateTime->format(
                    'Y-m-d H:i:s'
                );

                $terminwunscheEnd = $endDateTime->format(
                    'Y-m-d H:i:s'
                );

                /*
            |--------------------------------------------------------------------------
            | Mitarbeiterverfügbarkeit prüfen
            |--------------------------------------------------------------------------
            */

                $isAvailable = $terminwunsche->isAvailable(
                    $employeeId,
                    $terminwunscheStart,
                    $terminwunscheEnd
                );

                if (!$isAvailable) {
                    throw new RuntimeException(
                        'Dieser Mitarbeiter ist zu dieser Zeit bereits belegt.'
                    );
                }

                /*
            |--------------------------------------------------------------------------
            | Terminwunsch speichern
            |--------------------------------------------------------------------------
            */

                $terminwunsche->createTerminwunsch(
                    $employeeId,
                    $serviceId,
                    $terminwunscheStart,
                    $terminwunscheEnd,
                    $customerNote !== ''
                        ? $customerNote
                        : null
                );

                $message = $zeitslotId
                    ? 'Der ausgewählte Termin wurde erfolgreich gebucht.'
                    : 'Dein Terminwunsch wurde erfolgreich angefragt.';

                $success = true;

                // Post-Redirect-Get: Erfolgsmeldung in der Session zwischenspeichern
                // und danach auf dieselbe Seite umleiten. So wird das Formular
                // beim Neuladen (F5) nicht erneut abgeschickt, und die Seite
                // zeigt automatisch die aktualisierte Liste freier Termine.
                $_SESSION['flash_message'] = $message;
                $_SESSION['flash_success'] = $success;

                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (RuntimeException $e) {
                $message = $e->getMessage();
                $success = false;
            } catch (PDOException $e) {
                $message = 'Datenbankfehler: ' . $e->getMessage();
                $success = false;
            }
        }
    }
}
?>








<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/book_appointment.css">

    <title>Termin buchen - Bella Beauty</title>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="booking-page">

        <div class="booking-hero">
            <h1>Elevate Your Beauty</h1>
            <p>Wähle eine Dienstleistung und finde den perfekten Zeitpunkt für dein Self-Care-Ritual. Unsere erfahrenen Artisans freuen sich auf dich.</p>
        </div>

        <?php
        $servicesNachKategorie = [];
        foreach ($services as $service) {
            $kategorie = $service['category'] ?? '';
            $kategorie = $kategorie !== '' ? $kategorie : 'Sonstiges';
            $servicesNachKategorie[$kategorie][] = $service;
        }
        ?>

        <div class="booking-services__header">
            <h2>Our Signature Services</h2>

            <div class="category-pills" id="category-pills">
                <button type="button" class="category-pill is-active" data-category="all">All</button>
                <?php foreach (array_keys($servicesNachKategorie) as $kategorieName): ?>
                    <button type="button" class="category-pill" data-category="<?= htmlspecialchars($kategorieName) ?>">
                        <?= htmlspecialchars($kategorieName) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="signature-grid" id="signature-grid">
            <?php foreach ($services as $service): ?>
                <?php $serviceKategorie = ($service['category'] ?? '') !== '' ? $service['category'] : 'Sonstiges'; ?>
                <div class="signature-card" data-category="<?= htmlspecialchars($serviceKategorie) ?>" data-service-id="<?= (int) $service['id'] ?>">
                    <?php if (!empty($service['photo_url'])): ?>
                        <div class="signature-card__image" style="background-image: url('<?= htmlspecialchars($service['photo_url']) ?>')"></div>
                    <?php else: ?>
                        <div class="signature-card__image signature-card__image--placeholder">💇</div>
                    <?php endif; ?>
                    <div class="signature-card__body">
                        <h3>
                            <?= htmlspecialchars($service['name']) ?>
                            <?= in_array((int) $service['id'], $beliebtesteServiceIds, true) ? ' ⭐' : '' ?>
                        </h3>
                        <span>Ab <?= number_format((float) $service['price'], 2, ',', '.') ?> €</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" class="booking-layout">
            <?= Csrf::field() ?>

            <?php if ($message !== ''): ?>
                <script>
                    alert(<?= json_encode($message, JSON_UNESCAPED_UNICODE) ?>);
                    window.location.href = window.location.href;
                </script>
            <?php endif; ?>

            <div class="booking-card">
                <h2>Book Appointment</h2>

                <div class="booking-field">
                    <label for="service_id">Select Service</label>
                    <select name="service_id" id="service_id" required>
                        <option value="">Bitte auswählen</option>
                        <?php foreach ($servicesNachKategorie as $kategorieName => $serviceGruppe): ?>
                            <optgroup label="<?= htmlspecialchars($kategorieName) ?>">
                                <?php foreach ($serviceGruppe as $service): ?>
                                    <option
                                        value="<?= (int) $service['id'] ?>"
                                        <?= ((($_POST['service_id'] ?? $_GET['service_id'] ?? '')) == $service['id']) ? 'selected' : '' ?>>
                                        <?= in_array((int) $service['id'], $beliebtesteServiceIds, true) ? '⭐ ' : '' ?><?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>
                                        – <?= (int) $service['duration_minutes'] ?> Min. – <?= number_format((float) $service['price'], 2, ',', '.') ?> €
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="booking-field">
                    <label for="employee_id">Beauty Specialist</label>
                    <select name="employee_id" id="employee_id" required>
                        <option value="">Bitte auswählen</option>
                        <?php foreach ($employees as $employee): ?>
                            <option
                                value="<?= (int) $employee['id'] ?>"
                                <?= (($_POST['employee_id'] ?? '') == $employee['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($employee['vor_name'] . ' ' . $employee['nach_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="booking-field">
                    <label for="terminwunsche_date">Select Date</label>
                    <input
                        type="date"
                        name="terminwunsche_date"
                        id="terminwunsche_date"
                        min="<?= date('Y-m-d') ?>"
                        value="<?= htmlspecialchars($_POST['terminwunsche_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="booking-field">
                    <label for="terminwunsche_time">Uhrzeit (falls kein Zeitslot rechts gewählt)</label>
                    <input
                        type="time"
                        name="terminwunsche_time"
                        id="terminwunsche_time"
                        min="09:00"
                        max="18:00"
                        step="1800"
                        value="<?= htmlspecialchars($_POST['terminwunsche_time'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="booking-field">
                    <label for="customer_note">Nachricht oder Wunsch</label>
                    <textarea
                        name="customer_note"
                        id="customer_note"
                        rows="3"
                        placeholder="Optional"><?= htmlspecialchars($_POST['customer_note'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button type="submit" class="booking-submit-btn">
                    Confirm Selection
                </button>
            </div>

            <div class="booking-card">
                <h2>Available Time Slots</h2>

                <div class="slots-legend">
                    <span><i style="background:#22c55e;"></i> Frei</span>
                    <span><i style="background:#f59e0b;"></i> In Bearbeitung</span>
                    <span><i style="background:#ef4444;"></i> Ausgebucht</span>
                </div>

                <?php if (empty($freieTermine)): ?>

                    <p class="booking-empty">Aktuell sind keine freien Termine verfügbar.</p>

                <?php else: ?>

                    <?php foreach ($freieTermine as $datum => $slots): ?>
                        <?php
                        $ts = strtotime($datum);
                        $tag = $wochentage[(int) date('N', $ts) - 1];
                        $anzeige = $tag . ', ' . date('d.m.Y', $ts);
                        ?>

                        <div class="slots-day-block">
                            <p class="slots-day-block__title"><?= h($anzeige) ?></p>

                            <div class="slot-grid">
                                <?php foreach ($slots as $slot): ?>
                                    <?php
                                    $istAusgebucht = $slot['status'] === 'ausgebucht';
                                    $statusText = match ($slot['status']) {
                                        'frei' => 'Frei',
                                        'in_bearbeitung' => 'In Bearbeitung',
                                        'ausgebucht' => 'Ausgebucht',
                                        default => '',
                                    };
                                    ?>
                                    <div class="slot-chip slot-chip--<?= h($slot['status']) ?>">
                                        <input
                                            type="radio"
                                            name="zeitslot_id"
                                            id="slot-<?= (int) $slot['id'] ?>"
                                            value="<?= (int) $slot['id'] ?>"
                                            <?= $istAusgebucht ? 'disabled' : '' ?>
                                            <?= (($_POST['zeitslot_id'] ?? '') == $slot['id']) ? 'checked' : '' ?>>
                                        <label for="slot-<?= (int) $slot['id'] ?>">
                                            <?= h(substr($slot['uhrzeit'], 0, 5)) ?>
                                            <span class="slot-chip__status"><?= h($statusText) ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>
            </div>

        </form>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Kategorie-Filter für die Signature-Services-Karten
        document.querySelectorAll('#category-pills .category-pill').forEach(function(pill) {
            pill.addEventListener('click', function() {
                document.querySelectorAll('#category-pills .category-pill').forEach(function(p) {
                    p.classList.remove('is-active');
                });
                pill.classList.add('is-active');

                var kategorie = pill.getAttribute('data-category');

                document.querySelectorAll('#signature-grid .signature-card').forEach(function(card) {
                    var passt = kategorie === 'all' || card.getAttribute('data-category') === kategorie;
                    card.style.display = passt ? '' : 'none';
                });
            });
        });

        // Klick auf eine Service-Karte wählt sie im Dropdown aus
        document.querySelectorAll('#signature-grid .signature-card').forEach(function(card) {
            card.addEventListener('click', function() {
                var serviceId = card.getAttribute('data-service-id');
                var select = document.getElementById('service_id');
                if (select) {
                    select.value = serviceId;
                }
            });
        });
    </script>

</body>

</html>