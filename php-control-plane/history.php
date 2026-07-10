<?php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$logs = $redis->lRange('transit-historical-logs', 0, -1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Logs Telemetry Archive</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #7ec699; margin: 40px; }
        .log-container { background: #2d2d2d; border-radius: 4px; padding: 20px; border: 1px solid #3f3f3f; }
        .log-line { border-bottom: 1px dashed #3f3f3f; padding: 8px 0; font-size: 14px; }
        .back-link { color: #61afef; text-decoration: none; display: inline-block; margin-bottom: 20px; }
    </style>
</head>
<body>
    <a class="back-link" href="dashboard.php">← Return to Main Telemetry Dashboard</a>
    <h1>System Logs Telemetry Archive (Redis Cache Buffer)</h1>
    <div class="log-container">
        <?php if (empty($logs)): ?>
            <div class="log-line">No historical entries recorded in cache map.</div>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <div class="log-line">📟 SYSTEM_LOG >> <?php echo htmlspecialchars($log); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>