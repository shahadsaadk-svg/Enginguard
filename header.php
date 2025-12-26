<!-- Shared public header -->
<header class="top-nav">

    <!-- Logo -->
    <div class="nav-left">
        <a href="home.php" class="logo-link">
            <img src="images/enginguard-logo.png" alt="EnginGuard Logo" class="main-logo">
        </a>
    </div>

    <!-- Primary navigation links -->
    <nav class="nav-links">
        <a href="home.php">Home</a>
        <a href="how-it-works.php">How It Works</a>
        <a href="about.php">About Us</a>
        <a href="contact.php">Contact</a>
        <a href="login.php">Login</a>
    </nav>

</header>

<!-- Auto-highlight active navigation link -->
<script>
  (function () {
    // Get current page filename (default to home.php)
    const currentPage = window.location.pathname.split("/").pop() || "home.php";

    // Select all nav links
    const links = document.querySelectorAll(".nav-links a:not(.nav-cta)");

    // Add 'active' class to the matching link
    links.forEach(link => {
      if (link.getAttribute("href") === currentPage) {
        link.classList.add("active");
      }
    });
  })();
</script>
