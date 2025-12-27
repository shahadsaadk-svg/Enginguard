<?php
/**
 * export_pdf.php
 * Generate a PDF campaign report using wkhtmltopdf.
 */

session_start();
date_default_timezone_set('Asia/Bahrain');
require 'db.php'; // PDO $pdo (SQLite)

// Admin-only
if (!isset($_SESSION['user_id']) || (($_SESSION['user_role'] ?? '') !== 'admin')) {
    header('Location: login.php');
    exit;
}

// Campaign id
$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
if ($campaignId <= 0) {
    die('Invalid campaign.');
}

try {
    // Campaign info
    $stmt = $pdo->prepare("
        SELECT campaign_id, name, start_at, end_at, status
        FROM campaigns
        WHERE campaign_id = :cid
        LIMIT 1
    ");
    $stmt->execute([':cid' => $campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        die('Campaign not found.');
    }

    $campaignName = $campaign['name'];
    $startAt      = $campaign['start_at'] ?? '';
    $endAt        = $campaign['end_at']   ?? '';
    $status       = $campaign['status']   ?? '';

    // Total targets
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id)
        FROM campaign_targets
        WHERE campaign_id = :cid
    ");
    $stmt->execute([':cid' => $campaignId]);
    $totalTargets = (int)$stmt->fetchColumn();

    // Clicked
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ct.user_id) AS cnt
        FROM campaign_targets ct
        JOIN email_events ee ON ee.target_id = ct.target_id
        JOIN campaigns c     ON c.campaign_id = ct.campaign_id
        WHERE ct.campaign_id = :cid
          AND ee.event_type  = 'clicked'
          AND datetime(ee.created_at) >= datetime(c.start_at)
          AND datetime(ee.created_at) <= datetime(c.end_at)
    ");
    $stmt->execute([':cid' => $campaignId]);
    $clicked = (int)$stmt->fetchColumn();

    // Reported
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ct.user_id) AS cnt
        FROM campaign_targets ct
        JOIN email_events ee ON ee.target_id = ct.target_id
        JOIN campaigns c     ON c.campaign_id = ct.campaign_id
        WHERE ct.campaign_id = :cid
          AND ee.event_type  = 'reported'
          AND datetime(ee.created_at) >= datetime(c.start_at)
          AND datetime(ee.created_at) <= datetime(c.end_at)
    ");
    $stmt->execute([':cid' => $campaignId]);
    $reported = (int)$stmt->fetchColumn();

    // Acted (clicked or reported)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ct.user_id) AS cnt
        FROM campaign_targets ct
        JOIN email_events ee ON ee.target_id = ct.target_id
        JOIN campaigns c     ON c.campaign_id = ct.campaign_id
        WHERE ct.campaign_id = :cid
          AND ee.event_type IN ('clicked','reported')
          AND datetime(ee.created_at) >= datetime(c.start_at)
          AND datetime(ee.created_at) <= datetime(c.end_at)
    ");
    $stmt->execute([':cid' => $campaignId]);
    $acted = (int)$stmt->fetchColumn();

    // Ignored
    $ignored = ($totalTargets > 0) ? max(0, $totalTargets - $acted) : 0;

    $emailChartData = [
        'clicked'  => $clicked,
        'ignored'  => $ignored,
        'reported' => $reported,
        'quiz'     => 0,
    ];

    // Quiz results (only "continue")
    $stmt = $pdo->prepare("
        SELECT
            qa.attempt_id,
            qa.score,
            qa.passed,
            qa.created_at,
            u.user_id,
            u.name,
            u.email,
            d.name AS department_name
        FROM quiz_attempts qa
        JOIN campaign_targets ct ON qa.target_id = ct.target_id
        JOIN campaigns c         ON ct.campaign_id = c.campaign_id
        JOIN users u             ON ct.user_id   = u.user_id
        LEFT JOIN departments d  ON u.department_id = d.department_id
        LEFT JOIN warning_decisions wd
               ON wd.target_id = ct.target_id
              AND wd.decision  = 'continue'
              AND datetime(wd.created_at) >= datetime(c.start_at)
              AND datetime(wd.created_at) <= datetime(c.end_at)
        WHERE ct.campaign_id = :cid
          AND wd.rowid IS NOT NULL
          AND datetime(qa.created_at) >= datetime(c.start_at)
          AND datetime(qa.created_at) <= datetime(c.end_at)
        ORDER BY qa.created_at ASC
    ");
    $stmt->execute([':cid' => $campaignId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Latest attempt per user
    $perUser = [];
    foreach ($rows as $row) {
        $uid = (int)$row['user_id'];
        $perUser[$uid] = $row;
    }
    $quizResults   = array_values($perUser);
    $completedQuiz = count($quizResults);
    $emailChartData['quiz'] = $completedQuiz;

    // Total continue
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ct.user_id) AS cnt
        FROM campaign_targets ct
        JOIN campaigns c ON c.campaign_id = ct.campaign_id
        JOIN warning_decisions wd
             ON wd.target_id = ct.target_id
            AND wd.decision  = 'continue'
            AND datetime(wd.created_at) >= datetime(c.start_at)
            AND datetime(wd.created_at) <= datetime(c.end_at)
        WHERE ct.campaign_id = :cid
    ");
    $stmt->execute([':cid' => $campaignId]);
    $totalContinue = (int)$stmt->fetchColumn();

    // Training %
    $trainingPct = ($totalContinue > 0) ? (int)round(($completedQuiz / $totalContinue) * 100) : 0;

    // Rates
    $phishFailureRate = ($totalTargets > 0) ? (int)round(($clicked / $totalTargets) * 100) : 0;
    $reportRate       = ($totalTargets > 0) ? (int)round(($reported / $totalTargets) * 100) : 0;

    // Avg score
    $sumScores = 0;
    foreach ($quizResults as $qr) {
        $sumScores += (int)$qr['score'];
    }
    $quizCount       = count($quizResults);
    $avgScoreValue   = $quizCount > 0 ? round($sumScores / $quizCount, 1) : 0;
    $avgScorePercent = $quizCount > 0 ? (int)round(($sumScores / ($quizCount * 5)) * 100) : 0;

    // Dept summary (this campaign)
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(d.name, 'Unknown') AS department_name,
            COUNT(DISTINCT ct.user_id) AS total_targets,
            SUM(
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM email_events ee
                    JOIN campaigns c2 ON c2.campaign_id = ct.campaign_id
                    WHERE ee.target_id = ct.target_id
                      AND ee.event_type = 'clicked'
                      AND datetime(ee.created_at) >= datetime(c2.start_at)
                      AND datetime(ee.created_at) <= datetime(c2.end_at)
                ) THEN 1 ELSE 0 END
            ) AS clicked_users,
            SUM(
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM email_events ee2
                    JOIN campaigns c3 ON c3.campaign_id = ct.campaign_id
                    WHERE ee2.target_id = ct.target_id
                      AND ee2.event_type = 'reported'
                      AND datetime(ee2.created_at) >= datetime(c3.start_at)
                      AND datetime(ee2.created_at) <= datetime(c3.end_at)
                ) THEN 1 ELSE 0 END
            ) AS reported_users,
            SUM(
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM quiz_attempts qa
                    JOIN campaigns c4 ON c4.campaign_id = ct.campaign_id
                    JOIN warning_decisions wd
                         ON wd.target_id = ct.target_id
                        AND wd.decision  = 'continue'
                        AND datetime(wd.created_at) >= datetime(c4.start_at)
                        AND datetime(wd.created_at) <= datetime(c4.end_at)
                    WHERE qa.target_id = ct.target_id
                      AND datetime(qa.created_at) >= datetime(c4.start_at)
                      AND datetime(qa.created_at) <= datetime(c4.end_at)
                ) THEN 1 ELSE 0 END
            ) AS quiz_completed
        FROM campaign_targets ct
        JOIN users u            ON ct.user_id = u.user_id
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE ct.campaign_id = :cid
        GROUP BY department_name
        ORDER BY total_targets DESC
    ");
    $stmt->execute([':cid' => $campaignId]);
    $deptStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Safest / most at-risk (from deptStats)
    $safestDept   = null;
    $mostRiskDept = null;
    foreach ($deptStats as $d) {
        $t = (int)$d['total_targets'];
        $c = (int)$d['clicked_users'];
        if ($t <= 0) continue;

        $ratio = $c / $t;
        $entry = [
            'name'     => $d['department_name'],
            'ratio'    => $ratio,
            'clicked'  => $c,
            'targeted' => $t,
        ];

        if ($mostRiskDept === null || $ratio > $mostRiskDept['ratio']) $mostRiskDept = $entry;
        if ($safestDept === null   || $ratio < $safestDept['ratio'])   $safestDept   = $entry;
    }

    // Top 5 risky employees (this campaign window)
    $stmt = $pdo->prepare("
        SELECT
            u.name,
            u.email,
            COALESCE(d.name, 'Unknown') AS department_name,
            SUM(CASE WHEN ee.event_type = 'clicked'  THEN 1 ELSE 0 END) AS clicks,
            SUM(CASE WHEN ee.event_type = 'reported' THEN 1 ELSE 0 END) AS reports
        FROM campaign_targets ct
        JOIN users u             ON ct.user_id = u.user_id
        LEFT JOIN departments d  ON u.department_id = d.department_id
        LEFT JOIN campaigns c    ON ct.campaign_id = c.campaign_id
        LEFT JOIN email_events ee
               ON ee.target_id = ct.target_id
              AND datetime(ee.created_at) >= datetime(c.start_at)
              AND datetime(ee.created_at) <= datetime(c.end_at)
        WHERE ct.campaign_id = :cid
        GROUP BY u.user_id
        HAVING SUM(CASE WHEN ee.event_type = 'clicked' THEN 1 ELSE 0 END) > 0
        ORDER BY clicks DESC, reports ASC, u.name ASC
        LIMIT 5
    ");
    $stmt->execute([':cid' => $campaignId]);
    $topRiskEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die('Database error while generating report.');
}

