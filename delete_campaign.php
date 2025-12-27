<?php
// delete_campaign.php – Delete a campaign and all related data

require 'auth_admin.php';
require 'db.php';

// Validate campaign ID
$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($campaign_id <= 0) {
    header('Location: campaigns.php');
    exit;
}

// Ensure campaign exists
$stmt = $pdo->prepare("SELECT campaign_id FROM campaigns WHERE campaign_id = ?");
$stmt->execute([$campaign_id]);
if (!$stmt->fetchColumn()) {
    header('Location: campaigns.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Get all target IDs for this campaign
    $tStmt = $pdo->prepare("SELECT target_id FROM campaign_targets WHERE campaign_id = ?");
    $tStmt->execute([$campaign_id]);
    $targetIds = $tStmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete tracking data linked to targets
    if (!empty($targetIds)) {
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));

        $pdo->prepare("DELETE FROM email_events WHERE target_id IN ($placeholders)")
            ->execute($targetIds);

        $pdo->prepare("DELETE FROM warning_decisions WHERE target_id IN ($placeholders)")
            ->execute($targetIds);

        $pdo->prepare("DELETE FROM awareness_views WHERE target_id IN ($placeholders)")
            ->execute($targetIds);

        $pdo->prepare("DELETE FROM quiz_attempts WHERE target_id IN ($placeholders)")
            ->execute($targetIds);
    }

    // Delete campaign targets
    $pdo->prepare("DELETE FROM campaign_targets WHERE campaign_id = ?")
        ->execute([$campaign_id]);

    // Delete campaign
    $pdo->prepare("DELETE FROM campaigns WHERE campaign_id = ?")
        ->execute([$campaign_id]);

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: campaigns.php?error=delete_failed');
    exit;
}

// Redirect back
header('Location: campaigns.php');
exit;
