<?php
// how-it-works.php – public page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>How EnginGuard Works</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Page-specific styles -->
    <link rel="stylesheet" href="css/how-it-works.css">
</head>
<body>

<!-- Shared public header -->
<?php include 'header.php'; ?>

<main>
    <!-- HERO / PAGE INTRO -->
    <section class="hero" aria-labelledby="how-title">
        <div class="hero-inner">
            <div class="hero-text">
                <h1 id="how-title">How EnginGuard Works</h1>

                <p class="hero-subtitle">
                    A step-by-step process that converts phishing mistakes into training and insights.
                </p>

                <!-- Key capability highlights -->
                <div class="hero-chips" aria-label="Key capabilities">
                    <span class="chip">
                        <span class="chip-icon" aria-hidden="true">✉️</span>
                        Simulate emails
                    </span>
                    <span class="chip">
                        <span class="chip-icon" aria-hidden="true">⚠️</span>
                        Catch risky clicks
                    </span>
                    <span class="chip">
                        <span class="chip-icon" aria-hidden="true">📊</span>
                        See clear reports
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- STEP-BY-STEP WORKFLOW -->
    <section class="steps-section" aria-label="How it works steps">
        <div class="steps-grid">

            <!-- Step 1 -->
            <article class="step-card">
                <div class="step-badge">Step 1</div>
                <div class="step-icon-circle" aria-hidden="true">✉️</div>
                <h3 class="step-title">Launch a Phishing Campaign</h3>
                <p class="step-text">
                    Admins log in to the EnginGuard dashboard, choose a phishing-style email template,
                    select target employees or departments, and set the campaign start and end time.
                </p>
            </article>

            <!-- Step 2 -->
            <article class="step-card">
                <div class="step-badge">Step 2</div>
                <div class="step-icon-circle" aria-hidden="true">👥</div>
                <h3 class="step-title">Employees Interact</h3>
                <p class="step-text">
                    Employees receive the simulated email and may open, click, ignore, or report it.
                    Unsafe clicks lead to a warning page, awareness content, and a short quiz.
                </p>
            </article>

            <!-- Step 3 -->
            <article class="step-card">
                <div class="step-badge">Step 3</div>
                <div class="step-icon-circle" aria-hidden="true">📊</div>
                <h3 class="step-title">Track Results</h3>
                <p class="step-text">
                    EnginGuard records events in the database and shows them on the dashboard and reports pages.
