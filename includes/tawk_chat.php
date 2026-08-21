<?php

require_once __DIR__ . '/Env.php';

Env::load(__DIR__ . '/../.env');

$tawkPropertyId = Env::get('TAWK_PROPERTY_ID', '');
$tawkWidgetId = Env::get('TAWK_WIDGET_ID', '');

// Nur einbinden, wenn beide IDs in der .env gesetzt sind
if ($tawkPropertyId !== '' && $tawkWidgetId !== ''):
?>
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {};
        var Tawk_LoadStart = new Date();

        (function() {
            var s1 = document.createElement("script");
            var s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/<?= htmlspecialchars($tawkPropertyId, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($tawkWidgetId, ENT_QUOTES, 'UTF-8') ?>';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>
<?php endif; ?>