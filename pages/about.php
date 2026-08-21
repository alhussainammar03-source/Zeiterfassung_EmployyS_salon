<?php

session_start();

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/employeeRepository.php';

$isLoggedIn =
    isset($_SESSION['logged_in']) &&
    $_SESSION['logged_in'] === true;

$rolle = $_SESSION['rolle'] ?? '';
$activeNav = 'about';

$team = [];
$fehler = '';

try {
    $db = Database::getInstance()->getConnection();
    $employeeRepository = new employeeRepository($db);
    $team = $employeeRepository->getAllActiveEmployees();
} catch (Throwable $exception) {
    $fehler = 'Das Team konnte momentan nicht geladen werden.';
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/about.css">

    <title>Über uns - Bella Beauty</title>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main>

        <section class="about-hero">
            <h1>Über Bella Beauty</h1>
            <p>Ihr Zentrum für professionelle Kosmetik und Wellness – seit vielen Jahren mit Herz und Handwerk für Ihre Schönheit im Einsatz.</p>
        </section>

        <section class="about-story">
            <div class="about-story__image" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAk7kv4_bDqoe35q6CdU9UAj-N8F1AYBk0GMvxm7eLJV1f0oeGN2hx_PPhDWEKDS4TKnETN_I8x7bmz2oqo-Vh2GBDquW53ann31F6z0rdXWfyGMBTuv4v3IV14yu_-T9GPJA6qlBAL-tFc0tA9iKH7PLoB2Mcacfwxi5O3VY-3rYcRtMdv7h46Vj6H089VuhhdNkWKrKV0RfQOR5YPGkk5BwNDwinLRBXnFzL6hyYavYwh9hcVJHkF')"></div>

            <div class="about-story__text">
                <h2>Unsere Geschichte</h2>
                <p>Bella Beauty wurde mit einer klaren Vision gegründet: ein Ort zu schaffen, an dem sich jede und jeder rundum wohlfühlt und mit strahlender Ausstrahlung nach Hause geht.</p>
                <p>Was als kleines Studio begann, ist heute ein etabliertes Team aus erfahrenen Spezialistinnen und Spezialisten für Haare, Gesicht, Körper und Wellness.</p>
                <p>Unser Anspruch bleibt derselbe wie am ersten Tag: höchste Qualität, persönliche Beratung und eine Atmosphäre zum Entspannen.</p>
            </div>
        </section>

        <section class="about-stats">
            <div class="about-stats__grid">
                <div>
                    <div class="about-stat__value"><?= count($team) ?>+</div>
                    <div class="about-stat__label">Teammitglieder</div>
                </div>
                <div>
                    <div class="about-stat__value">4.9</div>
                    <div class="about-stat__label">Ø Kundenbewertung</div>
                </div>
                <div>
                    <div class="about-stat__value">500+</div>
                    <div class="about-stat__label">Zufriedene Kunden</div>
                </div>
                <div>
                    <div class="about-stat__value">100%</div>
                    <div class="about-stat__label">Premium-Produkte</div>
                </div>
            </div>
        </section>

        <section class="about-values">
            <h2>Wofür wir stehen</h2>

            <div class="about-values__grid">
                <div class="about-value-card">
                    <div class="about-value-card__icon">🌟</div>
                    <h3>Qualität</h3>
                    <p>Wir arbeiten ausschließlich mit hochwertigen, dermatologisch geprüften Produkten und modernster Technik.</p>
                </div>
                <div class="about-value-card">
                    <div class="about-value-card__icon">🤝</div>
                    <h3>Persönlich</h3>
                    <p>Jede Behandlung ist individuell auf Sie abgestimmt – keine Massenabfertigung, sondern echte Beratung.</p>
                </div>
                <div class="about-value-card">
                    <div class="about-value-card__icon">🎓</div>
                    <h3>Erfahrung</h3>
                    <p>Unser Team bildet sich regelmäßig weiter, um Ihnen stets die neuesten Techniken bieten zu können.</p>
                </div>
                <div class="about-value-card">
                    <div class="about-value-card__icon">🧘</div>
                    <h3>Entspannung</h3>
                    <p>Bei uns geht es nicht nur um Schönheit, sondern auch um eine Auszeit vom Alltag.</p>
                </div>
            </div>
        </section>

        <section class="about-team">
            <h2>Unser Team</h2>
            <p>Lernen Sie die Menschen kennen, die sich um Ihre Schönheit kümmern.</p>

            <?php if ($fehler !== ''): ?>
                <p><?= htmlspecialchars($fehler) ?></p>
            <?php elseif ($team === []): ?>
                <p>Aktuell sind keine Teammitglieder hinterlegt.</p>
            <?php else: ?>
                <div class="about-team__grid">
                    <?php foreach ($team as $mitglied): ?>
                        <div class="about-team-card">
                            <?php if (!empty($mitglied['photo_url'])): ?>
                                <div class="about-team-card__photo" style="background-image: url('<?= htmlspecialchars($mitglied['photo_url']) ?>')"></div>
                            <?php else: ?>
                                <div class="about-team-card__photo about-team-card__photo--placeholder">
                                    <?= htmlspecialchars(mb_substr($mitglied['vor_name'], 0, 1) . mb_substr($mitglied['nach_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <h3><?= htmlspecialchars($mitglied['vor_name'] . ' ' . $mitglied['nach_name']) ?></h3>
                            <span><?= htmlspecialchars($mitglied['position'] ?? 'Team-Mitglied') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="about-cta">
            <a href="<?= $isLoggedIn ? '../customer/book_appointment.php' : 'register.php' ?>" class="btn-primary">
                Jetzt Termin buchen
            </a>
        </section>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>