<?php
// edit_campaign.php – Edit existing campaign details + update campaign_targets (admin only)

require 'auth_admin.php';
require 'db.php';
date_default_timezone_set('Asia/Bahrain');

// Validate campaign id
$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($campaign_id <= 0) {
    header("Location: campaigns.php");
    exit;
}

$error   = '';
$success = '';

// Load campaign
$stmt = $pdo->prepare("SELECT * FROM campaigns WHERE campaign_id = ?");
$stmt->execute([$campaign_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    header("Location: campaigns.php");
    exit;
}

// Only allow editing when status is scheduled
$status_raw = strtolower($campaign['status'] ?? '');
$is_locked  = ($status_raw !== 'scheduled');

// Prepare initial form values
$name        = $campaign['name'] ?? '';
$template_id = (int)($campaign['template_id'] ?? 0);
$description = $campaign['description'] ?? '';

$start_date = $campaign['start_at'] ? date('Y-m-d', strtotime($campaign['start_at'])) : '';
$start_time = $campaign['start_at'] ? date('H:i',   strtotime($campaign['start_at'])) : '';
$end_date   = $campaign['end_at']   ? date('Y-m-d', strtotime($campaign['end_at']))   : '';
$end_time   = $campaign['end_at']   ? date('H:i',   strtotime($campaign['end_at']))   : '';

$targets = '';

// Preload targets on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tStmt = $pdo->prepare("
        SELECT u.email
        FROM campaign_targets ct
        JOIN users u ON u.user_id = ct.user_id
        WHERE ct.campaign_id = ?
          AND u.role = 'employee'
        ORDER BY u.email
    ");
    $tStmt->execute([$campaign_id]);
    $emails = $tStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $targets = implode(', ', $emails);
}

