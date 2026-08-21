<?php

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../repositories/EmployeeRepository.php';
require_once __DIR__ . '/../includes/Csrf.php';

Auth::requireAdmin();

$message = '';
$error = '';

try {
    $pdo = Database::getInstance()->getConnection();
    $employeeRepository = new employeeRepository($pdo);

    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $employees = $employeeRepository->searchEmployees($search);
    } else {
        $employees = $employeeRepository->getAllEmployees();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status_umschalten') {
        if (Csrf::verify($_POST['csrf_token'] ?? null)) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $neuerStatus = ($_POST['neuer_status'] ?? '') === 'aktiv' ? 'aktiv' : 'inaktiv';

            if ($id) {
                $employeeRepository->changeStatus($id, $neuerStatus);
            }
        }
        header('Location: employees.php' . ($search !== '' ? '?search=' . urlencode($search) : ''));
        exit;
    }

    $statusFilter = $_GET['status'] ?? 'alle';

    if ($statusFilter === 'aktiv' || $statusFilter === 'inaktiv') {
        $employees = array_values(array_filter(
            $employees,
            fn($e) => $e['status'] === $statusFilter
        ));
    }

    if (isset($_GET['created'])) {
        $message = 'Der Mitarbeiter wurde erfolgreich hinzugefügt.';

        if (isset($_GET['foto_fehler'])) {
            $message .= ' Das Profilfoto konnte allerdings nicht hochgeladen '
                . 'werden – du kannst es beim Bearbeiten erneut versuchen.';
        }
    }

    if (isset($_GET['updated'])) {
        $message = 'Der Mitarbeiter wurde erfolgreich bearbeitet.';
    }

    if (isset($_GET['deleted'])) {
        $message = 'Der Mitarbeiter wurde erfolgreich gelöscht.';
    }
} catch (PDOException $exception) {
    $employees = [];
    $error = $exception->getMessage();
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitarbeiterverwaltung - Admin Bereich</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/home_page.css">
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="../style/employees_admin.css">
    <link rel="stylesheet" href="../style/header.css">
</head>

<body>

    <?php $activeNav = '';
    include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-container">

        <div class="page-header">
            <div>
                <h1>Mitarbeiterverwaltung</h1>
                <p style="color: var(--bella-on-surface-variant); margin: 4px 0 0 0;">
                    Verwalten Sie Ihr Team, Rollen und Status.
                </p>
            </div>
            <div>
                <a href="../admin/employee_create.php" class="emp-add-btn">+ Mitarbeiter hinzufügen</a>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="emp-toolbar">
            <form method="get" class="emp-search-form" style="margin-bottom: 0;">
                <input
                    type="search"
                    name="search"
                    value="<?= htmlspecialchars($search ?? '') ?>"
                    placeholder="🔍 Suchen...">

                <select name="status" class="emp-filter-select" onchange="this.form.submit()">
                    <option value="alle" <?= $statusFilter === 'alle' ? 'selected' : '' ?>>Alle</option>
                    <option value="aktiv" <?= $statusFilter === 'aktiv' ? 'selected' : '' ?>>Nur Aktive</option>
                    <option value="inaktiv" <?= $statusFilter === 'inaktiv' ? 'selected' : '' ?>>Nur Inaktive</option>
                </select>

                <button type="submit">Filtern</button>

                <?php if (($search ?? '') !== '' || $statusFilter !== 'alle'): ?>
                    <a href="employees.php">Zurücksetzen</a>
                <?php endif; ?>
            </form>

            <div class="emp-toolbar__right">
                <a href="employees_export.php" class="emp-export-btn">⬇ Export</a>
            </div>
        </div>

        <div class="emp-table-wrapper">
            <table class="emp-table">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Kontakt</th>
                        <th>Rolle</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($employees === []): ?>
                        <tr>
                            <td colspan="7">Keine Mitarbeiter gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($employee['photo_url'])): ?>
                                        <img
                                            src="<?= htmlspecialchars($employee['photo_url']) ?>"
                                            alt="Foto von <?= htmlspecialchars($employee['vor_name']) ?>"
                                            class="employee-thumbnail">
                                    <?php else: ?>
                                        <span class="employee-thumbnail employee-thumbnail--placeholder">
                                            <?= htmlspecialchars(
                                                mb_substr($employee['vor_name'], 0, 1)
                                                    . mb_substr($employee['nach_name'], 0, 1)
                                            ) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="emp-id">#EMP-<?= str_pad((string) $employee['id'], 3, '0', STR_PAD_LEFT) ?></td>

                                <td class="emp-name">
                                    <?= htmlspecialchars($employee['vor_name'] . ' ' . $employee['nach_name']) ?>
                                </td>

                                <td>
                                    <span class="emp-contact__email"><?= htmlspecialchars($employee['email']) ?></span>
                                    <span class="emp-contact__phone"><?= htmlspecialchars($employee['telefon'] ?? '') ?></span>
                                </td>

                                <td>
                                    <?php if (!empty($employee['position'])): ?>
                                        <span class="emp-role-badge"><?= htmlspecialchars($employee['position']) ?></span>
                                    <?php else: ?>
                                        <span class="emp-role-badge"><?= htmlspecialchars(ucfirst($employee['role'] ?? '')) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <form method="post" style="display:inline;">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="status_umschalten">
                                        <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                        <input type="hidden" name="neuer_status" value="<?= $employee['status'] === 'aktiv' ? 'inaktiv' : 'aktiv' ?>">

                                        <label class="emp-toggle" title="<?= $employee['status'] === 'aktiv' ? 'Deaktivieren' : 'Aktivieren' ?>">
                                            <input
                                                type="checkbox"
                                                <?= $employee['status'] === 'aktiv' ? 'checked' : '' ?>
                                                onchange="this.closest('form').submit()">
                                            <span class="emp-toggle__slider"></span>
                                        </label>
                                    </form>
                                </td>

                                <td class="emp-actions">
                                    <a href="employee_edit.php?id=<?= (int) $employee['id'] ?>" class="emp-icon-btn" title="Bearbeiten">✏️</a>

                                    <form
                                        method="post"
                                        action="employee_delete.php"
                                        onsubmit="return confirm('Mitarbeiter wirklich löschen?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                        <button type="submit" class="emp-icon-btn emp-icon-btn--delete" title="Löschen">🗑️</button>
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