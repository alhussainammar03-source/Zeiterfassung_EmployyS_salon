<?php
<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

// dein bisheriger Code...
session_start();

$isLoggedIn =
    isset($_SESSION['logged_in']) &&
    $_SESSION['logged_in'] === true;

$rolle = $_SESSION['rolle'] ?? '';
$vorName = $_SESSION['vor_name'] ?? '';
$activeNav = 'home';

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">

    <title>Bella Beauty</title>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main>

        <section class="hero">
            <div class="hero__grid">
                <div>
                    <span class="hero__eyebrow">Willkommen bei Bella Beauty</span>

                    <?php if ($isLoggedIn): ?>
                        <h1 class="hero__title">
                            Willkommen zurück, <?= htmlspecialchars($vorName, ENT_QUOTES, 'UTF-8') ?>!
                        </h1>
                    <?php else: ?>
                        <h1 class="hero__title">Ihre Schönheit in professionellen Händen</h1>
                    <?php endif; ?>

                    <p class="hero__text">
                        Erleben Sie exklusive Behandlungen und modernste Technik in einer Atmosphäre purer Entspannung.
                    </p>

                    <div class="hero__actions">
                        <a href="<?= $isLoggedIn ? '../customer/book_appointment.php' : 'register.php' ?>" class="btn-primary">
                            Jetzt Termin buchen
                        </a>
                        <a href="services.php" class="btn-outline">Unsere Services</a>
                    </div>
                </div>

                <div class="hero__image-wrap">
                    <div class="hero__image" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAk7kv4_bDqoe35q6CdU9UAj-N8F1AYBk0GMvxm7eLJV1f0oeGN2hx_PPhDWEKDS4TKnETN_I8x7bmz2oqo-Vh2GBDquW53ann31F6z0rdXWfyGMBTuv4v3IV14yu_-T9GPJA6qlBAL-tFc0tA9iKH7PLoB2Mcacfwxi5O3VY-3rYcRtMdv7h46Vj6H089VuhhdNkWKrKV0RfQOR5YPGkk5BwNDwinLRBXnFzL6hyYavYwh9hcVJHkF')"></div>

                    <div class="hero__badge">
                        <div class="hero__badge-icon">★</div>
                        <div>
                            <strong>4.9/5 Sterne</strong>
                            <span>500+ Bewertungen</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="services-section">
            <div class="services-section__header">
                <h2>Unsere Schwerpunkte</h2>
                <p>Wir bieten Ihnen maßgeschneiderte Lösungen für Ihre individuelle Schönheitspflege.</p>
            </div>

            <div class="services-grid">

                <div class="service-card">
                    <div class="service-card__icon">✨</div>
                    <h3>Laser</h3>
                    <p>Dauerhafte Haarentfernung und Hautverjüngung mit neuester Lasertechnologie für perfekte Ergebnisse.</p>
                    <a href="services.php">Mehr erfahren →</a>
                </div>

                <div class="service-card">
                    <div class="service-card__icon">🧖</div>
                    <h3>Gesichtsbehandlung</h3>
                    <p>Individuelle Tiefenreinigung und Pflegekonzepte für einen strahlenden Teint und vitale Haut.</p>
                    <a href="services.php">Mehr erfahren →</a>
                </div>

                <div class="service-card">
                    <div class="service-card__icon">💆</div>
                    <h3>Massage</h3>
                    <p>Entspannung pur für Körper und Seele durch professionelle Massagetechniken in ruhiger Atmosphäre.</p>
                    <a href="services.php">Mehr erfahren →</a>
                </div>

            </div>
        </section>

        <section class="why-section">
            <div class="why-section__grid">

                <div class="why-collage">
                    <div class="why-collage__col">
                        <div class="why-collage__img why-collage__img--tall" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDn7qCvm1ULHJCj8S5aSDCktOMtiu_DC3SVYv-VRbwR2zECyMjctT_lztItRasHnoWlfUqSV2l2Zzo8iSTSiAZ98OK0yBzFPhIUuKjFm13OJ-6I42X-LeuQwhmRPdY6WfpfeDPkk6J74tJXdgqHNfYoYyRC55sgNpqi3JpmyUsCDVLjHHt7e2cz-d5J3zLb35JpLT7FR-Xa9cpJoN_MviNp8il4NL_O4p1oRDTogqXKne1sMVXN2q2F')"></div>
                        <div class="why-collage__img" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAVvuO_JlWLhcQ3ebrRfzlFWEuzHjFzCeVXKg3R3kL3TXPrpyAGPNFgaNirrqOvE47fFgJ5ktsd5NNYxZkS7TxSLwCDALoLlrCUi5YTCmaobntAXYgX2GgeIKm73VGezBL221LMafz7_UDb4E74Su8tysCOb1U3gpM3UrPSnFHbqRHtFe589S_c9NSfDslEsBrtlsnlI00TFhybG_aA6Yv7EzKBl9CLRw8-7IdCv6dXZU-8Y0ALPEbQ')"></div>
                    </div>
                    <div class="why-collage__col">
                        <div class="why-collage__img" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAKavlIL2PPaFJvdL8xZJWBE2iw6fWF91iSVJJMBQuOs-Wf3C5zRNtpHUPWI4b6j_4yQ3quvWKLv47y0wvWA-u1QQEnZTkKSqdyWEBl2qQwrQXZH3rinC8-9c5_XfWfzyGeK01jqLQ_i-7XtapqL6CF5EpNQnblzirOWacNyBQTtEkXTObltEKMxmsRA6b3w6HBaTXUqodIPP_MkiqsuOvNwc5hmbA13Dh8af7IXFCaCrsKpQhBeD4x')"></div>
                        <div class="why-collage__img why-collage__img--tall" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBMQ2fL0_2tW2EDP4tEF5QYvnizueInr6xdVpB7nTCK26M4m4jzfL2h4p_yGlUqReD6TPnVcv5a_QqIFtuZ6qNUKyk5Vd50yj4pqFkA8LyouoqQnIyTMwC0TpAruwYubZYdaHbq2hTfDiTbpbBqzbzZ8VO7KEdjWnnKa4pWAeKqdgG7j8FxfiAkKYrsJY0uc_u28Zub5CZ9H27ZLYASDAlOQfsImUMxgkpMPRQs6d6UGamjviP7u9KU')"></div>
                    </div>
                </div>

                <div class="why-content">
                    <h2>Warum Bella Beauty</h2>
                    <p>Wir setzen Maßstäbe in Sachen Schönheit und Wohlbefinden. Unsere Philosophie verbindet Fachkompetenz mit höchstem Serviceanspruch.</p>

                    <div class="why-list">
                        <div class="why-item">
                            <div class="why-item__check">✓</div>
                            <div>
                                <h4>Erfahrene Experten</h4>
                                <p>Unser Team besteht aus zertifizierten Spezialisten mit langjähriger Erfahrung.</p>
                            </div>
                        </div>
                        <div class="why-item">
                            <div class="why-item__check">✓</div>
                            <div>
                                <h4>Moderne Technik</h4>
                                <p>Wir investieren kontinuierlich in die neuesten und effektivsten Behandlungsmethoden.</p>
                            </div>
                        </div>
                        <div class="why-item">
                            <div class="why-item__check">✓</div>
                            <div>
                                <h4>Entspannte Atmosphäre</h4>
                                <p>Genießen Sie Ihre Auszeit vom Alltag in unseren stilvoll gestalteten Räumlichkeiten.</p>
                            </div>
                        </div>
                        <div class="why-item">
                            <div class="why-item__check">✓</div>
                            <div>
                                <h4>Beste Produkte</h4>
                                <p>Wir verwenden ausschließlich hochwertige, dermatologisch geprüfte Wirkstoffkosmetik.</p>
                            </div>
                        </div>
                    </div>

                    <a href="<?= $isLoggedIn ? '../customer/book_appointment.php' : 'register.php' ?>" class="btn-primary">
                        Überzeugen Sie sich selbst
                    </a>
                </div>

            </div>
        </section>

    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>