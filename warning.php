<?php
// warning.php – shown when an employee clicks the phishing link

session_start();
date_default_timezone_set('Asia/Bahrain');
require __DIR__ . '/db.php';

/* Token */
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = trim($token);

if ($token === '') {
    http_response_code(400);
    echo "Invalid or missing token.";
    exit;
}

/* Look up target */
$stmt = $pdo->prepare("
    SELECT 
        ct.target_id,
        ct.campaign_id,
        u.name AS user_name,
        u.email AS user_email,
        c.name AS campaign_name
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
    echo "This link is invalid or has expired.";
    exit;
}

$targetId = (int)$target['target_id'];

function eg_log_event_once(PDO $pdo, int $targetId, string $eventType, string $ip, string $ua): void
{
    $check = $pdo->prepare("
        SELECT COUNT(*)
        FROM email_events
        WHERE target_id = :tid AND event_type = :etype
    ");
    $check->execute([':tid' => $targetId, ':etype' => $eventType]);

    if ((int)$check->fetchColumn() > 0) {
        return;
    }

    $ins = $pdo->prepare("
        INSERT INTO email_events (target_id, event_type, ip, user_agent, created_at)
        VALUES (:tid, :etype, :ip, :ua, datetime('now','localtime'))
    ");
    $ins->execute([
        ':tid'   => $targetId,
        ':etype' => $eventType,
        ':ip'    => $ip,
        ':ua'    => $ua,
    ]);
}

$ip        = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

/* Log click */
eg_log_event_once($pdo, $targetId, 'clicked', $ip, $userAgent);

/* Handle decision */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = strtolower(trim($_POST['decision'] ?? ''));

    if (in_array($decision, ['continue', 'back'], true)) {
        $checkDecision = $pdo->prepare("
            SELECT COUNT(*)
            FROM warning_decisions
            WHERE target_id = :tid
              AND decision  = :decision
        ");
        $checkDecision->execute([
            ':tid'      => $targetId,
            ':decision' => $decision
        ]);

        if ((int)$checkDecision->fetchColumn() === 0) {
            $decStmt = $pdo->prepare("
                INSERT INTO warning_decisions (target_id, decision, created_at)
                VALUES (:tid, :decision, datetime('now','localtime'))
            ");
            $decStmt->execute([
                ':tid'      => $targetId,
                ':decision' => $decision
            ]);
        }

        if ($decision === 'continue') {
            eg_log_event_once($pdo, $targetId, 'continue_anyway', $ip, $userAgent);
            header('Location: awareness.php?token=' . urlencode($token));
            exit;
        } else {
            eg_log_event_once($pdo, $targetId, 'go_back', $ip, $userAgent);
            header('Location: https://www.google.com/');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Warning</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/warning.css">
</head>
<body class="warn-body">

<main class="warn-wrap">
    <section class="warn-card" role="alert" aria-live="polite">

        <div class="warn-icon" aria-hidden="true">⚠️</div>

        <h1 class="warn-title">Security Warning</h1>
        <p class="warn-text">
            The page you are trying to open may be unsafe or attempting to steal personal information.
            If you do not trust this link, go back to safety.
        </p>

        <form method="post" class="warn-actions">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

            <button type="submit" name="decision" value="back" class="btn btn-secondary">
                Go Back
            </button>

            <button type="submit" name="decision" value="continue" class="btn btn-primary">
                Continue
            </button>
        </form>

    </section>
</main>


</body>
</html>
