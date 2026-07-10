<?php
$redis = new Redis();
$is_online = false;
try {
    $host = getenv('REDISHOST') ?: '127.0.0.1';
    $port = getenv('REDISPORT') ?: 6379;
    $password = getenv('REDISPASSWORD') ?: null;

    $redis->connect($host, (int)$port, 1.5, null, 0, 0, ['protocol' => 2]);
    if ($password) {
        $redis->auth($password);
    }
    $is_online = $redis->ping() ? true : false;
} catch (Exception $e) {
    $is_online = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTA Live Real-Time Feed Monitor</title>
    <style>
        :root {
            --bg-color: #061325;
            --panel-bg: #12243a;
            --table-header: #1e3552;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --accent-green: #10b981;
            --badge-blue: #0284c7;
            --border-color: #1e293b;
            --alert-red: #ef4444;
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: system-ui, sans-serif; padding: 40px; margin: 0; }
        h1 { color: #38bdf8; font-size: 28px; margin-bottom: 5px; font-weight: 600; }
        .subtitle { color: var(--text-muted); margin-bottom: 25px; font-size: 14px; }
        .filter-group { margin-bottom: 20px; display: flex; gap: 10px; }
        .filter-btn { background: #1e3552; color: #94a3b8; border: 1px solid #1e293b; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; transition: all 0.2s ease; }
        .filter-btn:hover, .filter-btn.active { background: #0284c7; color: white; border-color: #0284c7; }
        .incident-banner { background: linear-gradient(90deg, #7f1d1d, #991b1b); color: #fca5a5; border: 1px solid #b91c1c; padding: 16px 24px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); animation: slideDown 0.4s ease; }
        .table-container { background-color: var(--panel-bg); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.5); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: var(--table-header); color: var(--text-muted); text-transform: uppercase; font-size: 11px; font-weight: 700; letter-spacing: 0.07em; padding: 18px 24px; border-bottom: 1px solid var(--border-color); }
        td { padding: 18px 24px; border-bottom: 1px solid var(--border-color); font-size: 15px; }
        tr:hover td { background-color: #162c47; }
        .badge { background-color: var(--badge-blue); color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-family: monospace; font-weight: bold; }
        .line-profile { font-weight: 600; }
        .route-vector { color: #cbd5e1; }
        .time-cell { color: var(--text-muted); font-family: monospace; }
        .countdown-cell { color: var(--accent-green); font-family: monospace; font-weight: bold; font-size: 15px; }
        .status-boarding { color: #f59e0b; font-weight: bold; animation: pulse 1.5s infinite; }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body>
    <div id="alert-zone"></div>
    <h1>MTA Live Real-Time Feed Monitor</h1>
    <div class="subtitle">📡 Resilient SSE Engine Stream Active | Local Topology Matrix Engine</div>

    <div class="filter-group">
        <button class="filter-btn active" onclick="filterLine('ALL', this)">All Lines</button>
        <button class="filter-btn" onclick="filterLine('1', this)">1 Line</button>
        <button class="filter-btn" onclick="filterLine('A', this)">A Line</button>
        <button class="filter-btn" onclick="filterLine('Q', this)">Q Line</button>
        <button class="filter-btn" onclick="filterLine('R', this)">R Line</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Trip ID</th>
                    <th>Line Profile</th>
                    <th>Route Vector</th>
                    <th>Arrival Time</th>
                    <th>Departure Time</th>
                    <th>ETA Countdown</th>
                </tr>
            </thead>
            <tbody id="timetable-rows">
                <?php if (!$is_online): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--alert-red); padding: 40px; font-weight: bold;">
                            ❌ Redis Layer Offline. Cannot establish stream pipe.
                        </td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;">
                            ⏳ Synchronizing with streaming nodes...
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<script>
let scheduleSource, alertSource;
let reconnectDelay = 1000;
let activeFilter = 'ALL';

// Flexible Parser designed to read both "HH:MM %p" and "HH:MM:SS %p" structural timestamps perfectly
function parseTransitTime(timeStr) {
    if (!timeStr || timeStr.includes('--')) return null;
    
    const now = new Date();
    const parts = timeStr.trim().split(' ');
    if (parts.length !== 2) return null;
    
    const timeTokens = parts[0].split(':');
    const modifier = parts[1].toUpperCase();
    
    // Support both HH:MM and HH:MM:SS formats dynamically
    if (timeTokens.length < 2 || timeTokens.length > 3) return null; 
    
    let hours = parseInt(timeTokens[0], 10);
    let minutes = parseInt(timeTokens[1], 10);
    let seconds = timeTokens.length === 3 ? parseInt(timeTokens[2], 10) : 0; 
    
    if (modifier === 'PM' && hours < 12) hours += 12;
    if (modifier === 'AM' && hours === 12) hours = 0;
    
    return new Date(now.getFullYear(), now.getMonth(), now.getDate(), hours, minutes, seconds);
}

function connectScheduleStream() {
    if (!<?php echo $is_online ? 'true' : 'false'; ?>) return;
    scheduleSource = new EventSource('stream.php');

    scheduleSource.onopen = function() { reconnectDelay = 1000; };
    scheduleSource.onmessage = function(event) {
        const trains = JSON.parse(event.data);
        if (!trains || trains.length === 0) return;
        const tbody = document.getElementById('timetable-rows');
        tbody.innerHTML = ''; 

        trains.forEach(train => {
            const firstChar = train.line ? train.line.charAt(0) : 'U';
            tbody.innerHTML += `
                <tr data-arrival="${train.arrival}" data-line="${firstChar}">
                    <td><span class="badge">MTA-${train.id}</span></td>
                    <td class="line-profile">${train.line}</td>
                    <td class="route-vector">${train.origin} &rarr; ${train.destination}</td>
                    <td class="time-cell">${train.arrival}</td>
                    <td class="time-cell">${train.departure}</td>
                    <td class="countdown-cell" id="timer-${train.id}">Calculating...</td>
                </tr>`;
        });
        updateAllCountdowns();
        applyRowVisibility();
    };

    scheduleSource.onerror = function() {
        scheduleSource.close();
        setTimeout(() => { reconnectDelay = Math.min(reconnectDelay * 2, 30000); connectScheduleStream(); }, reconnectDelay);
    };
}

function filterLine(lineCharacter, buttonElement) {
    activeFilter = lineCharacter;
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    buttonElement.classList.add('active');
    applyRowVisibility();
}

function applyRowVisibility() {
    document.querySelectorAll('#timetable-rows tr').forEach(row => {
        const lineAttr = row.getAttribute('data-line');
        if (!lineAttr) return;
        row.style.display = (activeFilter === 'ALL' || lineAttr === activeFilter) ? '' : 'none';
    });
}

function updateAllCountdowns() {
    const now = new Date();
    document.querySelectorAll('#timetable-rows tr').forEach(row => {
        const arrivalStr = row.getAttribute('data-arrival');
        const timerCell = row.querySelector('.countdown-cell');
        if (!arrivalStr || !timerCell) return;

        const arrivalDate = parseTransitTime(arrivalStr);
        if (!arrivalDate) { timerCell.innerText = '--'; return; }
        
        const diffMs = arrivalDate - now;

        if (diffMs <= 0) {
            if (diffMs > -45000) {
                timerCell.innerHTML = '<span class="status-boarding">Station Boarding</span>';
            } else {
                timerCell.innerText = 'Departed'; 
                timerCell.style.color = 'var(--text-muted)';
            }
        } else {
            const totalSecs = Math.floor(diffMs / 1000);
            const mins = Math.floor(totalSecs / 60);
            const secs = totalSecs % 60;
            timerCell.innerText = `In ${mins}m ${secs}s`;
            timerCell.style.color = 'var(--accent-green)';
        }
    });
}

// Initial execution triggers
connectScheduleStream();
setInterval(updateAllCountdowns, 1000);

if (<?php echo $is_online ? 'true' : 'false'; ?>) {
    alertSource = new EventSource('alert-stream.php');
    alertSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        const banner = document.createElement('div');
        banner.className = 'incident-banner';
        banner.innerHTML = `<span>🚨 SYSTEM ALERT: Train profile ${data.train_number} is now [${data.status.toUpperCase()}].</span><span style="cursor:pointer; opacity:0.7;" onclick="this.parentElement.remove()">Dismiss</span>`;
        document.getElementById('alert-zone').insertBefore(banner, document.getElementById('alert-zone').firstChild);
    };
}
</script>
</body>
</html>