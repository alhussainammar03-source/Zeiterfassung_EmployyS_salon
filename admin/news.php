<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/NewsRepository.php';
require_once __DIR__ . '/../services/CloudinaryUploader.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$meldung = '';
$fehler = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $newsRepository = new NewsRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $fehler = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'anlegen') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['veroeffentlicht', 'entwurf'], true) ? $_POST['status'] : 'veroeffentlicht';

            if ($title === '' || $content === '') {
                $fehler = 'Bitte Titel und Inhalt angeben.';
            } else {
                $newsRepository->create($title, $content, $status);
                $neueId = $newsRepository->getLastInsertId();

                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $uploader = new CloudinaryUploader();
                        $url = $uploader->uploadNewsPhoto($_FILES['photo']['tmp_name'], $neueId);
                        $newsRepository->updatePhotoUrl($neueId, $url);
                    } catch (RuntimeException $uploadException) {
                        error_log($uploadException->getMessage());
                    }
                }

                header('Location: news.php?angelegt=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'bearbeiten') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['veroeffentlicht', 'entwurf'], true) ? $_POST['status'] : 'veroeffentlicht';

            if (!$id || $title === '' || $content === '') {
                $fehler = 'Bitte Titel und Inhalt angeben.';
            } else {
                $neueFotoUrl = null;

                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $uploader = new CloudinaryUploader();
                        $neueFotoUrl = $uploader->uploadNewsPhoto($_FILES['photo']['tmp_name'], $id);
                    } catch (RuntimeException $uploadException) {
                        error_log($uploadException->getMessage());
                    }
                }

                $newsRepository->update($id, $title, $content, $status, $neueFotoUrl);
                header('Location: news.php?bearbeitet=1');
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'loeschen') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id) {
                $newsRepository->delete($id);
            }
            header('Location: news.php?geloescht=1');
            exit;
        }
    }

    $newsListe = $newsRepository->getAll();

    if (isset($_GET['angelegt'])) {
        $meldung = 'News-Beitrag wurde erstellt.';
    }
    if (isset($_GET['bearbeitet'])) {
        $meldung = 'News-Beitrag wurde aktualisiert.';
    }
    if (isset($_GET['geloescht'])) {
        $meldung = 'News-Beitrag wurde gelöscht.';
    }
} catch (Throwable $exception) {
    $fehler = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
    $newsListe = [];
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salon News verwalten - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/news_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Salon News verwalten</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Neuigkeiten für deine Kunden veröffentlichen.
                </p>
            </div>
            <div>
                <button type="button" class="news-new-btn" onclick="document.getElementById('news-modal').classList.add('is-open')">
                    + Neuer Beitrag
                </button>
            </div>
        </div>

        <?php if ($meldung !== ''): ?>
            <div class="message success"><?= htmlspecialchars($meldung) ?></div>
        <?php endif; ?>

        <?php if ($fehler !== ''): ?>
            <div class="message error"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <?php if ($newsListe === []): ?>
            <p>Noch keine News-Beiträge.</p>
        <?php else: ?>
            <div class="news-grid">
                <?php foreach ($newsListe as $news): ?>
                    <div class="news-card">
                        <?php if (!empty($news['photo_url'])): ?>
                            <div class="news-card__image" style="background-image: url('<?= htmlspecialchars($news['photo_url']) ?>')"></div>
                        <?php else: ?>
                            <div class="news-card__image news-card__image--placeholder">📰</div>
                        <?php endif; ?>

                        <div class="news-card__body">
                            <div class="news-card__top">
                                <h3><?= htmlspecialchars($news['title']) ?></h3>
                                <span class="news-card__status news-card__status--<?= $news['status'] ?>">
                                    <?= $news['status'] === 'veroeffentlicht' ? 'Live' : 'Entwurf' ?>
                                </span>
                            </div>

                            <p><?= htmlspecialchars($news['content']) ?></p>

                            <div class="news-card__footer">
                                <span class="news-card__date"><?= date('d.m.Y', strtotime($news['created_at'])) ?></span>

                                <div class="news-card__actions">
                                    <button type="button" class="news-icon-btn news-icon-btn--edit" title="Bearbeiten" onclick="document.getElementById('edit-modal-<?= (int) $news['id'] ?>').classList.add('is-open')">✏️</button>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Beitrag wirklich löschen?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="loeschen">
                                        <input type="hidden" name="id" value="<?= (int) $news['id'] ?>">
                                        <button type="submit" class="news-icon-btn news-icon-btn--delete" title="Löschen">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bearbeiten-Modal -->
                    <div class="news-modal-overlay" id="edit-modal-<?= (int) $news['id'] ?>">
                        <div class="news-modal">
                            <h3>Beitrag bearbeiten</h3>

                            <form method="post" enctype="multipart/form-data">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="bearbeiten">
                                <input type="hidden" name="id" value="<?= (int) $news['id'] ?>">

                                <label>Titel</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($news['title']) ?>" required>

                                <label>Inhalt</label>
                                <textarea name="content" rows="3" required><?= htmlspecialchars($news['content']) ?></textarea>

                                <label>Neues Foto (optional)</label>
                                <input type="file" name="photo" accept="image/png, image/jpeg, image/webp">

                                <label>Status</label>
                                <select name="status">
                                    <option value="veroeffentlicht" <?= $news['status'] === 'veroeffentlicht' ? 'selected' : '' ?>>Veröffentlicht</option>
                                    <option value="entwurf" <?= $news['status'] === 'entwurf' ? 'selected' : '' ?>>Entwurf</option>
                                </select>

                                <div class="news-modal-actions">
                                    <button type="button" class="news-icon-btn" style="width:auto; padding:9px 16px;" onclick="document.getElementById('edit-modal-<?= (int) $news['id'] ?>').classList.remove('is-open')">Abbrechen</button>
                                    <button type="submit" class="news-new-btn">Speichern</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Neuer Beitrag Modal -->
    <div class="news-modal-overlay" id="news-modal">
        <div class="news-modal">
            <h3>Neuen Beitrag erstellen</h3>

            <form method="post" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="anlegen">

                <label>Titel</label>
                <input type="text" name="title" required>

                <label>Inhalt</label>
                <textarea name="content" rows="3" required></textarea>

                <label>Foto (optional)</label>
                <input type="file" name="photo" accept="image/png, image/jpeg, image/webp">

                <label>Status</label>
                <select name="status">
                    <option value="veroeffentlicht">Veröffentlicht</option>
                    <option value="entwurf">Entwurf</option>
                </select>

                <div class="news-modal-actions">
                    <button type="button" class="news-icon-btn" style="width:auto; padding:9px 16px;" onclick="document.getElementById('news-modal').classList.remove('is-open')">Abbrechen</button>
                    <button type="submit" class="news-new-btn">Erstellen</button>
                </div>
            </form>
        </div>
    </div>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>