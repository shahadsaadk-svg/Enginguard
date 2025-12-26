<?php
// launch_campaign.php – Create a new campaign

require 'auth_admin.php';
date_default_timezone_set('Asia/Bahrain');
require 'db.php';

$departments  = [];
$deptNameToId = [];
$employees    = [];
$error        = '';
$oldTargets   = [];

try {
    $stmt = $pdo->query("SELECT department_id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($departments as $d) {
        $deptNameToId[strtolower($d['name'])] = (int)$d['department_id'];
    }
} catch (Exception $e) {
    $error = 'Unable to load departments.';
}

try {
    $stmt = $pdo->query("
        SELECT user_id, name, email, department_id
        FROM users
        WHERE role = 'employee'
        ORDER BY name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Unable to load employees.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tmp = $_POST['targets'] ?? [];
    if (!is_array($tmp)) $tmp = [$tmp];
    $oldTargets = $tmp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $template_id = (int)($_POST['template_id'] ?? 0);
    $start_date  = $_POST['start_date'] ?? '';
    $end_date    = $_POST['end_date'] ?? '';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';
    $description = trim($_POST['description'] ?? '');

    $targetsInput = $_POST['targets'] ?? [];
    if (!is_array($targetsInput)) $targetsInput = [$targetsInput];

    if ($name === '' || !$template_id || $start_date === '' || $end_date === '' || $start_time === '' || $end_time === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $start_at = $start_date . ' ' . $start_time . ':00';
        $end_at   = $end_date   . ' ' . $end_time   . ':00';

        $now      = new DateTime('now');
        $startObj = new DateTime($start_at);
        $endObj   = new DateTime($end_at);

        if ($endObj <= $startObj) {
            $error = 'End date/time must be after start date/time.';
        } else {
            $tokens = [];
            foreach ($targetsInput as $val) {
                foreach (explode(',', $val) as $piece) {
                    $piece = trim($piece);
                    if ($piece !== '') $tokens[] = $piece;
                }
            }

            $selectedUserIds = [];

            foreach ($tokens as $token) {
                $tokenLower = strtolower($token);

                if (stripos($tokenLower, 'deptid:') === 0) {
                    $deptId = (int)substr($tokenLower, strlen('deptid:'));
                    if ($deptId > 0) {
                        $q = $pdo->prepare("
                            SELECT user_id
                            FROM users
                            WHERE department_id = :dept_id
                              AND role = 'employee'
                        ");
                        $q->execute([':dept_id' => $deptId]);
                        while ($u = $q->fetch(PDO::FETCH_ASSOC)) {
                            $selectedUserIds[(int)$u['user_id']] = true;
                        }
                    }
                    continue;
                }

                if (stripos($tokenLower, 'user:') === 0) {
                    $emailLower = substr($tokenLower, strlen('user:'));
                    foreach ($employees as $emp) {
                        if (strtolower($emp['email']) === $emailLower) {
                            $selectedUserIds[(int)$emp['user_id']] = true;
                        }
                    }
                    continue;
                }

                if (isset($deptNameToId[$tokenLower])) {
                    $deptId = $deptNameToId[$tokenLower];

                    $q = $pdo->prepare("
                        SELECT user_id
                        FROM users
                        WHERE department_id = :dept_id
                          AND role = 'employee'
                    ");
                    $q->execute([':dept_id' => $deptId]);
                    while ($u = $q->fetch(PDO::FETCH_ASSOC)) {
                        $selectedUserIds[(int)$u['user_id']] = true;
                    }
                    continue;
                }

                foreach ($employees as $emp) {
                    if (strtolower($emp['name']) === $tokenLower || strtolower($emp['email']) === $tokenLower) {
                        $selectedUserIds[(int)$emp['user_id']] = true;
                    }
                }
            }

            if (empty($selectedUserIds)) {
                $error = 'No valid employees were found for the selected targets. Please select at least one real employee or department.';
            } else {
                if ($now < $startObj) {
                    $status = 'scheduled';
                } elseif ($now >= $startObj && $now < $endObj) {
                    $status = 'running';
                } else {
                    $status = 'completed';
                }

                try {
                    $insert = $pdo->prepare("
                        INSERT INTO campaigns (name, start_at, end_at, status, created_by, template_id, description)
                        VALUES (:name, :start_at, :end_at, :status, :created_by, :template_id, :description)
                    ");

                    $insert->execute([
                        ':name'        => $name,
                        ':start_at'    => $start_at,
                        ':end_at'      => $end_at,
                        ':status'      => $status,
                        ':created_by'  => (int)$_SESSION['user_id'],
                        ':template_id' => $template_id,
                        ':description' => $description,
                    ]);

                    $campaign_id = (int)$pdo->lastInsertId();

                    $pdo->beginTransaction();

                    $insTarget = $pdo->prepare("
                        INSERT INTO campaign_targets (campaign_id, user_id, unique_link_token)
                        VALUES (:campaign_id, :user_id, :token)
                    ");

                    foreach (array_keys($selectedUserIds) as $uid) {
                        $token = bin2hex(random_bytes(16));
                        $insTarget->execute([
                            ':campaign_id' => $campaign_id,
                            ':user_id'     => (int)$uid,
                            ':token'       => $token,
                        ]);
                    }

                    $pdo->commit();
                    header('Location: campaigns.php');
                    exit;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Database error while creating the campaign.';
                }
            }
        }
    }
}

$templateRows = [];
try {
    $stmt = $pdo->query("
        SELECT template_id, name
        FROM email_templates
        WHERE is_active = 1
        ORDER BY name
    ");
    $templateRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $templateRows = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Launch Campaign – EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/launch_campaign.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="launch-body">

<main class="page-container launch-page">
    <div class="launch-header-row">
        <div>
            <h1 class="page-title">Launch Campaign</h1>
            <p class="page-subtitle">Create a new phishing simulation campaign and select targets.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <p class="login-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" class="launch-form">
        <div class="form-row">

            <div class="form-column">
                <div class="form-card">
                    <div class="card-head">
                        <h2 class="form-card-title">Campaign Details</h2>
                        <span class="card-note">Required</span>
                    </div>

                    <div class="form-group">
                        <label for="name">Campaign Name</label>
                        <input type="text" id="name" name="name"
                               placeholder="Quarterly phishing simulation – Finance"
                               autocomplete="off"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="template_id">Email Template</label>
                        <select id="template_id" name="template_id">
                            <option value="">Select Template</option>
                            <?php foreach ($templateRows as $tpl): ?>
                                <option value="<?= (int)$tpl['template_id'] ?>"
                                    <?= (isset($_POST['template_id']) && (int)$_POST['template_id'] === (int)$tpl['template_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tpl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="time-row">
                        <div class="form-group">
                            <label for="start_time">Start Time</label>
                            <input type="time" id="start_time" name="start_time"
                                   value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <input type="time" id="end_time" name="end_time"
                                   value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Brief Description</label>
                        <textarea id="description" name="description" rows="3"
                                  placeholder="Short description shown to admins only"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mini-tip">
                        <span class="mini-tip-icon">💡</span>
                        <span>Tip: Use a clear name like “Finance – Password Reset Test”.</span>
                    </div>
                </div>
            </div>

            <div class="form-column">
                <div class="form-card">
                    <div class="card-head">
                        <h2 class="form-card-title">Targets &amp; Schedule</h2>
                        <span class="card-note">Required</span>
                    </div>

                    <div class="form-group">
                        <label for="targets">Targets</label>
                        <select id="targets" name="targets[]" multiple="multiple" style="width:100%;">
                            <optgroup label="Departments">
                                <?php foreach ($departments as $dept): ?>
                                    <?php
                                    $val = 'deptid:' . (int)$dept['department_id'];
                                    $sel = in_array($val, $oldTargets, true) ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($dept['name']) ?> (Department)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>

                            <optgroup label="Employees">
                                <?php foreach ($employees as $emp): ?>
                                    <?php
                                    $val = 'user:' . strtolower($emp['email']);
                                    $sel = in_array($val, $oldTargets, true) ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>

                        <small class="help-text">
                            Search and select, or type a name/email and press Enter.
                        </small>
                    </div>

                    <div class="time-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date"
                                   value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date"
                                   value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="hint-badge">
                        You can mix departments + individual employees in one campaign.
                    </div>
                </div>
            </div>

        </div>

        <div class="form-actions-row">
            <a href="campaigns.php" class="secondary-btn">Cancel</a>
            <button type="submit" class="primary-btn">Launch Campaign</button>
        </div>
    </form>
</main>

<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function () {
    $('#targets').select2({
        placeholder: 'Select departments and employees',
        tags: true,
        tokenSeparators: [',']
    });
});
</script>

</body>
</html>
