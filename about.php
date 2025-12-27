<?php
// about.php – public page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts (shared typography) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Shared header styles -->
    <link rel="stylesheet" href="css/header.css">

    <!-- About page specific styles -->
    <link rel="stylesheet" href="css/about.css">
</head>
<body>

<!-- Shared public header (reused across all public pages) -->
<?php include 'header.php'; ?>

<main>

    <!-- Hero section (page title + intro) -->
    <section class="hero" aria-labelledby="about-title">
        <div class="hero-inner">
            <div class="hero-text">
                <h1 id="about-title">About EnginGuard</h1>
                <p class="hero-subtitle">
                    EnginGuard helps organizations reduce phishing risks through simulation,
                    awareness, and clear reporting.
                </p>
            </div>
        </div>
    </section>

    <div class="about-wrapper">

        <!-- Short introduction text -->
        <section class="about-intro">
            <p>
                Empowering organizations to reduce phishing risks through education, simulation,
                and real-time insights.
            </p>
        </section>

        <!-- Core information cards -->
        <section class="about-grid" aria-label="About EnginGuard overview">

            <!-- Mission card -->
            <article class="about-card">
                <h3 class="about-card-title">Our Mission</h3>
                <p class="about-card-text">
                    EnginGuard aims to strengthen cybersecurity awareness by helping organizations
                    train their employees to recognize phishing threats before they cause harm.
                    The platform combines realistic simulations, instant feedback, and clear analytics
                    to build a stronger security culture.
                </p>
            </article>

            <!-- Approach card -->
            <article class="about-card">
                <h3 class="about-card-title">Our Approach</h3>
                <p class="about-card-text">
                    EnginGuard focuses on simplicity, clarity, and real results. Employees learn by doing,
                    not by reading long manuals. Every interaction becomes a learning opportunity, helping
                    organizations grow safer over time.
                </p>
            </article>

            <!-- Target audience card -->
            <article class="about-card">
                <h3 class="about-card-title">Who We Help</h3>
                <p class="about-card-text">
                    EnginGuard is designed for small and medium businesses, IT teams, educational
                    institutions, and any organization that wants to improve employee cybersecurity awareness.
                </p>
            </article>

        </section>

        <!-- Why EnginGuard section -->
        <section class="about-why" aria-labelledby="why-title">
            <h2 class="about-why-title" id="why-title">Why EnginGuard?</h2>

            <div class="about-why-grid">

                <!-- Feature: Realistic simulations -->
                <div class="about-why-card">
                    <div class="about-why-icon" aria-hidden="true">
                        <svg width="40" height="40" viewBox="0 0 24 24">
                            <rect x="3" y="5" width="18" height="14" rx="2" ry="2"
                                  fill="none" stroke="#f97316" stroke-width="1.7"/>
                            <polyline points="4,7 12,12 20,7"
                                      fill="none" stroke="#f97316" stroke-width="1.7"/>
                        </svg>
                    </div>
                    <h3 class="about-why-card-title">Realistic Simulations</h3>
                    <p class="about-why-text">
                        Phishing emails that look and feel real, using safe links and
                        controlled scenarios based on modern attack patterns.
                    </p>
                </div>

                <!-- Feature: Instant awareness -->
                <div class="about-why-card">
                    <div class="about-why-icon" aria-hidden="true">
                        <svg width="40" height="40" viewBox="0 0 24 24">
                            <path d="M12 3L5 6v5c0 4.4 2.7 8.1 7 9 4.3-0.9 7-4.6 7-9V6l-7-3z"
                                  fill="none" stroke="#f97316" stroke-width="1.7"/>
                            <polyline points="8,12 11,15 16,10"
                                      fill="none" stroke="#f97316" stroke-width="1.7"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="about-why-card-title">Instant Awareness</h3>
                    <p class="about-why-text">
                        Unsafe clicks trigger warning pages, awareness tips, and short quizzes
                        so that every mistake becomes a learning moment.
                    </p>
                </div>

                <!-- Feature: Clear analytics -->
                <div class="about-why-card">
                    <div class="about-why-icon" aria-hidden="true">
                        <svg width="40" height="40" viewBox="0 0 24 24">
                            <line x1="4" y1="19" x2="20" y2="19" stroke="#f97316" stroke-width="1.7"/>
                            <rect x="6" y="11" width="3" height="8"
                                  fill="none" stroke="#f97316" stroke-width="1.7"/>
                            <rect x="11" y="8" width="3" height="11"
                                  fill="none" stroke="#f97316" stroke-width="1.7"/>
                            <rect x="16" y="5" width="3" height="14"
                                  fill="none" stroke="#f97316" stroke-width="1.7"/>
                        </svg>
                    </div>
                    <h3 class="about-why-card-title">Clear Analytics</h3>
                    <p class="about-why-text">
                        Dashboards and reports highlight high-risk users, vulnerable departments,
                        and training progress over time.
                    </p>
                </div>

            </div>
        </section>
    </div>
</main>

<!-- Shared footer -->
<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
