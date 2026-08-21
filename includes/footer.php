<footer class="site-footer">
    <div class="site-footer__grid">
        <div>
            <div class="site-footer__brand">Bella Beauty</div>
            <p>Ihr Zentrum für professionelle Kosmetik und Wellness. Wir freuen uns darauf, Sie in unserem Salon begrüßen zu dürfen.</p>
        </div>

        <div>
            <h5>Quick Links</h5>
            <ul>
                <li><a href="#">Impressum</a></li>
                <li><a href="#">Datenschutz</a></li>
                <li><a href="#">Kontakt</a></li>
            </ul>
        </div>

        <div class="site-footer__contact">
            <h5>Kontakt &amp; Öffnungszeiten</h5>
            <p>📍 Musterstraße 123, 12345 Berlin</p>
            <p>📞 +49 (0) 30 123 456 78</p>
            <p><strong>Mo - Fr: 09:00 - 19:00 Uhr</strong></p>
            <p><strong>Sa: 10:00 - 16:00 Uhr</strong></p>
        </div>
    </div>

    <div class="site-footer__bottom">
        © <?= date('Y') ?> Bella Beauty Salon. Alle Rechte vorbehalten.
    </div>
</footer>

<script src="<?php
                require_once __DIR__ . '/Auth.php';
                echo Auth::baseUrl();
                ?>/js/site-nav.js"></script>

<?php include_once __DIR__ . '/tawk_chat.php'; ?>