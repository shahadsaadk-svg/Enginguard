<?php
// dashboard.php – main admin dashboard 

session_start();
require 'db.php'; // PDO $pdo
require_once __DIR__ . '/campaign_utils.php';

// Admin only
require 'auth_admin.php';

$adminName = $_SESSION['user_name'] ?? 'Admin';

// Update statuses first
updateCampaignStatuses($pdo);

/* ---------- 1) CAMPAIGN OVERVIEW COUNTS ---------- */
$totalCampaigns      = 0;
$runningCampaigns    = 0;
$scheduledCampaigns  = 0;
$completedCampaigns  = 0;

try {
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM campaigns
        GROUP BY status
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $status = strtolower($row['status']);
        $cnt    = (int)$row['cnt'];
        $totalCampaigns += $cnt;

        if ($status === 'running') {
            $runningCampaigns = $cnt;
        } elseif ($status === 'scheduled') {
            $scheduledCampaigns = $cnt;
        } elseif ($status === 'completed') {
            $completedCampaigns = $cnt;
        }
    }
} catch (Exception $e) {
    // keep zeros
}

/* ---------- 2) USER BEHAVIOUR COUNTS (COMPLETED CAMPAIGNS ONLY) ---------- */
$clickedUsers       = 0;
$ignoredUsers       = 0;
$reportedUsers      = 0;
$totalTargetedUsers = 0;

try {
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT ct.user_id)
        FROM campaign_targets ct
        JOIN campaigns c ON c.campaign_id = ct.campaign_id
        WHERE c.status = 'completed'
    ");
    $totalTargetedUsers = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT ee.event_type, COUNT(DISTINCT ct.user_id) AS cnt
        FROM email_events ee
        JOIN campaign_targets ct ON ee.target_id = ct.target_id
        JOIN campaigns c         ON ct.campaign_id = c.campaign_id
        WHERE c.status = 'completed'
          AND datetime(ee.created_at) >= datetime(c.start_at)
          AND datetime(ee.created_at) <= datetime(c.end_at)
          AND ee.event_type IN ('clicked','reported')
        GROUP BY ee.event_type
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $type = strtolower($row['event_type']);
        $cnt  = (int)$row['cnt'];
        if ($type === 'clicked') $clickedUsers = $cnt;
        if ($type === 'reported') $reportedUsers = $cnt;
    }

    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT ct.user_id) AS ignored_cnt
        FROM campaign_targets ct
        JOIN campaigns c ON c.campaign_id = ct.campaign_id
        WHERE c.status = 'completed'
          AND NOT EXISTS (
              SELECT 1
              FROM email_events ee
              WHERE ee.target_id = ct.target_id
                AND ee.event_type IN ('clicked','reported')
                AND datetime(ee.created_at) >= datetime(c.start_at)
                AND datetime(ee.created_at) <= datetime(c.end_at)
          )
    ");
    $ignoredUsers = (int)$stmt->fetchColumn();
    if ($ignoredUsers < 0) $ignoredUsers = 0;

} catch (Exception $e) {
    // keep zeros
}

/* ---------- 3) TRAINING PROGRESS (ALL CAMPAIGNS) ---------- */
$totalEligibleUsers = 0;
$totalTrainedUsers  = 0;
$deptTraining       = [];

try {
    $stmt = $pdo->query("
        SELECT
            u.user_id,
            COALESCE(d.name, 'Unknown') AS department_name,
            CASE WHEN EXISTS (
                SELECT 1
                FROM campaign_targets ct
                JOIN campaigns c           ON ct.campaign_id = c.campaign_id
                JOIN warning_decisions wd  ON wd.target_id = ct.target_id
                WHERE ct.user_id = u.user_id
                  AND wd.decision = 'continue'
                  AND datetime(wd.created_at) >= datetime(c.start_at)
                  AND datetime(wd.created_at) <= datetime(c.end_at)
            ) THEN 1 ELSE 0 END AS eligible,
            CASE WHEN EXISTS (
                SELECT 1
                FROM campaign_targets ct2
                JOIN campaigns c2           ON ct2.campaign_id = c2.campaign_id
                JOIN warning_decisions wd2  ON wd2.target_id = ct2.target_id
                JOIN quiz_attempts qa       ON qa.target_id = ct2.target_id
                WHERE ct2.user_id = u.user_id
                  AND wd2.decision = 'continue'
                  AND datetime(wd2.created_at) >= datetime(c2.start_at)
                  AND datetime(wd2.created_at) <= datetime(c2.end_at)
                  AND datetime(qa.created_at)  >= datetime(c2.start_at)
                  AND datetime(qa.created_at)  <= datetime(c2.end_at)
            ) THEN 1 ELSE 0 END AS trained
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE EXISTS (SELECT 1 FROM campaign_targets ct3 WHERE ct3.user_id = u.user_id)
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $deptName = $row['department_name'];
        $eligible = (int)$row['eligible'];
        $trained  = (int)$row['trained'];

        if ($eligible) $totalEligibleUsers++;
        if ($trained)  $totalTrainedUsers++;

        if (!isset($deptTraining[$deptName])) {
            $deptTraining[$deptName] = [
                'department' => $deptName,
                'eligible'   => 0,
                'trained'    => 0,
            ];
        }
        $deptTraining[$deptName]['eligible'] += $eligible;
        $deptTraining[$deptName]['trained']  += $trained;
    }

    $deptTraining = array_values($deptTraining);
} catch (Exception $e) {
    $deptTraining = [];
}

