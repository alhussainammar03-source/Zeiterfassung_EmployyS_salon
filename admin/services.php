<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/ServiceRepository.php';
require_once __DIR__ . '/../services/CloudinaryUploader.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $serviceRepository = new ServiceRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'anlegen') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $dauer = (int) ($_POST['duration_minutes'] ?? 0);
            $preis = trim($_POST['price'] ?? '');
            $status = $_POST['status'] ?? 'aktiv';
            $category = trim($_POST['category'] ?? '');

            if ($name === '' || $dauer <= 0 || $preis === '' || !is_numeric($preis)) {
                $fehler = 'Bitte Name, eine gültige Dauer und einen gültigen Preis angeben.';
            } else {
                $erstellt = $serviceRepository->createService(
                    $name,
                    $description !== '' ? $description : null,
                    $dauer,
                    (float) $preis,
                    in_array($status, ['aktiv', 'inaktiv'], true) ? $status : 'aktiv',
                    $category !== '' ? $category : null
                );

                if ($erstellt) {
                    $neueId = $serviceRepository->getLastInsertId();

                    if (
                        isset($_FILES['photo']) &&
                        $_FILES['photo']['error'] === UPLOAD_ERR_OK
                    ) {
                        try {
                            $uploader = new CloudinaryUploader();
                            $url = $uploader->uploadServicePhoto($_FILES['photo']['tmp_name'], $neueId);
                            $serviceRepository->updatePhotoUrl($neueId, $url);
                        } catch (RuntimeException $uploadException) {
                            error_log($uploadException->getMessage());
                        }
                    }
                }

                header('Location: services.php?angelegt=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'bearbeiten') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $dauer = (int) ($_POST['duration_minutes'] ?? 0);
            $preis = trim($_POST['price'] ?? '');
            $status = $_POST['status'] ?? 'aktiv';
            $category = trim($_POST['category'] ?? '');

            if (!$id || $name === '' || $dauer <= 0 || $preis === '' || !is_numeric($preis)) {
                $fehler = 'Bitte Name, eine gültige Dauer und einen gültigen Preis angeben.';
            } else {
                $neueFotoUrl = null;

                if (
                    isset($_FILES['photo']) &&
                    $_FILES['photo']['error'] === UPLOAD_ERR_OK
                ) {
                    try {
                        $uploader = new CloudinaryUploader();
                        $neueFotoUrl = $uploader->uploadServicePhoto($_FILES['photo']['tmp_name'], $id);
                    } catch (RuntimeException $uploadException) {
                        error_log($uploadException->getMessage());
                    }
                }

                $serviceRepository->updateService(
                    $id,
                    $name,
                    $description !== '' ? $description : null,
                    $dauer,
                    (float) $preis,
                    in_array($status, ['aktiv', 'inaktiv'], true) ? $status : 'aktiv',
                    $category !== '' ? $category : null,
                    $neueFotoUrl
                );
                header('Location: services.php?bearbeitet=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'status_aendern') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $neuerStatus = $_POST['status'] ?? '';

            if ($id) {
                $serviceRepository->changeStatus($id, $neuerStatus);
            }
            header('Location: services.php?status_geaendert=1');
            exit;
        } elseif (($_POST['action'] ?? '') === 'loeschen') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id) {
                try {
                    $serviceRepository->deleteService($id);
                    header('Location: services.php?geloescht=1');
                    exit;
                } catch (PDOException $exception) {
                    $fehler = 'Diese Dienstleistung kann wegen bestehender Termine '
                        . 'nicht gelöscht werden. Setze sie stattdessen auf inaktiv.';
                }
            }
        }
    }

    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $services = $serviceRepository->searchServices($search);
    } else {
        $services = $serviceRepository->getAllServicesAdmin();
    }

    $kategorien = $serviceRepository->getAlleKategorien();
    $beliebtesteIds = $serviceRepository->getBeliebtesteServiceIds(3);

    if (isset($_GET['angelegt'])) {
        $meldung = 'Die Dienstleistung wurde erfolgreich angelegt.';
    }
    if (isset($_GET['bearbeitet'])) {
        $meldung = 'Die Dienstleistung wurde aktualisiert.';
    }
    if (isset($_GET['status_geaendert'])) {
        $meldung = 'Der Status wurde geändert.';
    }
    if (isset($_GET['geloescht'])) {
        $meldung = 'Die Dienstleistung wurde gelöscht.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $services = [];
    $kategorien = [];
    $beliebtesteIds = [];
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dienstleistungen verwalten</title>

    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/button.css">
    <link rel="stylesheet" href="../style/nav.css">
    <link rel="stylesheet" href="../style/header.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/sidebar.css">
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Dienstleistungen verwalten</h1>

                <a href="../pages/admin_dashboard.php">
                    Zurück zum Dashboard
                </a>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success">
                <?= htmlspecialchars($meldung) ?>
            </div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error">
                <?= htmlspecialchars($fehler) ?>
            </div>
        <?php endif; ?>

        <datalist id="kategorien-liste">
            <?php foreach ($kategorien as $kategorie): ?>
                <option value="<?= htmlspecialchars($kategorie) ?>">
                <?php endforeach; ?>
        </datalist>

        <form method="get" class="search-form">
            <input
                type="search"
                name="search"
                value="<?= htmlspecialchars($search ?? '') ?>"
                placeholder="Name, Beschreibung oder Kategorie suchen">
            <button type="submit">Suchen</button>
            <?php if (($search ?? '') !== ''): ?>
                <a href="services.php">Suche zurücksetzen</a>
            <?php endif; ?>
        </form>

        <fieldset class="admin-form" style="margin: 20px 0 30px;">
            <legend>Neue Dienstleistung anlegen</legend>

            <form method="post" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="anlegen">

                <div class="form-group">
                    <label for="name">Name</label>
                    <input id="name" type="text" name="name" required>
                </div>

                <div class="form-group">
                    <label for="category">Kategorie (optional)</label>
                    <input id="category" type="text" name="category" list="kategorien-liste" placeholder="z.B. Haare, Gesicht, Massage">
                </div>

                <div class="form-group">
                    <label for="description">Beschreibung (optional)</label>
                    <textarea id="description" name="description" rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label for="duration_minutes">Dauer (Minuten)</label>
                    <input id="duration_minutes" type="number" name="duration_minutes" min="5" step="5" value="30" required>
                </div>

                <div class="form-group">
                    <label for="price">Preis (€)</label>
                    <input id="price" type="number" name="price" min="0" step="0.01" required>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="aktiv">Aktiv</option>
                        <option value="inaktiv">Inaktiv</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="photo">Foto (optional)</label>
                    <input id="photo" type="file" name="photo" accept="image/png, image/jpeg, image/webp">
                </div>

                <div class="form-actions">
                    <button type="submit">Anlegen</button>
                </div>
            </form>
        </fieldset>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Name</th>
                        <th>Kategorie</th>
                        <th>Beschreibung</th>
                        <th>Dauer</th>
                        <th>Preis</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($services === []): ?>
                        <tr>
                            <td colspan="8">Keine Dienstleistungen gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <?php $istBeliebt = in_array((int) $service['id'], $beliebtesteIds, true); ?>
                            <tr>
                                <td>
                                    <?php if (!empty($service['photo_url'])): ?>
                                        <img
                                            src="<?= htmlspecialchars($service['photo_url']) ?>"
                                            alt="Foto von <?= htmlspecialchars($service['name']) ?>"
                                            class="employee-thumbnail">
                                    <?php else: ?>
                                        <span class="employee-thumbnail employee-thumbnail--placeholder">
                                            <?= htmlspecialchars(mb_substr($service['name'], 0, 2)) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($service['name']) ?>
                                    <?php if ($istBeliebt): ?>
                                        <span class="status aktiv">⭐ Beliebt</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($service['category'] ?? '') ?></td>
                                <td><?= htmlspecialchars($service['description'] ?? '') ?></td>
                                <td><?= (int) $service['duration_minutes'] ?> Min.</td>
                                <td><?= number_format((float) $service['price'], 2, ',', '.') ?> €</td>
                                <td>
                                    <span class="status <?= $service['status'] === 'aktiv' ? 'aktiv' : 'inaktiv' ?>">
                                        <?= $service['status'] === 'aktiv' ? 'Aktiv' : 'Inaktiv' ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <details>
                                        <summary>Bearbeiten</summary>

                                        <form method="post" class="admin-form" enctype="multipart/form-data">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="bearbeiten">
                                            <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">

                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" name="name" value="<?= htmlspecialchars($service['name']) ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label>Kategorie</label>
                                                <input type="text" name="category" list="kategorien-liste" value="<?= htmlspecialchars($service['category'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label>Beschreibung</label>
                                                <textarea name="description" rows="2"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label>Dauer (Minuten)</label>
                                                <input type="number" name="duration_minutes" min="5" step="5" value="<?= (int) $service['duration_minutes'] ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label>Preis (€)</label>
                                                <input type="number" name="price" min="0" step="0.01" value="<?= htmlspecialchars((string) $service['price']) ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label>Status</label>
                                                <select name="status">
                                                    <option value="aktiv" <?= $service['status'] === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                                                    <option value="inaktiv" <?= $service['status'] === 'inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label>Neues Foto (optional, ersetzt aktuelles)</label>
                                                <input type="file" name="photo" accept="image/png, image/jpeg, image/webp">
                                            </div>

                                            <div class="form-actions">
                                                <button type="submit">Speichern</button>
                                            </div>
                                        </form>
                                    </details>

                                    <form method="post" style="display:inline;">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="status_aendern">
                                        <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $service['status'] === 'aktiv' ? 'inaktiv' : 'aktiv' ?>">
                                        <button type="submit">
                                            <?= $service['status'] === 'aktiv' ? 'Deaktivieren' : 'Aktivieren' ?>
                                        </button>
                                    </form>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Dienstleistung wirklich löschen?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="loeschen">
                                        <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                        <button class="delete-button" type="submit">Löschen</button>
                                    </form>
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