function eg_generate_token(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Resolve targets string -> employee user_ids (engin.local only)
 * Supports: department names, exact employee email, or exact employee name.
 */
function eg_resolve_target_user_ids(PDO $pdo, string $targets_raw): array
{
    $targets_raw = trim($targets_raw);
    if ($targets_raw === '') {
        return [];
    }

    $tokens  = preg_split('/[,;]+/', $targets_raw);
    $userIds = [];

    $stmtDept = $pdo->prepare("
        SELECT u.user_id
        FROM departments d
        JOIN users u ON u.department_id = d.department_id
        WHERE LOWER(d.name) = LOWER(?)
          AND u.role = 'employee'
          AND LOWER(u.email) LIKE '%@engin.local'
    ");

    $stmtUser = $pdo->prepare("
        SELECT user_id
        FROM users
        WHERE role = 'employee'
          AND LOWER(email) LIKE '%@engin.local'
          AND (LOWER(email) = LOWER(?) OR LOWER(name) = LOWER(?))
    ");

    foreach ($tokens as $raw) {
        $term = trim($raw);
        if ($term === '') {
            continue;
        }

        // 1) Department name
        $stmtDept->execute([$term]);
        $deptRows = $stmtDept->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!empty($deptRows)) {
            foreach ($deptRows as $uid) {
                $userIds[(int)$uid] = true;
            }
            continue;
        }

        // 2) User email or name
        $stmtUser->execute([$term, $term]);
        $userRow = $stmtUser->fetchColumn();
        if ($userRow) {
            $userIds[(int)$userRow] = true;
        }
    }

    return array_keys($userIds);
}

// Handle form submission (only if not locked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {

    $name        = trim($_POST['name'] ?? '');
    $template_id = (int)($_POST['template_id'] ?? 0);
    $start_date  = $_POST['start_date'] ?? '';
    $end_date    = $_POST['end_date'] ?? '';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $targets     = trim($_POST['targets'] ?? '');

    if ($name === '' || !$template_id || $start_date === '' || $end_date === '' || $start_time === '' || $end_time === '') {
        $error = 'Please fill in all required fields.';
    } else {

        $start_at = $start_date . ' ' . $start_time . ':00';
        $end_at   = $end_date   . ' ' . $end_time   . ':00';

        $startObj = new DateTime($start_at);
        $endObj   = new DateTime($end_at);

        if ($endObj <= $startObj) {
            $error = 'End date/time must be after start date/time.';
        } else {

            $userIds = eg_resolve_target_user_ids($pdo, $targets);

            if (empty($userIds)) {
                $error = 'No valid employees were found. Use employee emails (@engin.local) or department names.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Update campaign (keep scheduled)
                    $upd = $pdo->prepare("
                        UPDATE campaigns
                        SET name = ?, template_id = ?, start_at = ?, end_at = ?, description = ?, status = 'scheduled'
                        WHERE campaign_id = ?
                    ");
                    $upd->execute([$name, $template_id, $start_at, $end_at, $description, $campaign_id]);

                    // Rebuild targets
                    $pdo->prepare("DELETE FROM campaign_targets WHERE campaign_id = ?")->execute([$campaign_id]);

                    $ins = $pdo->prepare("
                        INSERT INTO campaign_targets (campaign_id, user_id, unique_link_token)
                        VALUES (?, ?, ?)
                    ");

                    foreach ($userIds as $uid) {
                        $ins->execute([$campaign_id, (int)$uid, eg_generate_token()]);
                    }

                    $pdo->commit();

                    header("Location: campaigns.php");
                    exit;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Database error while updating the campaign.';
                }
            }
        }
    }
}

// Load active templates
$templateStmt = $pdo->query("
    SELECT template_id, name
    FROM email_templates
    WHERE is_active = 1
    ORDER BY name
");
$templates = $templateStmt ? ($templateStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Campaign - EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/edit_campaign.css">
</head>
<body>

<?php include 'admin_header.php'; ?>

<main class="page-container">
    <h1 class="page-title">Edit Campaign</h1>

    <?php if ($is_locked): ?>
        <p class="info-message">
            This campaign is <strong><?= htmlspecialchars(ucfirst($status_raw)) ?></strong> and cannot be edited.
            You can still view its details below.
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" class="campaign-form">
        <div class="form-row">

            <div class="form-column">
                <div class="form-group">
                    <label for="name">Campaign Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($name) ?>"
                        <?= $is_locked ? 'disabled' : '' ?>
                    >
                </div>

                <div class="form-group">
                    <label for="template_id">Email Template</label>
                    <select
                        id="template_id"
                        name="template_id"
                        <?= $is_locked ? 'disabled' : '' ?>
                    >
                        <option value="">Select Template</option>
                        <?php foreach ($templates as $tpl): ?>
                            <option value="<?= (int)$tpl['template_id'] ?>"
                                <?= ((int)$tpl['template_id'] === (int)$template_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tpl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input
                        type="time"
                        id="start_time"
                        name="start_time"
                        value="<?= htmlspecialchars($start_time) ?>"
                        <?= $is_locked ? 'disabled' : '' ?>
                    >
                </div>

                <div class="form-group">
                    <label for="end_time">End Time</label>
                    <input
                        type="time"
                        id="end_time"
                        name="end_time"
                        value="<?= htmlspecialchars($end_time) ?>"
                        <?= $is_locked ? 'disabled' : '' ?>
                    >
                </div>
            </div>

            <div class="form-column">
                <div class="form-group">
                    <label for="targets">Targets</label>
                    <input
                        type="text"
                        id="targets"
                        name="targets"
                        placeholder="Department or employee email (@engin.local)"
                        value="<?= htmlspecialchars($targets) ?>"
                        <?= $is_locked ? 'disabled' : '' ?>
                    >
                    <small class="help-inline">Example: IT, Marketing, sara@engin.local</small>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="<?= htmlspecialchars($start_date) ?>"
                        <?= $is_locked ? 'disabled' : '' ?>
                    >
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        value="<?= htmlspecialchars($end_date) ?>"
                        <?= $is_locked ? 'disabled' : '' ?>
                    >
                </div>

                <div class="form-group">
                    <label for="description">Brief Description</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="2"
                        <?= $is_locked ? 'disabled' : '' ?>
                    ><?= htmlspecialchars($description) ?></textarea>
                </div>
            </div>

        </div>

        <div class="form-actions-row">
            <a href="campaigns.php" class="secondary-btn">Cancel</a>
            <?php if (!$is_locked): ?>
                <button type="submit" class="primary-btn">Save</button>
            <?php endif; ?>
        </div>
    </form>
</main>

<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