// CSS path for wkhtmltopdf
$cssPath = realpath(__DIR__ . '/css/export_pdf.css');

// Build HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campaign Report – <?= htmlspecialchars($campaignName) ?></title>
    <?php if ($cssPath): ?>
        <link rel="stylesheet" href="file://<?= htmlspecialchars($cssPath, ENT_QUOTES) ?>">
    <?php endif; ?>
</head>
<body>

<div class="pdf-wrapper">

    <header class="pdf-header">
        <div class="pdf-header-left">
            <h1>Campaign Report</h1>
            <div class="subtitle">
                <?= htmlspecialchars($campaignName) ?><br>
                <?php if ($startAt || $endAt): ?>
                    <span class="subtitle-dates">
                        <?= htmlspecialchars($startAt ?: '') ?>
                        <?php if ($endAt): ?> – <?= htmlspecialchars($endAt) ?><?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="pdf-header-right">
            <div class="status-pill status-<?= htmlspecialchars(strtolower($status)) ?>">
                <?= htmlspecialchars(ucfirst($status)) ?>
            </div>
            <div class="small-text">Generated <?= date('Y-m-d H:i') ?></div>
        </div>
    </header>

    <!-- Metrics -->
    <section class="metrics-section">
        <div class="metrics-grid">
            <div class="metric-card metric-clicked">
                <div class="metric-title">Clicked</div>
                <div class="metric-value"><?= (int)$clicked ?></div>
                <div class="metric-sub">Employees who clicked the email</div>
            </div>
            <div class="metric-card metric-ignored">
                <div class="metric-title">Ignored</div>
                <div class="metric-value"><?= (int)$ignored ?></div>
                <div class="metric-sub">Employees who did nothing</div>
            </div>
            <div class="metric-card metric-reported">
                <div class="metric-title">Reported</div>
                <div class="metric-value"><?= (int)$reported ?></div>
                <div class="metric-sub">Employees who reported phishing</div>
            </div>
            <div class="metric-card metric-quiz">
                <div class="metric-title">Completed Quiz</div>
                <div class="metric-value"><?= (int)$completedQuiz ?></div>
                <div class="metric-sub">Employees who completed the quiz</div>
            </div>
        </div>

        <div class="metrics-grid metrics-grid-secondary">
            <div class="metric-card">
                <div class="metric-title">Total Targets</div>
                <div class="metric-value"><?= (int)$totalTargets ?></div>
                <div class="metric-sub">Employees targeted in this campaign</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Phishing Failure Rate</div>
                <div class="metric-value"><?= (int)$phishFailureRate ?>%</div>
                <div class="metric-sub">% of targeted users who clicked</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Report Rate</div>
                <div class="metric-value"><?= (int)$reportRate ?>%</div>
                <div class="metric-sub">% of targeted users who reported</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Avg Quiz Score</div>
                <div class="metric-value"><?= htmlspecialchars($avgScoreValue) ?>/5</div>
                <div class="metric-sub">≈ <?= (int)$avgScorePercent ?>% average score</div>
            </div>
        </div>
    </section>

    <!-- Highlights -->
    <section class="content-section">
        <h2 class="section-title">Global Risk Highlights</h2>

        <div class="top-summary">
            <div class="top-summary-card top-safe">
                <div class="top-summary-title">Safest Department</div>
                <?php if ($safestDept): ?>
                    <div class="top-strong"><?= htmlspecialchars($safestDept['name']) ?></div>
                    <div class="small-text">
                        <?= (int)$safestDept['clicked'] ?> clicked /
                        <?= (int)$safestDept['targeted'] ?> targeted
                        (<?= (int)round($safestDept['ratio'] * 100) ?>% click rate)
                    </div>
                <?php else: ?>
                    <div class="small-text">No department data yet.</div>
                <?php endif; ?>
            </div>

            <div class="top-summary-card top-risk">
                <div class="top-summary-title">Most At-Risk Department</div>
                <?php if ($mostRiskDept): ?>
                    <div class="top-strong"><?= htmlspecialchars($mostRiskDept['name']) ?></div>
                    <div class="small-text">
                        <?= (int)$mostRiskDept['clicked'] ?> clicked /
                        <?= (int)$mostRiskDept['targeted'] ?> targeted
                        (<?= (int)round($mostRiskDept['ratio'] * 100) ?>% click rate)
                    </div>
                <?php else: ?>
                    <div class="small-text">No department data yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($topRiskEmployees)): ?>
            <div class="panel panel-table">
                <h3>Top 5 Riskiest Employees (this campaign)</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="w-name">Name</th>
                            <th class="w-email">Email</th>
                            <th class="w-dept">Department</th>
                            <th class="w-num">Clicks</th>
                            <th class="w-num">Reports</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topRiskEmployees as $emp): ?>
                            <tr>
                                <td><?= htmlspecialchars($emp['name']) ?></td>
                                <td><?= htmlspecialchars($emp['email']) ?></td>
                                <td><?= htmlspecialchars($emp['department_name']) ?></td>
                                <td><?= (int)$emp['clicks'] ?></td>
                                <td><?= (int)$emp['reports'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Engagement -->
    <section class="content-section avoid-break">
        <h2 class="section-title">Engagement Details</h2>

        <div class="two-col">
            <div class="panel">
                <h3>Email Interactions</h3>
                <div class="chart-bars">
                    <?php
                    $max = max($emailChartData) ?: 1;
                    $labels = ['clicked'=>'Clicked','ignored'=>'Ignored','reported'=>'Reported','quiz'=>'Quiz'];
                    foreach ($labels as $key => $label):
                        $height = ($emailChartData[$key] / $max) * 110;
                    ?>
                        <div class="chart-bar-container">
                            <div class="chart-bar bar-<?= htmlspecialchars($key) ?>" style="height: <?= (int)$height ?>px;"></div>
                            <div class="chart-label"><?= htmlspecialchars($label) ?></div>
                            <div class="chart-label chart-value"><?= (int)$emailChartData[$key] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <h3>Training Completion</h3>
                <div class="donut-wrapper">
                    <div class="training-circle" style="--progress: <?= (int)$trainingPct ?>%;">
                        <div class="training-circle-inner">
                            <div>Trained</div>
                            <div class="training-percent"><?= (int)$trainingPct ?>%</div>
                        </div>
                    </div>
                    <div class="small-text donut-text">
                        <?= (int)$completedQuiz ?> of <?= (int)$totalContinue ?> employees who clicked
                        “Continue Anyway” completed the quiz.
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-table panel-quiz-table avoid-break">
            <h3>Quiz Results (Latest Attempt Per User)</h3>
            <?php if (empty($quizResults)): ?>
                <p class="small-text">No quiz results for this campaign yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th class="w-name">User</th>
                            <th class="w-dept">Department</th>
                            <th class="w-num">Score</th>
                            <th class="w-num">Passed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizResults as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>
                                <td><?= (int)$row['score'] ?>/5</td>
                                <td><?= ((int)$row['passed'] === 1) ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Department risk -->
    <section class="content-section">
        <h2 class="section-title">Department Risk Summary</h2>

        <?php if (empty($deptStats)): ?>
            <p class="small-text">No department data available for this campaign.</p>
        <?php else: ?>
            <table class="dept-table">
                <thead>
                    <tr>
                        <th class="w-dept">Department</th>
                        <th class="w-num">Targeted</th>
                        <th class="w-num">Clicked</th>
                        <th class="w-num">Reported</th>
                        <th class="w-num">Quiz</th>
                        <th class="w-num">Risk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deptStats as $dRow): ?>
                        <?php
                        $deptTotal   = (int)$dRow['total_targets'];
                        $deptClicked = (int)$dRow['clicked_users'];
                        $ratio       = $deptTotal > 0 ? $deptClicked / $deptTotal : 0;

                        if ($ratio >= 0.5) { $riskLabel = 'High'; $riskClass = 'risk-high'; }
                        elseif ($ratio >= 0.2) { $riskLabel = 'Medium'; $riskClass = 'risk-medium'; }
                        else { $riskLabel = 'Low'; $riskClass = 'risk-low'; }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($dRow['department_name']) ?></td>
                            <td><?= $deptTotal ?></td>
                            <td><?= $deptClicked ?></td>
                            <td><?= (int)$dRow['reported_users'] ?></td>
                            <td><?= (int)$dRow['quiz_completed'] ?></td>
                            <td><span class="risk-pill <?= $riskClass ?>"><?= $riskLabel ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <footer class="pdf-footer">
        <div class="footer-note">
            Generated by EnginGuard Admin • <?= date('Y-m-d H:i') ?>
        </div>
    </footer>

