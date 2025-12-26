<?php
// templates.php – Admin email template management

require 'auth_admin.php';
require 'db.php';

date_default_timezone_set('Asia/Bahrain');

$error   = '';
$success = '';

function eg_allowed_sender_email(string $email): bool {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    return preg_match('/@eng1n\.local$/i', $email) === 1;
}

function eg_clean_name(string $name): string {
    return trim(preg_replace('/\s+/', ' ', $name));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateId  = (int)($_POST['template_id'] ?? 0);
    $name        = eg_clean_name($_POST['name'] ?? '');
    $senderName  = eg_clean_name($_POST['sender_name'] ?? '');
    $senderEmail = strtolower(trim($_POST['sender_email'] ?? ''));
    $subject     = trim($_POST['subject'] ?? '');
    $bodyHtml    = trim($_POST['body_html'] ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '' || $senderName === '' || $senderEmail === '' || $subject === '' || $bodyHtml === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!eg_allowed_sender_email($senderEmail)) {
        $error = 'Sender Email must use @eng1n.local';
    } else {
        try {
            if ($templateId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE email_templates
                    SET name=?, sender_name=?, sender_email=?, subject=?, body_html=?, is_active=?
                    WHERE template_id=?
                ");
                $stmt->execute([$name, $senderName, $senderEmail, $subject, $bodyHtml, $isActive, $templateId]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO email_templates
                    (name, sender_name, sender_email, subject, body_html, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, datetime('now','localtime'))
                ");
                $stmt->execute([$name, $senderName, $senderEmail, $subject, $bodyHtml, $isActive]);
            }
            header('Location: templates.php?saved=1');
            exit;
        } catch (Exception $e) {
            $error = 'Database error while saving template.';
        }
    }
}

$templates = $pdo->query("
    SELECT template_id, name, sender_email, is_active
    FROM email_templates
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$editId  = (int)($_GET['edit_id'] ?? 0);
$editing = false;

$editRow = [
    'template_id'  => 0,
    'name'         => '',
    'sender_name'  => 'EnginGuard HR',
    'sender_email' => 'hr-team@eng1n.local',
    'subject'      => '',
    'body_html'    => '',
    'is_active'    => 1,
];

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE template_id=? LIMIT 1");
    $stmt->execute([$editId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $editRow = $row;
        $editing = true;
    }
}

if (isset($_GET['saved'])) {
    $success = 'Template saved successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Templates – EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/templates.css">
</head>
<body>

<?php include 'admin_header.php'; ?>

<main class="page-container templates-page">

    <!-- HEADER -->
    <div class="templates-header-banner">
        <div>
            <h1>Email Templates</h1>
            <p>Create phishing-style templates used in campaigns.</p>
        </div>
        <span class="templates-count"><?= count($templates) ?> templates</span>
    </div>

    <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success-message"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <!-- TEMPLATE CARDS -->
    <section class="templates-library">
        <?php foreach ($templates as $tpl): ?>
            <div class="template-card">
                <div class="template-card-header">
                    <h3><?= htmlspecialchars($tpl['name']) ?></h3>
                    <span class="<?= $tpl['is_active'] ? 'status-active' : 'status-inactive' ?>">
                        <?= $tpl['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>

                <div class="template-meta">
                    <?= htmlspecialchars($tpl['sender_email']) ?>
                </div>

                <a href="templates.php?edit_id=<?= (int)$tpl['template_id'] ?>" class="edit-btn">
                    Edit →
                </a>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- EDITOR -->
    <section class="template-editor-card">
        <h2><?= $editing ? 'Edit Template' : 'Create New Template' ?></h2>

        <form method="post" class="template-form">
            <input type="hidden" name="template_id" value="<?= (int)$editRow['template_id'] ?>">

            <label>Template Name *</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name']) ?>">

            <div class="two-col">
                <div>
                    <label>Sender Name *</label>
                    <input type="text" name="sender_name" value="<?= htmlspecialchars($editRow['sender_name']) ?>">
                </div>
                <div>
                    <label>Sender Email *</label>
                    <input type="text" name="sender_email" value="<?= htmlspecialchars($editRow['sender_email']) ?>">
                </div>
            </div>

            <label>Subject *</label>
            <input type="text" name="subject" value="<?= htmlspecialchars($editRow['subject']) ?>">

            <label>Email Body *</label>
            <textarea name="body_html"><?= htmlspecialchars($editRow['body_html']) ?></textarea>

            <div class="editor-actions">
                <label class="toggle">
                    <input type="checkbox" name="is_active" <?= $editRow['is_active'] ? 'checked' : '' ?>>
                    Template is active
                </label>

                <button type="submit" class="primary-btn">
                    <?= $editing ? 'Save Changes' : 'Create Template' ?>
                </button>
            </div>
        </form>
    </section>

</main>

<footer class="footer">
    © 2025 EnginGuard. All Rights Reserved.
</footer>

</body>
</html>
