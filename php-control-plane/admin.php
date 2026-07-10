<?php
$redis = new Redis();
$active_trips = [];
$is_online = false;
$error_msg = '';

try {
    $host = getenv('REDISHOST') ?: '127.0.0.1';
    $port = getenv('REDISPORT') ?: 6379;
    $password = getenv('REDISPASSWORD') ?: null;

    $redis->connect($host, (int)$port, 1.5, null, 0, 0, ['protocol' => 2]);
    if ($password) {
        $redis->auth($password);
    }
    $is_online = $redis->ping() ? true : false;
    
    // Pull the active vectors to populate our drop-down list dynamically
    $schedule_raw = $redis->get('mta-live-schedule');
    if ($schedule_raw) {
        $active_trips = json_decode($schedule_raw, true) ?? [];
    }
} catch (Exception $e) {
    $is_online = false;
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
            --bg-color: #061325;
            --panel-bg: #12243a;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --accent-blue: #0284c7;
            --alert-red: #ef4444;
            --border-color: #1e293b;
            --accent-green: #10b981;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 40px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 20px;
        }

        h1 {
            font-size: 28px;
            color: #38bdf8;
            margin: 0;
            font-weight: 600;
        }

        .btn-logout {
            background-color: var(--alert-red);
            color: white;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }

        .btn-logout:hover {
            background-color: #b91c1c;
        }

        /* Responsive Grid Dashboard Wrapper */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        @media (min-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 450px 1fr;
            }
        }

        .control-panel, .monitor-panel {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.5);
        }

        h2 {
            font-size: 18px;
            color: #cbd5e1;
            margin-top: 0;
            margin-bottom: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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
            background-color: #061325;
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

        /* Metric Cards styling */
        .metrics-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .metric-card {
            background-color: #061325;
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 6px;
        }

        .metric-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .metric-value {
            font-size: 20px;
            font-weight: bold;
            color: #f8fafc;
            margin-top: 5px;
        }

        /* Console log simulator styling */
        .console-box {
            background-color: #030a13;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            font-family: monospace;
            font-size: 13px;
            height: 180px;
            overflow-y: auto;
            color: #38bdf8;
            line-height: 1.6;
        }

        .console-entry {
            margin-bottom: 6px;
            border-left: 2px solid var(--accent-blue);
            padding-left: 8px;
        }
    </style>
</head>
<body>

    <div class="header-container">
        <h1>Transit Dispatcher Control Room</h1>
        <button class="btn-logout" onclick="alert('Logging out of terminal instance...')">Log Out Station</button>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="status-banner" style="display: block; background-color: #7f1d1d; color: #fca5a5; margin-bottom: 30px;">
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        
        <div class="control-panel">
            <h2>Command Intercept</h2>
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

        <div class="monitor-panel">
            <h2>Live Telemetry & Signals</h2>
            
            <div class="metrics-container">
                <div class="metric-card">
                    <div class="metric-title">Shared Cache Node</div>
                    <div class="metric-value" style="color: <?php echo $is_online ? 'var(--accent-green)' : 'var(--alert-red)'; ?>;">
                        <?php echo $is_online ? 'CONNECTED' : 'OFFLINE'; ?>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">Active Fleet Trackers</div>
                    <div class="metric-value" style="color: #38bdf8;"><?php echo count($active_trips); ?> Vectors</div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">System Intercept</div>
                    <div class="metric-value" style="color: var(--accent-green);">NOMINAL</div>
                </div>
            </div>

            <label>Dispatcher Terminal Action Log</label>
            <div class="console-box" id="console-log">
                <div class="console-entry" style="color: var(--text-muted);">[SYSTEM] Terminal terminal pipeline linked successfully. Waiting for broadcasts...</div>
            </div>
        </div>

    </div>

<script>
function sendDisruption(event) {
    event.preventDefault();
    
    const trainNum = document.getElementById('track-profile').value;
    const statusVal = document.getElementById('operational-status').value;
    const msgBox = document.getElementById('status-message');
    const consoleBox = document.getElementById('console-log');
    
    if (!trainNum) {
        alert("Please select a live train profile mapping.");
        return;
    }

    fetch('broadcast-endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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

            // Append live event straight into our custom terminal monitoring log block
            const timestamp = new Date().toLocaleTimeString();
            consoleBox.innerHTML += `<div class="console-entry">[${timestamp}] Broadcast Payload routing complete: ${trainNum} -> [${statusVal.toUpperCase()}]</div>`;
            consoleBox.scrollTop = consoleBox.scrollHeight;
        } else {
            msgBox.style.display = 'block';
            msgBox.style.backgroundColor = '#7f1d1d';
            msgBox.style.color = '#fca5a5';
            msgBox.innerText = "❌ Core failed to route alert message payload.";
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