<?php
// send_campaign_email.php – Sends phishing emails for active campaigns (cron-safe)

date_default_timezone_set('Asia/Bahrain');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/email_config.php';

echo "=== EnginGuard Cron Mailer ===\n";
echo "Server time: " . date('Y-m-d H:i:s') . "\n";

/* Base URL reachable by employee VMs (Ubuntu web server) */
const WEB_HOST = 'http://192.168.56.101';

function buildPhishUrl(string $token): string {
    return WEB_HOST . '/warning.php?token=' . urlencode($token);
}

/* FIX: report must log into DB via report.php (not mailto) */
function buildReportUrl(string $token): string {
    return WEB_HOST . '/report.php?token=' . urlencode($token);
}

/* Allow only lab-approved sender domains */
function isAllowedFromEmail(string $email): bool {
    $email = strtolower(trim($email));
    if ($email === '') return false;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    return str_ends_with($email, '@eng1n.local');
}

/* Fetch active campaigns with pending targets */
$campaignSql = "
    SELECT 
        c.campaign_id,
        c.name,
        c.status,
        c.start_at,
        c.end_at,
        t.subject,
        t.body_html,
        t.sender_name,
        t.sender_email
    FROM campaigns c
    JOIN email_templates t ON c.template_id = t.template_id
    WHERE datetime(c.start_at) <= datetime('now','localtime')
      AND datetime(c.end_at)   > datetime('now','localtime')
      AND c.status IN ('scheduled','running')
      AND EXISTS (
            SELECT 1 FROM campaign_targets ct
            WHERE ct.campaign_id = c.campaign_id
              AND ct.delivery_status = 'pending'
        )
";

$campaigns = $pdo->query($campaignSql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($campaigns)) {
    echo "No campaigns with pending emails.\n";
    exit;
}

foreach ($campaigns as $c) {

    $cid        = (int)$c['campaign_id'];
    $campaignNm = (string)($c['name'] ?? '');
    $status     = (string)$c['status'];

    $subjectT = (string)($c['subject'] ?? '');
    $bodyT    = (string)($c['body_html'] ?? '');

    // Use template sender if valid; otherwise fall back to SMTP user
    $fromName  = trim((string)($c['sender_name'] ?? '')) ?: $SMTP_FROM_NAME;
    $fromEmail = isAllowedFromEmail((string)($c['sender_email'] ?? ''))
        ? (string)$c['sender_email']
        : $SMTP_USER;

    echo "\n-- Campaign #$cid ({$campaignNm}) --\n";

    $targetsStmt = $pdo->prepare("
        SELECT
            ct.target_id,
            ct.unique_link_token,
            u.name,
            u.email
        FROM campaign_targets ct
        JOIN users u ON ct.user_id = u.user_id
        WHERE ct.campaign_id = :cid
          AND ct.delivery_status = 'pending'
    ");
    $targetsStmt->execute([':cid' => $cid]);
    $targets = $targetsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$targets) continue;

    // Configure PHPMailer once per campaign
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host        = $SMTP_HOST;
    $mail->Port        = $SMTP_PORT;
    $mail->SMTPAuth    = true;
    $mail->Username    = $SMTP_USER;
    $mail->Password    = $SMTP_PASS;
    $mail->SMTPSecure  = false;
    $mail->SMTPAutoTLS = false;
    $mail->CharSet     = 'UTF-8';
    $mail->setFrom($fromEmail, $fromName);
    $mail->isHTML(true);

    foreach ($targets as $t) {

        $token    = (string)$t['unique_link_token'];
        $phishUrl = buildPhishUrl($token);

        // FIX: report link now goes to report.php to record 'reported' event
        $reportUrl = buildReportUrl($token);

        // Report button (keeps spacing so it doesn't overlap)
        $reportButton =
            '<div style="margin:16px 0 8px;">' .
              '<a href="' . htmlspecialchars($reportUrl, ENT_QUOTES) . '" style="' .
                'display:inline-block;padding:10px 18px;background:#dc2626;' .
                'color:#fff;text-decoration:none;border-radius:8px;' .
                'font-weight:800;letter-spacing:.02em;' .
              '">' .
                'REPORT' .
              '</a>' .
            '</div>';

        // Visible phishing link text (no long URL shown)
        $clickHereLink =
            '<a href="' . htmlspecialchars($phishUrl, ENT_QUOTES) . '" style="' .
            'color:#2563eb;text-decoration:underline;font-weight:700;' .
            '">Click here</a>';

        // Replace placeholders
        $htmlBody = str_replace(
            ['{{name}}', '{{phish_link}}', '{{report_link}}'],
            [htmlspecialchars((string)$t['name']), $clickHereLink, $reportButton],
            $bodyT
        );

        // If template contains a visible fake domain URL, replace it with the same "Click here" link
        $htmlBody = preg_replace(
            '#https?://eng1nguard\.com[^\s<]*#i',
            $clickHereLink,
            $htmlBody
        );

        // Convert plain text newlines once
        if (stripos($htmlBody, '<html') === false && stripos($htmlBody, '<br') === false) {
            $htmlBody = nl2br($htmlBody);
        }

        try {
            $mail->clearAllRecipients();
            $mail->clearAttachments();

            $mail->addAddress((string)$t['email'], (string)$t['name']);
            $mail->Subject = str_replace('{{name}}', (string)$t['name'], $subjectT);

            // Wrapper avoids layout issues across mail clients
            $mail->Body =
                '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111827;">' .
                $htmlBody .
                '</div>';

            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body));

            $mail->send();

            // Mark target as sent
            $pdo->prepare("
                UPDATE campaign_targets
                SET sent_at = datetime('now','localtime'),
                    delivery_status = 'sent'
                WHERE target_id = ?
            ")->execute([(int)$t['target_id']]);

            echo " ✓ Sent to {$t['email']}\n";

        } catch (Exception $e) {
            // Mark target as failed
            $pdo->prepare("
                UPDATE campaign_targets
                SET delivery_status = 'failed'
                WHERE target_id = ?
            ")->execute([(int)$t['target_id']]);

            echo " ✗ Failed: {$t['email']}\n";
        }
    }

    // scheduled -> running once it starts sending
    if ($status === 'scheduled') {
        $pdo->prepare("UPDATE campaigns SET status='running' WHERE campaign_id=?")
            ->execute([$cid]);
    }
}

echo "\n=== Completed ===\n";
