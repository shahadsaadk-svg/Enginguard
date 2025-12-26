<?php
// users.php 

require 'auth_admin.php'; // Admin-only access
require 'db.php';         // PDO $pdo

/* ---------------------------
   1) Load employees (only)
---------------------------- */
$sql = "
    SELECT
        u.user_id,
        u.name,
        u.email,
        d.name AS department,
        u.role
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.role = 'employee'
    ORDER BY u.name
";
$stmt = $pdo->query($sql);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ---------------------------
   2) Simple stats
---------------------------- */
$totalEmployees = count($employees);

// Count distinct departments (ignore null/empty)
$deptSet = [];
foreach ($employees as $emp) {
    $deptName = trim((string)($emp['department'] ?? ''));
    if ($deptName !== '') {
        $deptSet[$deptName] = true;
    }
}
$totalDepartments = count($deptSet);

/* ---------------------------
   3) Departments list (filter)
---------------------------- */
$deptQuery = $pdo->query("SELECT name FROM departments ORDER BY name ASC");
$allDepartments = $deptQuery->fetchAll(PDO::FETCH_COLUMN) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users – EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Shared admin styles -->
    <link rel="stylesheet" href="css/admin.css">
    <!-- Page styles -->
    <link rel="stylesheet" href="css/users.css">
</head>
<body>

<?php include 'admin_header.php'; ?>

<main class="page-container users-page">

    <!-- ===== Banner Header ===== -->
    <section class="users-hero">
        <div class="users-hero-left">
            <h1 class="users-hero-title">Users</h1>
            <p class="users-hero-subtitle">
                Manage employee accounts and departments used for campaigns and reports.
            </p>

            <div class="users-hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-label">Total Employees</div>
                    <div class="hero-stat-value"><?= (int)$totalEmployees ?></div>
                </div>
                <div class="hero-stat hero-stat-blue">
                    <div class="hero-stat-label">Departments</div>
                    <div class="hero-stat-value"><?= (int)$totalDepartments ?></div>
                </div>
            </div>
        </div>

        <div class="users-hero-right">
            <a href="add_user.php" class="primary-pill-btn">
                <span class="btn-icon">+</span>
                <span>Add User</span>
            </a>

        </div>
    </section>

    <!-- ===== Layout: main + sidebar ===== -->
    <div class="users-grid">

        <!-- ===== Main column ===== -->
        <section class="users-main">

            <!-- Toolbar card -->
            <div class="users-toolbar-card">
                <div class="toolbar-row">
                    <div class="toolbar-block">
                        <label for="userSearch" class="search-label">Search</label>
                        <div class="search-input-wrapper">
                            <input
                                type="text"
                                id="userSearch"
                                class="search-input"
                                placeholder="Name, email, or department..."
                                autocomplete="off"
                            >
                            <button type="button" id="clearUserSearch" class="clear-search-btn">Clear</button>
                        </div>
                    </div>

                    <div class="toolbar-block toolbar-right">
                        <label for="deptFilter" class="search-label">Department</label>
                        <select id="deptFilter" class="dept-filter-select">
                            <option value="all" selected>All Departments</option>
                            <?php foreach ($allDepartments as $deptName): ?>
                                <?php
                                    $deptRawLower = strtolower(trim((string)$deptName));
                                    $deptSafe     = htmlspecialchars((string)$deptName, ENT_QUOTES);
                                ?>
                                <option value="<?= htmlspecialchars($deptRawLower, ENT_QUOTES) ?>">
                                    <?= $deptSafe ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="dept-filter-note">
                            Showing <?= (int)$totalDepartments ?> department<?= $totalDepartments === 1 ? '' : 's' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="users-table-wrapper">
                <div class="users-table-top">
                    <div class="users-table-title">Employee List</div>
                    <div class="users-table-hint">Click “Add User” to register more employees.</div>
                </div>

                <table class="users-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Role</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="4" class="empty-row">
                                No employees found. Click “Add User” to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                                // Raw values for filtering
                                $rawName  = (string)($emp['name'] ?? '');
                                $rawEmail = (string)($emp['email'] ?? '');
                                $rawDept  = (string)($emp['department'] ?? '');

                                // Safe display values
                                $nameSafe  = htmlspecialchars($rawName, ENT_QUOTES);
                                $emailSafe = htmlspecialchars($rawEmail, ENT_QUOTES);
                                $deptSafe  = htmlspecialchars(($rawDept !== '' ? $rawDept : '—'), ENT_QUOTES);
                                $roleSafe  = htmlspecialchars(ucfirst((string)($emp['role'] ?? 'employee')), ENT_QUOTES);

                                // Lowercase keys for JS filtering
                                $dataName  = strtolower(trim($rawName));
                                $dataEmail = strtolower(trim($rawEmail));
                                $dataDept  = strtolower(trim($rawDept));
                            ?>
                            <tr
                                data-name="<?= htmlspecialchars($dataName, ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($dataEmail, ENT_QUOTES) ?>"
                                data-dept="<?= htmlspecialchars($dataDept, ENT_QUOTES) ?>"
                            >
                                <td><?= $nameSafe ?></td>
                                <td class="email-cell"><?= $emailSafe ?></td>
                                <td>
                                    <?php if ($rawDept !== ''): ?>
                                        <span class="dept-pill"><?= htmlspecialchars($rawDept, ENT_QUOTES) ?></span>
                                    <?php else: ?>
                                        <span class="dept-pill dept-none">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-pill role-employee"><?= $roleSafe ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===== Sidebar ===== -->
        <aside class="users-side">

            <div class="side-card">
                <div class="side-card-title">Departments</div>
                <div class="side-card-subtitle">Quick overview of your org structure.</div>

                <?php if (empty($allDepartments)): ?>
                    <div class="side-empty">No departments found.</div>
                <?php else: ?>
                    <div class="dept-list">
                        <?php foreach ($allDepartments as $deptName): ?>
                            <span class="dept-chip"><?= htmlspecialchars($deptName, ENT_QUOTES) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            </div>

        </aside>

    </div>

</main>

<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

<script>
/* Simple client-side search + department filter */
(function () {
    const input    = document.getElementById('userSearch');
    const clearBtn = document.getElementById('clearUserSearch');
    const deptSel  = document.getElementById('deptFilter');
    const rows     = document.querySelectorAll('#usersTable tbody tr');

    function applyFilters() {
        const term = (input.value || '').trim().toLowerCase();
        const selectedDept = deptSel ? deptSel.value : 'all';

        rows.forEach(row => {
            const name  = row.getAttribute('data-name')  || '';
            const email = row.getAttribute('data-email') || '';
            const dept  = row.getAttribute('data-dept')  || '';

            const matchesSearch =
                !term ||
                name.includes(term) ||
                email.includes(term) ||
                dept.includes(term);

            const matchesDept =
                selectedDept === 'all' ? true : dept === selectedDept;

            row.style.display = (matchesSearch && matchesDept) ? '' : 'none';
        });
    }

    if (input) input.addEventListener('input', applyFilters);
    if (deptSel) deptSel.addEventListener('change', applyFilters);

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            if (deptSel) deptSel.value = 'all';
            applyFilters();
            input.focus();
        });
    }
})();
</script>

</body>
</html>
