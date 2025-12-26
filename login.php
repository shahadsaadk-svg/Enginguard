<?php
// login.php – Admin login page

session_start();
require 'db.php';

$error = '';

// Remembered email 
$savedEmail = $_COOKIE['eng_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read inputs (backend)
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Basic validation
    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Fetch user by email 
            $stmt = $pdo->prepare("
                SELECT user_id, name, role, password
                FROM users
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check password (plain text for now)
            if ($user && ($user['password'] ?? '') === $password) {

                // Restrict login to admins only
                if (($user['role'] ?? '') !== 'admin') {
                    $error = 'Only admins can log in to EnginGuard.';
                } else {
                    // Start session 
                    $_SESSION['user_id']   = (int)$user['user_id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];

                    // Remember email (cookie) 
                    if (!empty($_POST['remember'])) {
                        setcookie('eng_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                    } else {
                        setcookie('eng_email', '', time() - 3600, '/', '', false, true);
                    }

                    // Redirect after success 
                    header('Location: dashboard.php');
                    exit;
                }

            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EnginGuard – Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Page styles -->
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

<!-- Public header  -->
<header class="top-nav">
    <div class="nav-left">
        <div class="logo-container">
            <img src="images/enginguard-logo.png" alt="EnginGuard Logo" class="main-logo">
        </div>
    </div>

    <nav class="nav-links">
        <a href="home.php">Home</a>
        <a href="how-it-works.php">How It Works</a>
        <a href="about.php">About Us</a>
        <a href="contact.php">Contact</a>
        <a href="login.php" class="active">Login</a>
    </nav>
</header>

<main>
    <div class="login-wrapper">
        <div class="login-card">
            <h1 class="login-title">Admin Login</h1>

            <!-- Login form -->
            <form method="POST" class="login-form" autocomplete="off">

                <!-- Email -->
                <div class="form-group">
                    <label>Email</label>
                    <input
                        type="email"
                        name="email"
                        class="input-field"
                        placeholder="Enter your admin email"
                        value="<?= htmlspecialchars($_POST['email'] ?? $savedEmail, ENT_QUOTES) ?>"
                        required
                    >
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label>Password</label>
                    <input
                        type="password"
                        name="password"
                        class="input-field"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <!-- Remember me -->
                <div class="login-actions">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" <?= ($savedEmail !== '') ? 'checked' : '' ?>>
                        Remember me
                    </label>
                </div>

                <!-- Submit -->
                <button type="submit" class="login-button">Log In</button>

                <!-- Error message (frontend display) -->
                <?php if ($error): ?>
                    <p class="login-error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

            </form>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
