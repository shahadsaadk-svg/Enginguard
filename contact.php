<?php
// contact.php – public contact form (sends message to EnginGuard inbox)

/* ---------- Backend logic (process form POST) ---------- */
$success = '';
$error   = '';
$to      = 'enginguard@engin.local'; // destination mailbox (internal)

/* Sanitizes text to reduce header injection risk */
function clean_text(string $value): string {
    $value = trim($value);
    $value = str_replace(["\r", "\n"], ' ', $value); // prevent header injection
    return $value;
}

/* Handle form submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Read & sanitize inputs
    $name    = trim($_POST['full_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // 2) Validate inputs
    if ($name === '' || $email === '' || $message === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // 3) Prepare email content
        $safeName  = clean_text($name);
        $safeEmail = clean_text($email);

        $subject = 'New Contact Message – EnginGuard';

        $body =
            "A new contact form message was submitted:\n\n" .
            "---------------------------------------\n" .
            "Name: {$safeName}\n" .
            "Email: {$safeEmail}\n" .
            "---------------------------------------\n\n" .
            "Message:\n{$message}\n\n" .
            "---------------------------------------\n";

        // 4) Email headers (From = platform, Reply-To = user)
        $headers  = "From: EnginGuard <{$to}>\r\n";
        $headers .= "Reply-To: {$safeEmail}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        // 5) Send email
        $sent = @mail($to, $subject, $body, $headers);

        if ($sent) {
            $success = 'Your message has been sent successfully!';
            // Clear values after success
            $_POST['full_name'] = '';
            $_POST['email']     = '';
            $_POST['message']   = '';
        } else {
            $error = 'Message could not be sent. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us – EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts (frontend asset) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Shared header styles (frontend) -->
    <link rel="stylesheet" href="css/header.css">

    <!-- Page styles (frontend) -->
    <link rel="stylesheet" href="css/contact.css">
</head>
<body>

<!-- Shared public header (frontend include) -->
<?php include 'header.php'; ?>

<main>
    <!-- HERO -->
    <section class="contact-hero" aria-labelledby="contact-title">
        <div class="contact-hero-inner">
            <h1 id="contact-title">Contact EnginGuard</h1>
            <p class="contact-hero-subtitle">
                Have questions, feedback, or ideas? Send us a message and we’ll get back to you.
            </p>
        </div>
    </section>

    <!-- MAIN CARD -->
    <div class="contact-wrapper">
        <div class="contact-card">

            <!-- Left info -->
            <div class="contact-left">
                <h2>Get in touch</h2>
                <p class="contact-text">
                    Use this form to reach the EnginGuard team for support, feature requests,
                    or general questions about your phishing awareness campaigns.
                </p>

                <div class="contact-info-block" role="note" aria-label="Contact information">
                    <p><strong>Email:</strong> enginguard@engin.local</p>
                    <p><strong>Typical reply time:</strong> within 1–2 business days</p>
                </div>
            </div>

            <!-- Right form -->
            <div class="contact-right">
                <form class="contact-form" method="post" action="contact.php" novalidate>

                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter full name"
                            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Work Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea
                            id="message"
                            name="message"
                            class="contact-textarea"
                            placeholder="Write your message here"
                            required
                        ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="contact-button">Send Message</button>

                    <!-- Status messages -->
                    <?php if ($success): ?>
                        <p class="contact-success" role="status">
                            <?= htmlspecialchars($success) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <p class="contact-error" role="alert">
                            <?= htmlspecialchars($error) ?>
                        </p>
                    <?php endif; ?>

                </form>
            </div>

        </div>
    </div>
</main>

<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
