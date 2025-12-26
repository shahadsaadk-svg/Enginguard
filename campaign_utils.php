<?php
date_default_timezone_set('Asia/Bahrain');

function eg_parse_campaign_datetime(string $value): ?DateTime
{
    $value = trim($value);
    if ($value === '') return null;

    $formats = [
        'm/d/Y H:i',
        'm/d/Y',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
    ];

    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $value);
        if ($dt instanceof DateTime) return $dt;
    }

    return null;
}

function computeCampaignStatusFromStrings(string $startStr, string $endStr): ?string
{
    $start = eg_parse_campaign_datetime($startStr);
    $end   = eg_parse_campaign_datetime($endStr);

    if (!$start || !$end) return null;

    $now = new DateTime('now');

    if ($now < $start) return 'scheduled';
    if ($now < $end)   return 'running';

    return 'completed';
}

function updateCampaignStatuses(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT campaign_id, start_at, end_at, status FROM campaigns");
    if (!$stmt) return;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current = strtolower(trim((string)$row['status']));
        $new     = computeCampaignStatusFromStrings(
            (string)$row['start_at'],
            (string)$row['end_at']
        );

        if ($new === null || $new === $current) continue;

        $update = $pdo->prepare(
            "UPDATE campaigns SET status = :status WHERE campaign_id = :id"
        );
        $update->execute([
            ':status' => $new,
            ':id'     => (int)$row['campaign_id'],
        ]);
    }
}
