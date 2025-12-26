<?php
// home.php – public landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EnginGuard – Phishing Awareness Training</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Shared public header styles -->
    <link rel="stylesheet" href="css/header.css">

    <!-- Home page specific styles -->
    <link rel="stylesheet" href="css/home.css">
</head>
<body>

<!-- Shared public header -->
<?php include 'header.php'; ?>

<main>

    <!-- Hero / introduction section -->
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-text">
                <h1>Empower Your Team to Outsmart Phishing Attacks</h1>

                <p class="hero-subtitle">
                    Send simulated phishing emails, monitor employee actions, and provide instant training.
                </p>

                <!-- Primary call-to-action buttons -->
                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary">Start Training</a>
                    <a href="how-it-works.php" class="btn btn-outline">View Features</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Platform feature overview -->
    <section class="features-section">
        <div class="features-grid">

            <!-- Feature card -->
            <div class="feature-card">
                <h3>Phishing Simulation</h3>
                <p>Simulated phishing emails with behaviour tracking.</p>
            </div>

            <!-- Feature card -->
            <div class="feature-card">
                <h3>Real-Time Analytics</h3>
                <p>Campaign performance and risk reporting.</p>
            </div>

            <!-- Feature card -->
            <div class="feature-card">
                <h3>Awareness & Quizzes</h3>
                <p>Training content triggered by unsafe actions.</p>
            </div>

        </div>
    </section>

</main>

<!-- Page footer -->
<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