$trainingPercent = $totalEligibleUsers > 0
    ? round(($totalTrainedUsers / $totalEligibleUsers) * 100)
    : 0;

/* ---------- 4) BEHAVIOUR CHART DATA ---------- */
$emailChartData = [
    'clicked'  => $clickedUsers,
    'ignored'  => $ignoredUsers,
    'reported' => $reportedUsers,
];

/* ---------- 5) BEST PERFORMING CAMPAIGN ---------- */
$bestCampaignName  = 'Not available yet';
$bestReportRatePct = 0.0;

try {
    $stmt = $pdo->query("
        SELECT
            c.campaign_id,
            c.name,
            COUNT(DISTINCT ct.user_id) AS total_targets,
            SUM(
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM email_events ee
                    WHERE ee.target_id = ct.target_id
                      AND ee.event_type = 'reported'
                      AND datetime(ee.created_at) >= datetime(c.start_at)
                      AND datetime(ee.created_at) <= datetime(c.end_at)
                ) THEN 1 ELSE 0 END
            ) AS reported_users
        FROM campaigns c
        JOIN campaign_targets ct ON ct.campaign_id = c.campaign_id
        GROUP BY c.campaign_id
        HAVING total_targets > 0
    ");
    $campaignRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $maxRate = -1;
    foreach ($campaignRows as $row) {
        $total = (int)$row['total_targets'];
        $rep   = (int)$row['reported_users'];
        if ($total > 0) {
            $rate = $rep / $total;
            if ($rate > $maxRate) {
                $maxRate = $rate;
                $bestCampaignName  = $row['name'];
                $bestReportRatePct = $rate * 100;
            }
        }
    }
} catch (Exception $e) {
    // default values
}

/* ---------- 6) AT-RISK DEPARTMENTS ---------- */
$deptRisk = [];

