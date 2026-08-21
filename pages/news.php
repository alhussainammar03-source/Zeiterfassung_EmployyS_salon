<?php

session_start();

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/NewsRepository.php';

$isLoggedIn =
    isset($_SESSION['logged_in']) &&
    $_SESSION['logged_in'] === true;

$rolle = $_SESSION['rolle'] ?? '';
$activeNav = 'news';

$fehler = '';
$newsListe = [];

try {
    $db = Database::getInstance()->getConnection();
    $newsRepository = new NewsRepository($db);
    $newsListe = $newsRepository->getVeroeffentlicht(50);
} catch (Throwable $exception) {
    $fehler = 'Die News konnten momentan nicht geladen werden.';
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/services.css">

    <title>News - Bella Beauty</title>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main>

        <header class="services-page-header">
            <div class="container">
                <h1>Salon News</h1>
                <p>Neuigkeiten, Aktionen und Aktuelles direkt aus deinem Bella Beauty Salon.</p>
            </div>
        </header>

        <?php if ($fehler !== ''): ?>

            <section class="category-section">
                <p><?= htmlspecialchars($fehler) ?></p>
            </section>

        <?php elseif ($newsListe === []): ?>

            <section class="category-section">
                <p>Aktuell gibt es keine News.</p>
            </section>

        <?php else: ?>

            <section class="category-section">

                <div class="service-cards-grid">

                    <?php foreach ($newsListe as $news): ?>

                        <div class="service-item-card">

                            <?php if (!empty($news['photo_url'])): ?>
                                <div class="service-item-card__image" style="background-image: url('<?= htmlspecialchars($news['photo_url']) ?>')"></div>
                            <?php else: ?>
                                <div class="service-item-card__image service-item-card__image--placeholder">📰</div>
                            <?php endif; ?>

                            <div class="service-item-card__body">
                                <h3><?= htmlspecialchars($news['title']) ?></h3>
                                <p class="service-item-card__desc"><?= nl2br(htmlspecialchars($news['content'])) ?></p>
                                <span class="service-item-card__duration">
                                    📅 <?= date('d.m.Y', strtotime($news['created_at'])) ?>
                                </span>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endif; ?>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>