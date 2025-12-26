<?php
// campaigns.php – Manage Campaigns (auto-status)

require 'auth_admin.php';
date_default_timezone_set('Asia/Bahrain');

require 'db.php';
require_once __DIR__ . '/campaign_utils.php';

updateCampaignStatuses($pdo);

// Load campaigns
$query   = "SELECT * FROM campaigns ORDER BY start_at DESC";
$results = $pdo->query($query);

// Summary counters
$totalCampaigns     = 0;
$runningCampaigns   = 0;
$scheduledCampaigns = 0;
$completedCampaigns = 0;

$campaignRows = [];
while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
    $campaignRows[] = $row;
    $totalCampaigns++;

    $liveStatus = computeCampaignStatusFromStrings($row['start_at'], $row['end_at']);
    $status_raw = strtolower($liveStatus ?? $row['status']);

    if ($status_raw === 'running') {
        $runningCampaigns++;
    } elseif ($status_raw === 'scheduled') {
        $scheduledCampaigns++;
    } elseif ($status_raw === 'completed') {
        $completedCampaigns++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Campaigns - EnginGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/campaigns.css">
</head>
<body>

<?php include 'admin_header.php'; ?>

<main class="page-container">

    <div class="campaign-top-row">
        <h1 class="page-title">Manage Campaigns</h1>

        <a href="launch_campaign.php" class="launch-btn">
            <span class="launch-icon">⚙️</span>
            <span>Launch Campaign</span>
        </a>
    </div>

    <div class="campaign-summary-row">
        <div class="campaign-summary-chip">
            <span class="summary-label">Total</span>
            <span class="summary-value"><?= (int)$totalCampaigns ?></span>
        </div>
        <div class="campaign-summary-chip summary-running">
            <span class="summary-label">Running</span>
            <span class="summary-value"><?= (int)$runningCampaigns ?></span>
        </div>
        <div class="campaign-summary-chip summary-scheduled">
            <span class="summary-label">Scheduled</span>
            <span class="summary-value"><?= (int)$scheduledCampaigns ?></span>
        </div>
        <div class="campaign-summary-chip summary-completed">
            <span class="summary-label">Completed</span>
            <span class="summary-value"><?= (int)$completedCampaigns ?></span>
        </div>
    </div>

    <div class="campaign-toolbar">
        <label for="searchCampaign" class="search-label">Search by campaign name</label>

        <div class="search-input-wrapper">
            <input
                type="text"
                id="searchCampaign"
                class="search-input"
                placeholder="Start typing a campaign name..."
                list="campaignList"
                autocomplete="off"
            >
            <button type="button" id="clearSearch" class="clear-search-btn">Clear</button>
        </div>

        <datalist id="campaignList">
            <?php foreach ($campaignRows as $row): ?>
                <option value="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>

    <div class="campaign-table-wrapper">
        <table class="campaign-table" id="campaignTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php
                if (empty($campaignRows)) {
                    echo "<tr><td colspan='5' class='empty-row'>No campaigns found. Create one using the Launch button.</td></tr>";
                } else {
                    foreach ($campaignRows as $row) {
                        $liveStatus   = computeCampaignStatusFromStrings($row['start_at'], $row['end_at']);
                        $status_raw   = strtolower($liveStatus ?? $row['status']);
                        $status_label = ucfirst($status_raw);

                        $statusClass = 'status-pill ';
                        if ($status_raw === 'running') {
                            $statusClass .= 'status-active';
                        } elseif ($status_raw === 'scheduled') {
                            $statusClass .= 'status-scheduled';
                        } elseif ($status_raw === 'completed') {
                            $statusClass .= 'status-completed';
                        } else {
                            $statusClass .= 'status-neutral';
                        }

                        $start_date = $row['start_at'] ? date("d - m - Y", strtotime($row['start_at'])) : '-';
                        $end_date   = $row['end_at']   ? date("d - m - Y", strtotime($row['end_at']))   : '-';

                        $id       = (int)$row['campaign_id'];
                        $safeName = htmlspecialchars($row['name']);
                        $dataName = htmlspecialchars($row['name'], ENT_QUOTES);
                        ?>
                        <tr data-campaign-name="<?= $dataName ?>">
                            <td class="campaign-name-cell"><?= $safeName ?></td>

                            <td>
                                <span class="<?= $statusClass ?>">
                                    <?= htmlspecialchars($status_label) ?>
                                </span>
                            </td>

                            <td><?= $start_date ?></td>
                            <td><?= $end_date ?></td>

                            <td class="action-buttons">
                                <?php if ($status_raw === 'scheduled'): ?>
                                    <a href="edit_campaign.php?id=<?= $id ?>" class="action-btn" title="Edit campaign">
                                        📝
                                    </a>
                                <?php else: ?>
                                    <span class="action-btn action-disabled"
                                          title="You can only edit scheduled campaigns">
                                        🚫
                                    </span>
                                <?php endif; ?>

                                <a href="reports.php?campaign_id=<?= $id ?>"
                                   class="action-btn action-report"
                                   title="View report for this campaign">
                                    📊
                                </a>

                                <a href="delete_campaign.php?id=<?= $id ?>"
                                   class="action-btn delete-btn"
                                   onclick="return confirm('Are you sure you want to delete this campaign?');"
                                   title="Delete campaign">
                                    🗑️
                                </a>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

</main>

<footer class="footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

<script>
(function () {
    const searchInput = document.getElementById('searchCampaign');
    const clearBtn    = document.getElementById('clearSearch');
    const rows        = document.querySelectorAll('#campaignTable tbody tr');

    function applyFilter() {
        const term = searchInput.value.trim().toLowerCase();
        rows.forEach(row => {
            const name = (row.getAttribute('data-campaign-name') || '').toLowerCase();
            row.style.display = (!term || name.includes(term)) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', applyFilter);

    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        applyFilter();
        searchInput.focus();
    });
})();
</script>

</body>
</html>
