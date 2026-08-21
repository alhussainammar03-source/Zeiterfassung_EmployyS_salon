<?php

session_start();

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/ServiceRepository.php';

$isLoggedIn =
    isset($_SESSION['logged_in']) &&
    $_SESSION['logged_in'] === true;

$rolle = $_SESSION['rolle'] ?? '';
$vorName = $_SESSION['vor_name'] ?? '';
$activeNav = 'services';

$fehler = '';
$servicesNachKategorie = [];

try {
    $db = Database::getInstance()->getConnection();
    $serviceRepository = new ServiceRepository($db);

    $services = $serviceRepository->getAllServices();
    $beliebtesteIds = $serviceRepository->getBeliebtesteServiceIds(3);

    foreach ($services as $service) {
        $kategorie = $service['category'] ?? '';
        $kategorie = $kategorie !== '' ? $kategorie : 'Weitere Leistungen';
        $servicesNachKategorie[$kategorie][] = $service;
    }
} catch (Throwable $exception) {
    $fehler = 'Die Dienstleistungen konnten momentan nicht geladen werden.';
    $beliebtesteIds = [];
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

    <title>Unsere Leistungen | Bella Beauty</title>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main>

        <header class="services-page-header">
            <div class="container">
                <h1>Unsere Services</h1>
                <p>Entdecken Sie unser breites Angebot an modernster Behandlungstechnik und exklusiven Beauty-Programmen.</p>
            </div>
        </header>

        <?php if ($fehler !== ''): ?>

            <section class="category-section">
                <p><?= htmlspecialchars($fehler) ?></p>
            </section>

        <?php elseif ($servicesNachKategorie === []): ?>

            <section class="category-section">
                <p>Aktuell sind keine Dienstleistungen hinterlegt.</p>
            </section>

        <?php else: ?>

            <?php foreach ($servicesNachKategorie as $kategorieName => $serviceGruppe): ?>

                <section class="category-section">

                    <h2 class="category-section__title"><?= htmlspecialchars($kategorieName) ?></h2>

                    <div class="service-cards-grid">

                        <?php foreach ($serviceGruppe as $service): ?>

                            <div class="service-item-card">

                                <?php if (!empty($service['photo_url'])): ?>
                                    <div class="service-item-card__image" style="background-image: url('<?= htmlspecialchars($service['photo_url']) ?>')"></div>
                                <?php else: ?>
                                    <div class="service-item-card__image service-item-card__image--placeholder">💇</div>
                                <?php endif; ?>

                                <div class="service-item-card__body">

                                    <div class="service-item-card__top">
                                        <h3>
                                            <?= htmlspecialchars($service['name']) ?>
                                            <?php if (in_array((int) $service['id'], $beliebtesteIds, true)): ?>
                                                <span class="popular-tag">⭐ Beliebt</span>
                                            <?php endif; ?>
                                        </h3>
                                        <span class="service-item-card__price">
                                            <?= number_format((float) $service['price'], 2, ',', '.') ?> €
                                        </span>
                                    </div>

                                    <?php if (!empty($service['description'])): ?>
                                        <p class="service-item-card__desc"><?= htmlspecialchars($service['description']) ?></p>
                                    <?php endif; ?>

                                    <div class="service-item-card__footer">
                                        <span class="service-item-card__duration">
                                            🕒 <?= (int) $service['duration_minutes'] ?> Min.
                                        </span>

                                        <a
                                            class="service-item-card__book-btn"
                                            href="<?= $isLoggedIn
                                                        ? '../customer/book_appointment.php?service_id=' . (int) $service['id']
                                                        : 'register.php' ?>">
                                            Buchen
                                        </a>
                                    </div>

                                </div>
                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

        <?php endif; ?>

        <section class="services-cta">
            <div class="services-cta__box">
                <h3>Bereit für Ihre Auszeit?</h3>
                <p>Werden Sie jetzt Kunde bei Bella Beauty und buchen Sie Ihren ersten Termin.</p>
                <a
                    href="<?= $isLoggedIn ? '../customer/book_appointment.php' : 'register.php' ?>"
                    class="btn-outline">
                    <?= $isLoggedIn ? 'Jetzt Termin buchen' : 'Jetzt Kunde werden' ?>
                </a>
            </div>
        </section>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>