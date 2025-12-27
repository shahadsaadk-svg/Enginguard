<?php
// reports.php – Campaign Report with stats, details, PDF export, and quiz history popup 

require 'auth_admin.php'; // admin guard + session
date_default_timezone_set('Asia/Bahrain');

require 'db.php';                  // PDO $pdo
require_once 'campaign_utils.php'; // shared status helper

// Auto-update campaign statuses based on current time
updateCampaignStatuses($pdo);

$error = '';

// 1) Load campaigns for dropdown
try {
    $stmt = $pdo->query("SELECT campaign_id, name FROM campaigns ORDER BY start_at DESC");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $campaigns = [];
    $error = 'Unable to load campaigns.';
}

// 2) Selected campaign
$selectedCampaignId = null;
if (!empty($campaigns)) {
    if (isset($_GET['campaign_id']) && ctype_digit($_GET['campaign_id'])) {
        $selectedCampaignId = (int)$_GET['campaign_id'];
    } else {
        $selectedCampaignId = (int)$campaigns[0]['campaign_id']; // default: latest
    }
}

// 3) Selected detail section
$validDetails = ['clicked', 'ignored', 'reported', 'quiz'];
$detail = $_GET['detail'] ?? '';
if (!in_array($detail, $validDetails, true)) {
    $detail = '';
}

/* Metrics defaults */
$totalTargets      = 0;
$clicked           = 0;
$ignored           = 0;
$reported          = 0;
$completedQuiz     = 0;
$totalContinue     = 0;
$totalGoBack       = 0;
$trainingPct       = 0;
$phishFailureRate  = 0;
$reportRate        = 0;
$avgScorePercent   = 0;
$avgScoreValue     = 0;

$emailChartData = [
    'clicked'  => 0,
    'ignored'  => 0,
    'reported' => 0,
    'quiz'     => 0,
];

$quizResults   = [];
$quizHistory   = [];
$detailRows    = [];
$deptStats     = [];
$safestDept    = null;
$riskiestDept  = null;
$topRiskUsers  = [];

