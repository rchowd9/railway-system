<?php
// Connect to local Redis stack
$redis = new Redis();
$active_trips = [];

try {
    $redis->connect('127.0.0.1', 6379, 1.5, null, 0, 0, ['protocol' => 2]);
    
    // Pull the active vectors to populate our drop-down list dynamically
    $schedule_raw = $redis->get('mta-live-schedule');
    if ($schedule_raw) {
        $active_trips = json_decode($schedule_raw, true) ?? [];
    }
} catch (Exception $e) {
    $error_msg = "Redis Core Offline: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transit Dispatcher Control Room</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --panel-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-blue: #0284c7;
            --alert-red: #ef4444;
            --border-color: #334155;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }

        .control-panel {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 30px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        h1 {
            font-size: 24px;
            color: #38bdf8;
            margin-top: 0;
            margin-bottom: 25px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        select {
            width: 100%;
            padding: 12px;
            background-color: #0f172a;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 6px;
            font-size: 15px;
            outline: none;
            cursor: pointer;
            font-family: monospace;
        }

        select:focus {
            border-color: var(--accent-blue);
        }

        .btn-broadcast {
            width: 100%;
            background-color: var(--accent-blue);
            color: white;
            border: none;
            padding: 14px;
            font-size: 15px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s ease;
            margin-top: 10px;
        }

        .btn-broadcast:hover {
            background-color: #0369a1;
        }

        .status-banner {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: none;
        }
    </style>
</head>
<body>

    <div class="control-panel">
        <h1>Transit Dispatcher Control Room</h1>
        
        <div id="status-message" class="status-banner"></div>

        <form id="dispatch-form" onsubmit="sendDisruption(event)">
            <div class="form-group">
                <label for="track-profile">Select Active Fleet Vector</label>
                <select id="track-profile" name="train_number" required>
                    <?php if (empty($active_trips)): ?>
                        <option value="">-- No Active Ingestion Vectors Found --</option>
                    <?php else: ?>
                        <option value="">-- Select Active Trip ID --</option>
                        <?php foreach ($active_trips as $train): ?>
                            <option value="MTA-<?php echo htmlspecialchars($train['id']); ?>">
                                MTA-<?php echo htmlspecialchars($train['id']); ?> (<?php echo htmlspecialchars($train['line']); ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="operational-status">Set Operational Status</label>
                <select id="operational-status" name="status" required>
                    <option value="on time">🟢 On Time</option>
                    <option value="delayed">🔴 Delayed</option>
                    <option value="bypassing stations">🟡 Bypassing Stations</option>
                    <option value="holding">🟠 Holding at Platform</option>
                    <option value="suspended">❌ Suspended</option>
                </select>
            </div>

            <button type="submit" class="btn-broadcast">Broadcast System Disruption</button>
        </form>
    </div>

<script>
function sendDisruption(event) {
    event.preventDefault();
    
    const trainNum = document.getElementById('track-profile').value;
    const statusVal = document.getElementById('operational-status').value;
    const msgBox = document.getElementById('status-message');
    
    if (!trainNum) {
        alert("Please select a live train profile mapping.");
        return;
    }

    // Submit payload asynchronously to the broadcast engine script
    fetch('broadcast-endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type: application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            'train_number': trainNum,
            'status': statusVal
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            msgBox.style.display = 'block';
            msgBox.style.backgroundColor = '#1e3a8a';
            msgBox.style.color = '#93c5fd';
            msgBox.style.border = '1px solid #2563eb';
            msgBox.innerText = `✅ Published alert: ${trainNum} status set to [${statusVal.toUpperCase()}].`;
        }
    })
    .catch(err => {
        msgBox.style.display = 'block';
        msgBox.style.backgroundColor = '#7f1d1d';
        msgBox.style.color = '#fca5a5';
        msgBox.innerText = "❌ Network failure dispatching network pipe payload.";
    });
}
</script>
</body>
</html>