try {
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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $dept    = $row['department_name'];
        $total   = (int)$row['total_targets'];
        $clicked = (int)$row['clicked_users'];
        $ratio   = $total > 0 ? $clicked / $total : 0;

        if ($ratio >= 0.5)      { $label = 'High';   $class = 'risk-high'; }
        elseif ($ratio >= 0.2)  { $label = 'Medium'; $class = 'risk-medium'; }
        else                    { $label = 'Low';    $class = 'risk-low'; }

        $deptRisk[] = [
            'department' => $dept,
            'total'      => $total,
            'clicked'    => $clicked,
            'ratio'      => $ratio,
            'label'      => $label,
            'class'      => $class,
        ];
    }

    usort($deptRisk, fn($a, $b) => $b['ratio'] <=> $a['ratio']);
    $deptRisk = array_slice($deptRisk, 0, 3);
} catch (Exception $e) {
    $deptRisk = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard – EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Shared admin + header -->
    <link rel="stylesheet" href="css/admin.css">
    <!-- Dashboard-only -->
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

<?php include 'admin_header.php'; ?>

<main class="dash-page">
    <div class="dashboard-header-row">
        <h1 class="dashboard-title">Dashboard</h1>
    </div>

    <div class="dashboard-grid">
        <!-- CARD 1 -->
        <div class="dash-card">
            <div class="dash-card-header">Campaign Overview</div>
            <div class="dash-card-body">
                <div class="card-row"><span>Total Campaigns</span><span class="badge-number"><?= (int)$totalCampaigns ?></span></div>
                <div class="card-row"><span>Running</span><span class="badge-number"><?= (int)$runningCampaigns ?></span></div>
                <div class="card-row"><span>Scheduled</span><span class="badge-number"><?= (int)$scheduledCampaigns ?></span></div>
                <div class="card-row"><span>Completed</span><span class="badge-number"><?= (int)$completedCampaigns ?></span></div>

                <div class="best-campaign-row">
                    <span class="best-label">Best Performing Campaign</span>
                    <span class="best-template"><?= htmlspecialchars($bestCampaignName) ?></span>
                    <span class="best-rate"><?= round($bestReportRatePct, 1) ?>% report rate</span>
                </div>
            </div>
        </div>

        <!-- CARD 2 -->
        <div class="dash-card">
            <div class="dash-card-header">User Behaviour Summary</div>
            <div class="dash-card-body">
                <div class="card-row"><span>Clicked</span><span class="badge-number"><?= (int)$clickedUsers ?></span></div>
                <div class="card-row"><span>Ignored</span><span class="badge-number"><?= (int)$ignoredUsers ?></span></div>
                <div class="card-row"><span>Reported</span><span class="badge-number"><?= (int)$reportedUsers ?></span></div>
            </div>
        </div>

        <!-- CARD 3 -->
        <div class="dash-card">
            <div class="dash-card-header">Behaviour Summary Chart</div>
            <div class="dash-card-body">
                <div class="chart-bars">
                    <?php
                    $max = max($emailChartData) ?: 1;
                    $labels = ['clicked' => 'Clicked', 'ignored' => 'Ignored', 'reported' => 'Reported'];

                    foreach ($labels as $key => $label):
                        $height = ($emailChartData[$key] / $max) * 120;
                    ?>
                        <div class="chart-bar-container">
                            <div class="chart-value"><?= (int)$emailChartData[$key] ?></div>
                            <div class="chart-bar bar-<?= htmlspecialchars($key) ?>" style="height: <?= (int)$height ?>px;"></div>
                            <div class="chart-label"><?= htmlspecialchars($label) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- CARD 4 -->
        <div class="dash-card">
            <div class="dash-card-header">Training Progress</div>
            <div class="dash-card-body">
                <div class="training-circle-wrapper">
                    <div class="training-circle" style="--progress: <?= (int)$trainingPercent ?>%;">
                        <div class="training-circle-inner">
                            <span>Trained</span>
                            <span class="training-circle-percent"><?= (int)$trainingPercent ?>%</span>
                        </div>
                    </div>
                </div>
                <p class="training-text">
                    <?= (int)$totalTrainedUsers ?> of <?= (int)$totalEligibleUsers ?> employees
                    who clicked <strong>“Continue Anyway”</strong> have completed the quiz.
                </p>
            </div>
        </div>

        <!-- CARD 5 -->
        <div class="dash-card">
            <div class="dash-card-header">At-Risk Departments</div>
            <div class="dash-card-body">
                <?php if (empty($deptRisk)): ?>
                    <p class="empty-text">No department data yet.</p>
                <?php else: ?>
                    <?php foreach ($deptRisk as $dept): ?>
                        <div class="risk-row">
                            <span><?= htmlspecialchars($dept['department']) ?></span>
                            <span class="risk-pill <?= htmlspecialchars($dept['class']) ?>">
                                <?= htmlspecialchars($dept['label']) ?>
                            </span>
                        </div>
                        <div class="risk-subtext">
                            Clicked: <?= (int)$dept['clicked'] ?> / <?= (int)$dept['total'] ?>
                            (<?= round($dept['ratio'] * 100) ?>%)
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- CARD 6 -->
        <div class="dash-card">
            <div class="dash-card-header">Training Completion</div>
            <div class="dash-card-body">
                <?php if (empty($deptTraining)): ?>
                    <p class="empty-text">No training data yet.</p>
                <?php else: ?>
                    <?php foreach ($deptTraining as $dt): ?>
                        <?php
                        $eligible = (int)$dt['eligible'];
                        $trained  = (int)$dt['trained'];
                        $pct      = $eligible > 0 ? round(($trained / $eligible) * 100) : 0;
                        ?>
                        <div class="risk-row">
                            <span><?= htmlspecialchars($dt['department']) ?></span>
                            <span class="training-percentage"><?= $pct ?>%</span>
                        </div>
                        <div class="risk-subtext">
                            <?= $trained ?> trained out of <?= $eligible ?> employees
                            who were required to complete the quiz.
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
