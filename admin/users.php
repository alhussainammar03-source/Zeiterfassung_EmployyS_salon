<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/CustomerRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$message = '';
$error = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $customerRepository = new CustomerRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $error = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        } elseif (($_POST['action'] ?? '') === 'status_aendern') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $neuerStatus = $_POST['status'] ?? '';

            if ($id) {
                $customerRepository->changeStatus($id, $neuerStatus);
            }

            header('Location: users.php' . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
            exit;
        } elseif (($_POST['action'] ?? '') === 'loeschen') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id) {
                try {
                    $customerRepository->deleteCustomer($id);
                    header('Location: users.php?geloescht=1');
                    exit;
                } catch (PDOException $exception) {
                    $error = 'Der Kunde kann möglicherweise wegen vorhandener '
                        . 'Termine nicht gelöscht werden. Setze ihn stattdessen '
                        . 'auf inaktiv.';
                }
            }
        }
    }

    $search = trim($_GET['search'] ?? '');
    $statusFilter = $_GET['status'] ?? 'alle';

    if ($search !== '') {
        $customers = $customerRepository->searchCustomers($search);
    } else {
        $customers = $customerRepository->getAllCustomers();
    }

    if ($statusFilter === 'aktiv' || $statusFilter === 'inaktiv') {
        $customers = array_values(array_filter(
            $customers,
            fn($c) => $c['status'] === $statusFilter
        ));
    }

    if (isset($_GET['status_geaendert'])) {
        $message = 'Der Status wurde erfolgreich geändert.';
    }

    if (isset($_GET['updated'])) {
        $message = 'Der Kunde wurde erfolgreich aktualisiert.';
    }

    if (isset($_GET['geloescht'])) {
        $message = 'Der Kunde wurde erfolgreich gelöscht.';
    }
} catch (PDOException $exception) {
    $customers = [];
    $error = 'Es ist ein Datenbankfehler aufgetreten.';
}

$avatarFarben = ['#d63384', '#a92a6c', '#655974', '#22c55e', '#0ea5e9', '#f59e0b'];

function initialen(string $vorName, string $nachName): string
{
    return mb_strtoupper(mb_substr($vorName, 0, 1) . mb_substr($nachName, 0, 1));
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kundenverwaltung - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/customers_admin.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Kundenverwaltung</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Übersicht und Verwaltung aller Salon-Kunden.
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="cust-toolbar">
            <form method="get" class="cust-search-form">
                <input
                    type="search"
                    name="search"
                    value="<?= htmlspecialchars($search ?? '') ?>"
                    placeholder="🔍 Kunden suchen nach Name, E-Mail oder ID...">

                <select name="status" class="emp-filter-select" onchange="this.form.submit()" style="width:auto; flex-shrink:0; padding:9px 14px; border:1px solid var(--bella-outline-variant,#dfbec8); border-radius:999px; font-size:13px; background:white;">
                    <option value="alle" <?= $statusFilter === 'alle' ? 'selected' : '' ?>>Alle</option>
                    <option value="aktiv" <?= $statusFilter === 'aktiv' ? 'selected' : '' ?>>Nur Aktive</option>
                    <option value="inaktiv" <?= $statusFilter === 'inaktiv' ? 'selected' : '' ?>>Nur Inaktive</option>
                </select>

                <button type="submit">Filtern</button>

                <?php if (($search ?? '') !== '' || $statusFilter !== 'alle'): ?>
                    <a href="users.php">Zurücksetzen</a>
                <?php endif; ?>
            </form>

            <div class="cust-toolbar__right">
                <a href="users_export.php" class="cust-export-btn">⬇ Export</a>
            </div>
        </div>

        <div class="cust-table-wrapper">
            <table class="cust-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Telefon</th>
                        <th>Registriert am</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($customers === []): ?>
                        <tr>
                            <td colspan="7">Keine Kunden gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <?php $avatarFarbe = $avatarFarben[$customer['id'] % count($avatarFarben)]; ?>
                            <tr>
                                <td class="cust-id">#C-<?= str_pad((string) $customer['id'], 4, '0', STR_PAD_LEFT) ?></td>

                                <td>
                                    <div class="cust-name-row">
                                        <div class="cust-avatar" style="background: <?= $avatarFarbe ?>;">
                                            <?= htmlspecialchars(initialen($customer['vor_name'], $customer['nach_name'])) ?>
                                        </div>
                                        <span class="cust-name"><?= htmlspecialchars($customer['vor_name'] . ' ' . $customer['nach_name']) ?></span>
                                    </div>
                                </td>

                                <td class="cust-email"><?= htmlspecialchars($customer['email']) ?></td>

                                <td><?= htmlspecialchars($customer['telefon1'] ?? '–') ?></td>

                                <td><?= date('d.m.Y', strtotime($customer['created_at'])) ?></td>

                                <td>
                                    <span class="cust-status-badge <?= $customer['status'] === 'aktiv' ? '' : 'cust-status-badge--inaktiv' ?>">
                                        <?= $customer['status'] === 'aktiv' ? 'Aktiv' : 'Inaktiv' ?>
                                    </span>
                                </td>

                                <td class="cust-actions">
                                    <a href="user_edit.php?id=<?= (int) $customer['id'] ?>" class="cust-icon-btn" title="Bearbeiten" style="color: var(--bella-primary); background: var(--bella-primary-light, #fce7f3);">✏️</a>

                                    <form method="post" style="display:inline;">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="status_aendern">
                                        <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $customer['status'] === 'aktiv' ? 'inaktiv' : 'aktiv' ?>">

                                        <label class="cust-toggle" title="<?= $customer['status'] === 'aktiv' ? 'Deaktivieren' : 'Aktivieren' ?>">
                                            <input
                                                type="checkbox"
                                                <?= $customer['status'] === 'aktiv' ? 'checked' : '' ?>
                                                onchange="this.closest('form').submit()">
                                            <span class="cust-toggle__slider"></span>
                                        </label>
                                    </form>

                                    <form
                                        method="post"
                                        style="display:inline;"
                                        onsubmit="return confirm('Kunde wirklich löschen?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="loeschen">
                                        <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
                                        <button class="cust-icon-btn" type="submit" title="Löschen">🗑️</button>
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