// 4) Load data for selected campaign
if ($selectedCampaignId !== null) {
    try {
        // Total targets (distinct users)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM campaign_targets
            WHERE campaign_id = :cid
        ");
        $stmt->execute([':cid' => $selectedCampaignId]);
        $totalTargets = (int)$stmt->fetchColumn();

        // Clicked (distinct users during window)
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
        $stmt->execute([':cid' => $selectedCampaignId]);
        $clicked = (int)$stmt->fetchColumn();
        $emailChartData['clicked'] = $clicked;

        // Reported (distinct users during window)
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
        $stmt->execute([':cid' => $selectedCampaignId]);
        $reported = (int)$stmt->fetchColumn();
        $emailChartData['reported'] = $reported;

        // Union of (clicked OR reported)
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
        $stmt->execute([':cid' => $selectedCampaignId]);
        $acted = (int)$stmt->fetchColumn();

        // Ignored = total - acted
        $ignored = ($totalTargets > 0) ? max(0, $totalTargets - $acted) : 0;
        $emailChartData['ignored'] = $ignored;

        // Quiz results (only for "continue")
        $stmt = $pdo->prepare("
            SELECT
                qa.attempt_id,
                qa.score,
                qa.passed,
                qa.created_at,
                u.user_id,
                u.name,
                u.email,
                d.name AS department_name,
                ct.target_id
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
        $stmt->execute([':cid' => $selectedCampaignId]);
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

        // Total "Continue Anyway"
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
        $stmt->execute([':cid' => $selectedCampaignId]);
        $totalContinue = (int)$stmt->fetchColumn();

        // Total "Go Back"
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ct.user_id) AS cnt
            FROM campaign_targets ct
            JOIN campaigns c ON c.campaign_id = ct.campaign_id
            JOIN warning_decisions wd
                 ON wd.target_id = ct.target_id
                AND wd.decision  = 'back'
                AND datetime(wd.created_at) >= datetime(c.start_at)
                AND datetime(wd.created_at) <= datetime(c.end_at)
            WHERE ct.campaign_id = :cid
        ");
        $stmt->execute([':cid' => $selectedCampaignId]);
        $totalGoBack = (int)$stmt->fetchColumn();

        // Quiz history (for modal), only for "continue"
        $stmt = $pdo->prepare("
            SELECT
                u.user_id,
                qa.score,
                qa.passed,
                qa.created_at
            FROM quiz_attempts qa
            JOIN campaign_targets ct ON qa.target_id = ct.target_id
            JOIN campaigns c         ON ct.campaign_id = c.campaign_id
            JOIN users u             ON ct.user_id   = u.user_id
            LEFT JOIN warning_decisions wd
                   ON wd.target_id = ct.target_id
                  AND wd.decision  = 'continue'
                  AND datetime(wd.created_at) >= datetime(c.start_at)
                  AND datetime(wd.created_at) <= datetime(c.end_at)
            WHERE ct.campaign_id = :cid
              AND wd.rowid IS NOT NULL
              AND datetime(qa.created_at) >= datetime(c.start_at)
              AND datetime(qa.created_at) <= datetime(c.end_at)
            ORDER BY u.user_id ASC, qa.created_at ASC
        ");
        $stmt->execute([':cid' => $selectedCampaignId]);
        $allAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $quizHistory = [];
        foreach ($allAttempts as $row) {
            $uid = (int)$row['user_id'];
            $quizHistory[$uid] ??= [];
            $quizHistory[$uid][] = [
                'score'      => (int)$row['score'],
                'passed'     => (int)$row['passed'],
                'created_at' => $row['created_at'],
            ];
        }

        // Training % based on "continue" group
        $trainingPct = ($totalContinue > 0)
            ? (int)round(($completedQuiz / $totalContinue) * 100)
            : 0;

        // Failure & report rates based on all targets
        if ($totalTargets > 0) {
            $phishFailureRate = (int)round(($clicked  / $totalTargets) * 100);
            $reportRate       = (int)round(($reported / $totalTargets) * 100);
        }

        // Average score
        $sumScores = 0;
        foreach ($quizResults as $qr) {
            $sumScores += (int)$qr['score'];
        }
        $quizCount = count($quizResults);
        if ($quizCount > 0) {
            $avgScoreValue   = round($sumScores / $quizCount, 1);
            $avgScorePercent = (int)round(($sumScores / ($quizCount * 5)) * 100);
        }

        // Detail tables
        if (in_array($detail, ['clicked', 'reported'], true)) {
            $stmt = $pdo->prepare("
                SELECT
                    u.name,
                    u.email,
                    d.name AS department_name,
                    MIN(ee.created_at) AS first_time
                FROM campaign_targets ct
                JOIN email_events ee ON ee.target_id = ct.target_id
                JOIN campaigns c     ON c.campaign_id = ct.campaign_id
                JOIN users u         ON ct.user_id   = u.user_id
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE ct.campaign_id = :cid
                  AND ee.event_type  = :etype
                  AND datetime(ee.created_at) >= datetime(c.start_at)
                  AND datetime(ee.created_at) <= datetime(c.end_at)
                GROUP BY u.user_id
                ORDER BY first_time ASC
            ");
            $stmt->execute([
                ':cid'   => $selectedCampaignId,
                ':etype' => $detail,
            ]);
            $detailRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($detail === 'ignored') {
            $stmt = $pdo->prepare("
                SELECT
                    u.name,
                    u.email,
                    d.name AS department_name
                FROM campaign_targets ct
                JOIN users u            ON ct.user_id = u.user_id
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE ct.campaign_id = :cid
                  AND u.user_id NOT IN (
                      SELECT DISTINCT ct2.user_id
                      FROM campaign_targets ct2
                      JOIN email_events ee ON ee.target_id = ct2.target_id
                      JOIN campaigns c2    ON c2.campaign_id = ct2.campaign_id
                      WHERE ct2.campaign_id = :cid
                        AND ee.event_type IN ('clicked','reported')
                        AND datetime(ee.created_at) >= datetime(c2.start_at)
                        AND datetime(ee.created_at) <= datetime(c2.end_at)
                  )
                ORDER BY u.name ASC
            ");
            $stmt->execute([':cid' => $selectedCampaignId]);
            $detailRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($detail === 'quiz') {
            $detailRows = $quizResults;
        }

        // Per-department stats (this campaign)
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
        $stmt->execute([':cid' => $selectedCampaignId]);
        $deptStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Global dept stats (safest/riskiest)
        $stmt = $pdo->query("
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
                ) AS clicked_users
            FROM campaign_targets ct
            JOIN users u            ON ct.user_id = u.user_id
            LEFT JOIN departments d ON u.department_id = d.department_id
            GROUP BY department_name
            HAVING total_targets > 0
        ");
        $allDeptStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $minRatio = null;
        $maxRatio = null;
        foreach ($allDeptStats as $row) {
            $t = (int)$row['total_targets'];
            $c = (int)$row['clicked_users'];
            $ratio = $t > 0 ? ($c / $t) : 0;

            if ($minRatio === null || $ratio < $minRatio) {
                $minRatio = $ratio;
                $safestDept = $row;
                $safestDept['ratio'] = $ratio;
            }
            if ($maxRatio === null || $ratio > $maxRatio) {
                $maxRatio = $ratio;
                $riskiestDept = $row;
                $riskiestDept['ratio'] = $ratio;
            }
        }

        // Top 5 risky employees
        $stmt = $pdo->query("
            SELECT
                u.name,
                u.email,
                d.name AS department_name,
                SUM(CASE WHEN ee.rowid IS NOT NULL THEN 1 ELSE 0 END) AS clicks,
                COUNT(DISTINCT ct.campaign_id) AS campaigns_targeted
            FROM campaign_targets ct
            JOIN users u             ON ct.user_id = u.user_id
            LEFT JOIN departments d  ON u.department_id = d.department_id
            LEFT JOIN campaigns c    ON ct.campaign_id = c.campaign_id
            LEFT JOIN email_events ee
                   ON ee.target_id = ct.target_id
                  AND ee.event_type = 'clicked'
                  AND datetime(ee.created_at) >= datetime(c.start_at)
                  AND datetime(ee.created_at) <= datetime(c.end_at)
            GROUP BY u.user_id
            HAVING clicks > 0
            ORDER BY clicks DESC
            LIMIT 5
        ");
        $topRiskUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = 'Unable to load report data for this campaign.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campaign Report – EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Admin layout -->
    <link rel="stylesheet" href="css/admin.css">
    <!-- Reports page -->
    <link rel="stylesheet" href="css/reports.css">
</head>
<body>

<?php include 'admin_header.php'; ?>

<main class="page-container report-page">

    <!-- Title + actions -->
    <div class="report-header-row">
        <div class="report-title-wrap">
            <h1 class="page-title report-title">Campaign Report</h1>
            <p class="report-subtitle">Review campaign performance, department risk, and quiz results.</p>
        </div>

        <?php if ($selectedCampaignId !== null): ?>
            <a href="export_pdf.php?campaign_id=<?= (int)$selectedCampaignId ?>"
               class="report-download-btn"
               target="_blank" rel="noopener">
                Download PDF
            </a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <p class="login-error" style="margin-bottom:15px;">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <?php if (empty($campaigns)): ?>
        <p>There are no campaigns yet. Launch a campaign to see reports.</p>
    <?php else: ?>

        <!-- Campaign selector -->
        <div class="report-controls-row">
            <form method="get" class="report-select-form">
                <label for="campaignSelect" class="report-select-label">Select Campaign</label>

                <select id="campaignSelect" class="report-select" name="campaign_id" onchange="this.form.submit()">
                    <?php foreach ($campaigns as $c): ?>
                        <option value="<?= (int)$c['campaign_id'] ?>"
                            <?= ((int)$c['campaign_id'] === (int)$selectedCampaignId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($detail): ?>
                    <input type="hidden" name="detail" value="<?= htmlspecialchars($detail) ?>">
                <?php endif; ?>
            </form>
        </div>

        <!-- Row: Clickable KPI cards -->
        <div class="report-metric-row">
            <a class="metric-card-link" href="reports.php?campaign_id=<?= (int)$selectedCampaignId ?>&detail=clicked">
                <div class="metric-card metric-clicked <?= $detail === 'clicked' ? 'metric-card-active' : '' ?>">
                    <div class="metric-title">Clicked</div>
                    <div class="metric-value"><?= (int)$clicked ?></div>
                    <div class="metric-sub">Employees who clicked the email</div>
                </div>
            </a>

            <a class="metric-card-link" href="reports.php?campaign_id=<?= (int)$selectedCampaignId ?>&detail=ignored">
                <div class="metric-card metric-ignored <?= $detail === 'ignored' ? 'metric-card-active' : '' ?>">
                    <div class="metric-title">Ignored</div>
                    <div class="metric-value"><?= (int)$ignored ?></div>
                    <div class="metric-sub">Employees who did nothing</div>
                </div>
            </a>

            <a class="metric-card-link" href="reports.php?campaign_id=<?= (int)$selectedCampaignId ?>&detail=reported">
                <div class="metric-card metric-reported <?= $detail === 'reported' ? 'metric-card-active' : '' ?>">
                    <div class="metric-title">Reported</div>
                    <div class="metric-value"><?= (int)$reported ?></div>
                    <div class="metric-sub">Employees who reported phishing</div>
                </div>
            </a>

            <a class="metric-card-link" href="reports.php?campaign_id=<?= (int)$selectedCampaignId ?>&detail=quiz">
                <div class="metric-card metric-quiz <?= $detail === 'quiz' ? 'metric-card-active' : '' ?>">
                    <div class="metric-title">Completed Quiz</div>
                    <div class="metric-value"><?= (int)$completedQuiz ?></div>
                    <div class="metric-sub">Employees who completed the quiz</div>
                </div>
            </a>
        </div>

        <!-- Row: Secondary KPIs -->
        <div class="report-metric-row report-metric-row-second">
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
                <div class="metric-value"><?= (int)$avgScorePercent ?>%</div>
                <div class="metric-sub">Average quiz score for this campaign</div>
            </div>

            <div class="metric-card">
                <div class="metric-title">Clicked “Continue Anyway”</div>
                <div class="metric-value"><?= (int)$totalContinue ?></div>
                <div class="metric-sub">Employees who chose to proceed</div>
            </div>

            <div class="metric-card">
                <div class="metric-title">Clicked “Go Back”</div>
                <div class="metric-value"><?= (int)$totalGoBack ?></div>
                <div class="metric-sub">Employees who went back instead of continuing</div>
            </div>
        </div>

        <!-- Bottom grid -->
        <div class="report-bottom-grid">

            <!-- Chart -->
            <div class="report-panel">
                <h2 class="panel-title">Email Interactions</h2>
                <div class="panel-body chart-wrapper">
                    <div class="chart-bars">
                        <?php
                        $max = max($emailChartData) ?: 1;
                        $labels = ['clicked'=>'Clicked','ignored'=>'Ignored','reported'=>'Reported','quiz'=>'Quiz'];
                        foreach ($labels as $key => $label):
                            $height = ($emailChartData[$key] / $max) * 150;
                            $cls = 'bar-' . $key;
                        ?>
                            <div class="chart-bar-container">
                                <div class="chart-bar <?= $cls ?>" style="height: <?= (int)$height ?>px;"></div>
                                <div class="chart-label"><?= htmlspecialchars($label) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Donut -->
            <div class="report-panel center-panel">
                <h2 class="panel-title">Training Completion</h2>
                <div class="panel-body donut-wrapper">
                    <div class="training-circle" style="--progress: <?= (int)$trainingPct ?>%;">
                        <div class="training-circle-inner">
                            <span>Trained</span>
                            <span class="training-percent"><?= (int)$trainingPct ?>%</span>
                        </div>
                    </div>
                    <p class="donut-caption">
                        <?= (int)$completedQuiz ?> of <?= (int)$totalContinue ?> employees who clicked “Continue Anyway” completed the quiz.
                    </p>
                </div>
            </div>

            <!-- Quiz table -->
            <div class="report-panel">
                <h2 class="panel-title">Quiz Result (Latest Per User)</h2>
                <div class="panel-body">
                    <?php if (empty($quizResults)): ?>
                        <p class="no-quiz-text">No quiz results for this campaign yet.</p>
                    <?php else: ?>
                        <div class="table-scroll">
                            <table class="quiz-table">
                                <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Department</th>
                                    <th>Score</th>
                                    <th>Passed</th>
                                    <th>Last Attempt</th>
                                    <th>Details</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($quizResults as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>
                                        <td><?= (int)$row['score'] ?>/5</td>
                                        <td><?= ((int)$row['passed'] === 1) ? 'Yes' : 'No' ?></td>
                                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                                        <td>
                                            <button type="button"
                                                    class="quiz-history-btn"
                                                    data-user-id="<?= (int)$row['user_id'] ?>"
                                                    data-user-name="<?= htmlspecialchars($row['name']) ?>">
                                                View history
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Department stats -->
        <?php if (!empty($deptStats)): ?>
            <section class="report-dept-section">
                <h2 class="panel-title">Department Risk Summary (This Campaign)</h2>

                <div class="table-scroll">
                    <table class="dept-table">
                        <thead>
                        <tr>
                            <th>Department</th>
                            <th>Targeted</th>
                            <th>Clicked</th>
                            <th>Reported</th>
                            <th>Completed Quiz</th>
                            <th>Risk Level</th>
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
                </div>
            </section>
        <?php endif; ?>

        <!-- Global summary -->
        <section class="report-global-summary">
            <h2 class="panel-title">Organization-wide Summary</h2>

            <div class="report-summary-grid">
                <div class="summary-card summary-safe">
                    <div class="summary-title">Safest Department</div>
                    <?php if ($safestDept): ?>
                        <div class="summary-value"><?= htmlspecialchars($safestDept['department_name']) ?></div>
                        <div class="summary-sub">
                            Clicked <?= (int)$safestDept['clicked_users'] ?> of <?= (int)$safestDept['total_targets'] ?>
                            (<?= (int)round($safestDept['ratio'] * 100) ?>% click rate)
                        </div>
                    <?php else: ?>
                        <div class="summary-sub">Not enough data yet.</div>
                    <?php endif; ?>
                </div>

                <div class="summary-card summary-risk">
                    <div class="summary-title">Most At-Risk Department</div>
                    <?php if ($riskiestDept): ?>
                        <div class="summary-value"><?= htmlspecialchars($riskiestDept['department_name']) ?></div>
                        <div class="summary-sub">
                            Clicked <?= (int)$riskiestDept['clicked_users'] ?> of <?= (int)$riskiestDept['total_targets'] ?>
                            (<?= (int)round($riskiestDept['ratio'] * 100) ?>% click rate)
                        </div>
                    <?php else: ?>
                        <div class="summary-sub">Not enough data yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="summary-table-title">Top 5 Riskiest Employees (All Campaigns)</h3>

            <?php if (empty($topRiskUsers)): ?>
                <p class="no-quiz-text">No risky employees yet – great job!</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="detail-table">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Times Clicked</th>
                            <th>Campaigns Targeted</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topRiskUsers as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['department_name'] ?? '-') ?></td>
                                <td><?= (int)$u['clicks'] ?></td>
                                <td><?= (int)$u['campaigns_targeted'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Detail section -->
        <?php if ($detail): ?>
            <section class="report-detail-section">
                <h2 class="panel-title">
                    <?php
                    $detailTitles = [
                        'clicked'  => 'Employees Who Clicked the Email',
                        'ignored'  => 'Employees Who Ignored the Email',
                        'reported' => 'Employees Who Reported the Email',
                        'quiz'     => 'Employees Who Completed the Quiz (Latest Attempt)',
                    ];
                    echo $detailTitles[$detail] ?? 'Details';
                    ?>
                </h2>

                <?php if (empty($detailRows)): ?>
                    <p class="no-quiz-text">No employees found for this category yet.</p>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="detail-table">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <?php if ($detail === 'quiz'): ?>
                                    <th>Score</th>
                                    <th>Passed</th>
                                    <th>Last Attempt</th>
                                    <th>Details</th>
                                <?php elseif ($detail === 'ignored'): ?>
                                    <th>Status</th>
                                <?php else: ?>
                                    <th>First Event Time</th>
                                <?php endif; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($detailRows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>

                                    <?php if ($detail === 'quiz'): ?>
                                        <td><?= (int)$row['score'] ?>/5</td>
                                        <td><?= ((int)$row['passed'] === 1) ? 'Yes' : 'No' ?></td>
                                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                                        <td>
                                            <button type="button"
                                                    class="quiz-history-btn"
                                                    data-user-id="<?= (int)$row['user_id'] ?>"
                                                    data-user-name="<?= htmlspecialchars($row['name']) ?>">
                                                View history
                                            </button>
                                        </td>
                                    <?php elseif ($detail === 'ignored'): ?>
                                        <td>Ignored during campaign window</td>
                                    <?php else: ?>
                                        <td><?= htmlspecialchars($row['first_time'] ?? '-') ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    <?php endif; ?>

</main>

<!-- Quiz History Modal -->
<div id="quizHistoryModal" class="modal-overlay">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="quizModalTitle">
        <div class="modal-header">
            <h2 id="quizModalTitle">Quiz History</h2>
            <button type="button" class="modal-close-btn" id="quizModalClose" aria-label="Close">&times;</button>
        </div>
        <div id="quizModalBody"></div>
    </div>
</div>

<footer class="footer footer-center">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

<script>
    const quizHistoryData = <?= json_encode($quizHistory, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;

    const modal      = document.getElementById('quizHistoryModal');
    const modalClose = document.getElementById('quizModalClose');
    const modalTitle = document.getElementById('quizModalTitle');
    const modalBody  = document.getElementById('quizModalBody');

    function openQuizHistory(userId, userName) {
        const history = quizHistoryData[userId] || [];
        modalTitle.textContent = 'Quiz History – ' + (userName || 'Employee');

        if (!history.length) {
            modalBody.innerHTML = '<p class="no-quiz-text">No quiz attempts recorded for this user in this campaign.</p>';
        } else {
            let rows = '';
            history.forEach(attempt => {
                const passedText = attempt.passed === 1 ? 'Yes' : 'No';
                rows += `
                    <tr>
                        <td>${attempt.created_at}</td>
                        <td>${attempt.score}/5</td>
                        <td>${passedText}</td>
                    </tr>
                `;
            });

            modalBody.innerHTML = `
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>Attempt Time</th>
                            <th>Score</th>
                            <th>Passed</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
        }

        modal.classList.add('show');
    }

    function closeQuizHistory() {
        modal.classList.remove('show');
    }

    document.querySelectorAll('.quiz-history-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const userId   = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name') || '';
            if (userId) openQuizHistory(userId, userName);
        });
    });

    modalClose.addEventListener('click', closeQuizHistory);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeQuizHistory();
    });

    // Escape key closes modal
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && modal.classList.contains('show')) closeQuizHistory();
    });
</script>

</body>
</html>
