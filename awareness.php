<?php
// awareness.php – shown after employee clicks "Continue" on warning page

session_start();
date_default_timezone_set('Asia/Bahrain');

require __DIR__ . '/db.php'; // $pdo

/* Token */
$token = $_GET['token'] ?? '';
$token = trim($token);

if ($token === '') {
    http_response_code(400);
    echo "Invalid or missing link.";
    exit;
}

/* Look up target */
$stmt = $pdo->prepare("
    SELECT 
        ct.target_id,
        u.name        AS user_name,
        u.email       AS user_email,
        c.name        AS campaign_name
    FROM campaign_targets ct
    JOIN users u      ON ct.user_id = u.user_id
    JOIN campaigns c  ON ct.campaign_id = c.campaign_id
    WHERE ct.unique_link_token = :token
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    http_response_code(404);
    echo "This link is invalid or has expired.";
    exit;
}

$targetId     = (int)$target['target_id'];
$userName     = (string)($target['user_name'] ?? '');
$campaignName = (string)($target['campaign_name'] ?? '');

/* Log awareness view once */
try {
    $check = $pdo->prepare("SELECT COUNT(*) FROM awareness_views WHERE target_id = :tid");
    $check->execute([':tid' => $targetId]);

    if ((int)$check->fetchColumn() === 0) {
        $log = $pdo->prepare("
            INSERT INTO awareness_views (target_id, viewed_at)
            VALUES (:tid, datetime('now','localtime'))
        ");
        $log->execute([':tid' => $targetId]);
    }
} catch (Exception $e) {
    // silent fail
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Awareness</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/awareness.css">
</head>
<body class="aware-body">

<main class="aware-wrap">

    <section class="aware-card" role="main">

        <div class="aware-top">
            <div class="aware-pill">Security Awareness</div>
        </div>

        <div class="aware-hero">
            <div class="aware-hero-left">
                <h1 class="aware-title">Phishing Awareness</h1>

                <!-- Fun + short line (no long explanation) -->
                <p class="aware-funline">
                    🎣 You’ve been phished — this was a phishing attempt.
                </p>

                <p class="aware-subtitle">
                    This page explains common phishing signs and how to protect yourself.
                </p>
            </div>

            <div class="aware-hero-right">
                <div class="aware-meta">
                    <div class="meta-row">
                        <span class="meta-label">Employee</span>
                        <span class="meta-value"><?= htmlspecialchars($userName, ENT_QUOTES) ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Campaign</span>
                        <span class="meta-value"><?= htmlspecialchars($campaignName, ENT_QUOTES) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="aware-grid">

            <div class="aware-panel">
                <div class="panel-title">
                    <span class="panel-icon">⚠️</span>
                    Common Phishing Signs
                </div>

                <div class="aware-list">
                    <div class="aware-item">
                        <div class="item-dot"></div>
                        <div class="item-text">
                            <strong>Urgency or threats</strong> like “Your account will be suspended today”.
                        </div>
                    </div>

                    <div class="aware-item">
                        <div class="item-dot"></div>
                        <div class="item-text">
                            <strong>Unexpected rewards</strong> such as “You won a prize”.
                        </div>
                    </div>

                    <div class="aware-item">
                        <div class="item-dot"></div>
                        <div class="item-text">
                            <strong>Strange links</strong> asking you to sign in or enter your password.
                        </div>
                    </div>

                    <div class="aware-item">
                        <div class="item-dot"></div>
                        <div class="item-text">
                            <strong>Spelling and formatting issues</strong> (unusual grammar, odd logos).
                        </div>
                    </div>
                </div>
            </div>

            <div class="aware-panel">
                <div class="panel-title">
                    <span class="panel-icon">🛡️</span>
                    How to Stay Safe
                </div>

                <div class="aware-list">
                    <div class="aware-item">
                        <div class="item-dot blue"></div>
                        <div class="item-text">
                            <strong>Stop and verify.</strong> If it feels urgent, confirm with IT or your manager.
                        </div>
                    </div>

                    <div class="aware-item">
                        <div class="item-dot blue"></div>
                        <div class="item-text">
                            <strong>Check sender + link.</strong> Hover links to preview the real destination.
                        </div>
                    </div>

                    <div class="aware-item">
                        <div class="item-dot blue"></div>
                        <div class="item-text">
                            <strong>Never share passwords</strong> through email links or popups.
                        </div>
                    </div>

                    <div class="aware-item">
                        <div class="item-dot blue"></div>
                        <div class="item-text">
                            <strong>Report suspicious emails</strong> instead of clicking or replying.
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="aware-bottom">
            <div class="aware-note">
                <span class="note-icon">ℹ️</span>
                If you think an email is suspicious, close it and contact support.
            </div>

            <div class="aware-actions">
                <a class="btn btn-primary" href="quiz.php?token=<?= urlencode($token) ?>">
                    Take the quiz
                </a>

                     <a class="btn btn-secondary" href="https://www.google.com/" target="_blank" rel="noopener">
                      Close
                     </a>
            </div>
        </div>

    </section>

</main>

<footer class="aware-footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
