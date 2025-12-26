<?php
// add_user.php – add a new employee (admin only)

require 'auth_admin.php';   // starts session + admin-only guard
require 'db.php';           // PDO $pdo

$error = '';
$departments = [];

// Load departments for dropdown
try {
    $stmt = $pdo->query("SELECT department_id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
    $error = 'Could not load departments.';
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $department_id = (int)($_POST['department_id'] ?? 0);
    $vm_ip         = trim($_POST['vm_ip'] ?? '');

    // Force employee role (prevents any form tampering)
    $role = 'employee';

    // Clean name
    $name = preg_replace('/\s+/', ' ', $name);
    if (function_exists('mb_strtolower')) {
        $name = ucwords(mb_strtolower($name, 'UTF-8'));
    } else {
        $name = ucwords(strtolower($name));
    }

    // Normalize email
    $email = strtolower($email);

    // Basic validation
    if ($name === '' || $email === '' || !$department_id) {
        $error = 'Please fill in name, email, and department.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/@engin\.local$/', $email)) {
        $error = 'Employee email must be under @engin.local.';
    } elseif ($vm_ip !== '' && !filter_var($vm_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $error = 'Please enter a valid IPv4 address (e.g. 192.168.56.105) or leave it blank.';
    }

    // Check department exists
    if ($error === '') {
        try {
            $deptCheck = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_id = ?");
            $deptCheck->execute([$department_id]);
            if (!$deptCheck->fetchColumn()) {
                $error = 'The selected department does not exist.';
            }
        } catch (Exception $e) {
            $error = 'Error checking department. Please try again.';
        }
    }

    // Insert employee
    if ($error === '') {
        try {
            // Unique email?
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetchColumn()) {
                $error = 'This email is already used for another user.';
            } else {
               
                $placeholderPassword = bin2hex(random_bytes(8));

                $insert = $pdo->prepare("
                    INSERT INTO users (email, name, role, department_id, password, vm_ip)
                    VALUES (:email, :name, :role, :department_id, :password, :vm_ip)
                ");
                $insert->execute([
                    ':email'         => $email,
                    ':name'          => $name,
                    ':role'          => $role,
                    ':department_id' => $department_id,
                    ':password'      => $placeholderPassword,
                    ':vm_ip'         => $vm_ip === '' ? null : $vm_ip,
                ]);

                header('Location: users.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'There was a problem saving the user. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User – EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Shared admin layout styles -->
    <link rel="stylesheet" href="css/admin.css">
    <!-- add-user specific styles -->
    <link rel="stylesheet" href="css/add_user.css">
</head>
<body class="no-admin-header">

<main class="page-container add-user-page">
    <div class="add-user-header-row">
        <div>
            <h1 class="page-title">Add User</h1>
            <p class="page-subtitle">Create a new employee account for EnginGuard simulations.</p>
        </div>

    </div>

    <?php if ($error): ?>
        <p class="login-error">
            <?= htmlspecialchars($error, ENT_QUOTES) ?>
        </p>
    <?php endif; ?>

    <div class="add-user-card">
        <form method="post" class="add-user-form" autocomplete="off">
            <div class="add-user-grid">
                <div class="form-column">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter full name"
                            value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES) ?>"
                            autocorrect="off"
                            autocapitalize="off"
                            spellcheck="false"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Work Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="employee@engin.local"
                            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>"
                            autocorrect="off"
                            autocapitalize="off"
                            spellcheck="false"
                            required
                        >
                    </div>
                </div>

                <div class="form-column">
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int)$dept['department_id'] ?>"
                                    <?= (isset($_POST['department_id']) && (int)$_POST['department_id'] === (int)$dept['department_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="vm_ip">Employee VM IP (optional)</label>
                        <input
                            type="text"
                            id="vm_ip"
                            name="vm_ip"
                            placeholder="e.g. 192.168.56.105"
                            value="<?= htmlspecialchars($_POST['vm_ip'] ?? '', ENT_QUOTES) ?>"
                            autocorrect="off"
                            autocapitalize="off"
                            spellcheck="false"
                        >
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="Employee" disabled>
                    </div>
                </div>
            </div>

            <div class="form-actions-row">
                <a href="users.php" class="secondary-btn">Cancel</a>
                <button type="submit" class="primary-btn">Save User</button>
            </div>
        </form>
    </div>
</main>

<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
