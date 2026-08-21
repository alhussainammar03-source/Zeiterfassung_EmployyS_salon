<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/PromotionRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $promotionRepository = new PromotionRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'anlegen') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? '🎉');
            $validUntil = trim($_POST['valid_until'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['aktiv', 'inaktiv'], true) ? $_POST['status'] : 'aktiv';

            if ($title === '' || $description === '') {
                $fehler = 'Bitte Titel und Beschreibung angeben.';
            } else {
                $promotionRepository->create(
                    $title,
                    $description,
                    $icon,
                    $validUntil !== '' ? $validUntil : null,
                    $status
                );
                header('Location: promotions.php?angelegt=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'bearbeiten') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? '🎉');
            $validUntil = trim($_POST['valid_until'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['aktiv', 'inaktiv'], true) ? $_POST['status'] : 'aktiv';

            if (!$id || $title === '' || $description === '') {
                $fehler = 'Bitte Titel und Beschreibung angeben.';
            } else {
                $promotionRepository->update(
                    $id,
                    $title,
                    $description,
                    $icon,
                    $validUntil !== '' ? $validUntil : null,
                    $status
                );
                header('Location: promotions.php?bearbeitet=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'loeschen') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id) {
                $promotionRepository->delete($id);
            }
            header('Location: promotions.php?geloescht=1');
            exit;
        }
    }

    $promotionsListe = $promotionRepository->getAll();

    if (isset($_GET['angelegt'])) {
        $meldung = 'Aktion wurde erstellt.';
    }
    if (isset($_GET['bearbeitet'])) {
        $meldung = 'Aktion wurde aktualisiert.';
    }
    if (isset($_GET['geloescht'])) {
        $meldung = 'Aktion wurde gelöscht.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $promotionsListe = [];
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rabatt-Aktionen verwalten - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/promotions_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Rabatt-Aktionen verwalten</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Aktionen und Angebote für deine Kunden verwalten.
                </p>
            </div>
            <div>
                <button type="button" class="promo-new-btn" onclick="document.getElementById('promo-modal').classList.add('is-open')">
                    + Neue Aktion
                </button>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <?php if ($promotionsListe === []): ?>
            <p>Noch keine Aktionen.</p>
        <?php else: ?>
            <div class="promo-grid">
                <?php foreach ($promotionsListe as $promo): ?>
                    <div class="promo-card <?= $promo['status'] === 'inaktiv' ? 'promo-card--inaktiv' : '' ?>">
                        <div class="promo-card__top">
                            <span class="promo-card__icon"><?= htmlspecialchars($promo['icon']) ?></span>
                            <span class="promo-card__status promo-card__status--<?= $promo['status'] ?>">
                                <?= $promo['status'] === 'aktiv' ? 'Aktiv' : 'Inaktiv' ?>
                            </span>
                        </div>

                        <h3><?= htmlspecialchars($promo['title']) ?></h3>
                        <p><?= htmlspecialchars($promo['description']) ?></p>

                        <?php if ($promo['valid_until'] !== null): ?>
                            <div class="promo-card__valid">Gültig bis <?= date('d.m.Y', strtotime($promo['valid_until'])) ?></div>
                        <?php endif; ?>

                        <div class="promo-card__actions">
                            <button type="button" class="promo-icon-btn promo-icon-btn--edit" title="Bearbeiten" onclick="document.getElementById('edit-modal-<?= (int) $promo['id'] ?>').classList.add('is-open')">✏️</button>

                            <form method="post" style="display:inline;" onsubmit="return confirm('Aktion wirklich löschen?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="loeschen">
                                <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                                <button type="submit" class="promo-icon-btn promo-icon-btn--delete" title="Löschen">🗑️</button>
                            </form>
                        </div>
                    </div>

                    <!-- Bearbeiten-Modal -->
                    <div class="promo-modal-overlay" id="edit-modal-<?= (int) $promo['id'] ?>">
                        <div class="promo-modal">
                            <h3>Aktion bearbeiten</h3>

                            <form method="post">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="bearbeiten">
                                <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">

                                <label>Titel</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($promo['title']) ?>" required>

                                <label>Beschreibung</label>
                                <textarea name="description" rows="2" required><?= htmlspecialchars($promo['description']) ?></textarea>

                                <label>Icon (Emoji)</label>
                                <input type="text" name="icon" value="<?= htmlspecialchars($promo['icon']) ?>" maxlength="10">

                                <label>Gültig bis</label>
                                <input type="date" name="valid_until" value="<?= htmlspecialchars($promo['valid_until'] ?? '') ?>">

                                <label>Status</label>
                                <select name="status">
                                    <option value="aktiv" <?= $promo['status'] === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                                    <option value="inaktiv" <?= $promo['status'] === 'inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
                                </select>

                                <div class="promo-modal-actions">
                                    <button type="button" class="promo-icon-btn" style="width:auto; padding:9px 16px;" onclick="document.getElementById('edit-modal-<?= (int) $promo['id'] ?>').classList.remove('is-open')">Abbrechen</button>
                                    <button type="submit" class="promo-new-btn">Speichern</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Neue Aktion Modal -->
    <div class="promo-modal-overlay" id="promo-modal">
        <div class="promo-modal">
            <h3>Neue Aktion erstellen</h3>

            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="anlegen">

                <label>Titel</label>
                <input type="text" name="title" placeholder="z.B. 15% Rabatt" required>

                <label>Beschreibung</label>
                <textarea name="description" rows="2" placeholder="z.B. Auf alle Gesichtsbehandlungen im November." required></textarea>

                <label>Icon (Emoji)</label>
                <input type="text" name="icon" value="🎉" maxlength="10">

                <label>Gültig bis (optional)</label>
                <input type="date" name="valid_until">

                <label>Status</label>
                <select name="status">
                    <option value="aktiv">Aktiv</option>
                    <option value="inaktiv">Inaktiv</option>
                </select>

                <div class="promo-modal-actions">
                    <button type="button" class="promo-icon-btn" style="width:auto; padding:9px 16px;" onclick="document.getElementById('promo-modal').classList.remove('is-open')">Abbrechen</button>
                    <button type="submit" class="promo-new-btn">Erstellen</button>
                </div>
            </form>
        </div>
    </div>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>