</div>

</body>
</html>
<?php
$html = ob_get_clean();

// Write HTML to temp file
$tmpHtml = tempnam(sys_get_temp_dir(), 'eng_report_') . '.html';
$tmpPdf  = tempnam(sys_get_temp_dir(), 'eng_report_') . '.pdf';
file_put_contents($tmpHtml, $html);

// wkhtmltopdf (print-friendly defaults)
$cmd = 'wkhtmltopdf --quiet --enable-local-file-access ' .
       '--page-size A4 --orientation Portrait ' .
       '--margin-top 12mm --margin-bottom 12mm --margin-left 10mm --margin-right 10mm ' .
       '--print-media-type --encoding utf-8 ' .
       escapeshellarg($tmpHtml) . ' ' . escapeshellarg($tmpPdf) . ' 2>&1';

$output = shell_exec($cmd);

// Cleanup HTML
@unlink($tmpHtml);

// Verify PDF
if (!file_exists($tmpPdf) || filesize($tmpPdf) < 1000) {
    @unlink($tmpPdf);
    echo "PDF generation failed.\n";
    echo htmlspecialchars($output ?? '');
    exit;
}

// Download (safe filename)
$safeName = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $campaignName);
$downloadName = 'campaign_report_' . $campaignId . '_' . $safeName . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpPdf));

readfile($tmpPdf);
@unlink($tmpPdf);
exit;
