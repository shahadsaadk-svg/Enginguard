<?php
// admin_header.php – shared top navigation for admin-only pages

require 'auth_admin.php'; // Starts session + blocks non-admins

// Highlight active page in the nav
$currentPage = basename($_SERVER['PHP_SELF']);

// Display admin name on the right
$adminName = $_SESSION['user_name'] ?? 'EnginGuard Admin';
?>
<header class="admin-top-nav">
    <!-- LEFT: Logo (click to dashboard) -->
    <div class="admin-nav-left">
        <a href="dashboard.php" class="admin-logo-link">
            <img src="images/enginguard-logo.png" alt="EnginGuard Logo" class="admin-logo">
        </a>
    </div>

    <!-- CENTER: Admin navigation -->
    <nav class="admin-nav-links">
        <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="campaigns.php" class="<?= $currentPage === 'campaigns.php' ? 'active' : '' ?>">Campaigns</a>
        <a href="templates.php" class="<?= $currentPage === 'templates.php' ? 'active' : '' ?>">Templates</a>
        <a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">Users</a>
        <a href="reports.php" class="<?= $currentPage === 'reports.php' ? 'active' : '' ?>">Reports</a>
    </nav>

    <!-- RIGHT: Signed-in info + Logout -->
    <div class="admin-nav-right">
        <span class="admin-user-pill">
            <span class="admin-user-label">Signed in as</span>
            <span class="admin-user-name"><?= htmlspecialchars($adminName) ?></span>
        </span>

        <a href="logout.php" class="admin-logout-pill">Logout</a>
    </div>
</header>
