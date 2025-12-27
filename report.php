<?php
// report.php – employee clicked "Report phishing" link

date_default_timezone_set('Asia/Bahrain');
require __DIR__ . '/db.php'; // $pdo

/* Token */
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
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
        u.name  AS user_name,
        u.email AS user_email,
        c.name  AS campaign_name
    FROM campaign_targets ct
    JOIN users u     ON ct.user_id = u.user_id
    JOIN campaigns c ON ct.campaign_id = c.campaign_id
    WHERE ct.unique_link_token = :token
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    http_response_code(404);
    echo "This reporting link is invalid or has expired.";
    exit;
}

$targetId     = (int)$target['target_id'];
$userName     = (string)($target['user_name'] ?? '');
$campaignName = (string)($target['campaign_name'] ?? '');

/* Log reported once */
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$check = $pdo->prepare("
    SELECT COUNT(*) 
    FROM email_events
    WHERE target_id = :tid
      AND event_type = 'reported'
");
$check->execute([':tid' => $targetId]);
$alreadyReported = (int)$check->fetchColumn() > 0;

if (!$alreadyReported) {
    $insert = $pdo->prepare("
        INSERT INTO email_events (target_id, event_type, ip, user_agent, created_at)
        VALUES (:tid, 'reported', :ip, :ua, datetime('now','localtime'))
    ");
    $insert->execute([
        ':tid' => $targetId,
        ':ip'  => $ip,
        ':ua'  => $userAgent
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Thank You for Reporting</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Dedicated CSS for this page -->
    <link rel="stylesheet" href="css/report.css">
</head>
<body class="report-body">

<main class="report-wrapper">
    <div class="report-icon" aria-hidden="true">✅</div>

    <h1 class="report-title">Thank you for reporting</h1>

    <p class="report-text">
        Your report helps keep your organization safe from phishing attempts.
    </p>

    <p class="report-tip">
        If you’re ever unsure about an email, report it or verify with IT before clicking.
    </p>

    <p class="close-text">
        You may now close this tab.
    </p>
</main>

</body>
</html>
