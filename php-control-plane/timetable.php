<?php
$redis = new Redis();
$is_online = false;
try {
    $host = getenv('REDISHOST') ?: '127.0.0.1';
    $port = getenv('REDISPORT') ?: 6379;
    $redis->connect($host, (int)$port, 1.5, null, 0, 0, ['protocol' => 2]);
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --bg-color: #061325; --panel-bg: #12243a; --table-header: #1e3552;
            --text-main: #ffffff; --text-muted: #94a3b8; --accent-green: #10b981;
            --badge-blue: #0284c7; --border-color: #1e293b; --alert-red: #ef4444;
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: system-ui, sans-serif; padding: 40px; margin: 0; }
        h1 { color: #38bdf8; font-size: 28px; margin-bottom: 5px; font-weight: 600; }
        .subtitle { color: var(--text-muted); margin-bottom: 25px; font-size: 14px; }
        .layout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .filter-group { margin-bottom: 20px; display: flex; gap: 10px; }
        .filter-btn { background: #1e3552; color: #94a3b8; border: 1px solid #1e293b; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .filter-btn.active { background: #0284c7; color: white; border-color: #0284c7; }
        .incident-banner { background: linear-gradient(90deg, #7f1d1d, #991b1b); color: #fca5a5; padding: 16px 24px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; justify-content: space-between; }
        .table-container { background-color: var(--panel-bg); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: var(--table-header); color: var(--text-muted); text-transform: uppercase; font-size: 11px; font-weight: 700; padding: 18px 24px; border-bottom: 1px solid var(--border-color); }
        th i { margin-right: 6px; color: #38bdf8; }
        td { padding: 18px 24px; border-bottom: 1px solid var(--border-color); font-size: 15px; }
        .badge { background-color: var(--badge-blue); color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-family: monospace; }
        
        /* FIXED: Added white-space prevention to stop countdown values from wrapping into stacked rows */
        .countdown-cell { color: var(--accent-green); font-family: monospace; font-weight: bold; white-space: nowrap; }
        
        .status-boarding { color: #f59e0b; font-weight: bold; }
        #map-panel { height: 535px; background: var(--panel-bg); border-radius: 8px; border: 1px solid var(--border-color); }
    </style>
</head>
<body>
    <div id="alert-zone"></div>
    <h1>MTA Live Real-Time Feed Monitor</h1>
    <div class="subtitle"><i class="fa-solid fa-satellite-dish"></i> Resilient SSE Engine Active</div>

    <div class="filter-group">
        <button class="filter-btn active" onclick="filterLine('ALL', this)"><i class="fa-solid fa-layer-group"></i> All Lines</button>
        <button class="filter-btn" onclick="filterLine('1', this)"><i class="fa-solid fa-train-subway"></i> 1 Line</button>
        <button class="filter-btn" onclick="filterLine('A', this)"><i class="fa-solid fa-train-subway"></i> A Line</button>
        <button class="filter-btn" onclick="filterLine('Q', this)"><i class="fa-solid fa-train-subway"></i> Q Line</button>
        <button class="filter-btn" onclick="filterLine('R', this)"><i class="fa-solid fa-train-subway"></i> R Line</button>
    </div>

    <div class="layout-grid">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-hashtag"></i>Trip ID</th>
                        <th><i class="fa-solid fa-train-subway"></i>Line Profile</th>
                        <th><i class="fa-solid fa-route"></i>Route Vector</th>
                        <th><i class="fa-solid fa-clock"></i>Arrival Time</th>
                        <th><i class="fa-solid fa-clock-rotate-left"></i>Departure Time</th>
                        <th><i class="fa-solid fa-hourglass-half"></i>ETA Countdown</th>
                    </tr>
                </thead>
                <tbody id="timetable-rows">
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;"><i class="fa-solid fa-spinner fa-spin"></i> Synchronizing stream nodes...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="map-panel"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    let scheduleSource, alertSource;
    let activeFilter = 'ALL';

    const map = L.map('map-panel').setView([40.7580, -73.9855], 11);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png').addTo(map);
    let mapMarkers = [];

    function connectScheduleStream() {
        if (!<?php echo $is_online ? 'true' : 'false'; ?>) return;
        scheduleSource = new EventSource('stream.php');

        // Keep a small clock delta so countdowns use server time instead of client-local time
        window.clockDelta = window.clockDelta || 0;
        scheduleSource.onmessage = function(event) {
                try {
                    const payload = JSON.parse(event.data);
                    let serverTs = null;
                    let trains = [];

                    // Backwards-compat: support both wrapped payloads ({server_ts,trains}) and legacy arrays
                    if (Array.isArray(payload)) {
                        trains = payload;
                        serverTs = Math.floor(Date.now() / 1000);
                    } else {
                        serverTs = payload.server_ts || Math.floor(Date.now() / 1000);
                        trains = payload.trains || [];
                    }

                    // Compute a clock delta between server and client to avoid skew
                    const clientRecv = Math.floor(Date.now() / 1000);
                    window.clockDelta = serverTs - clientRecv;

                    if (!Array.isArray(trains) || trains.length === 0) return;
                
                    const tbody = document.getElementById('timetable-rows');
                    tbody.innerHTML = ''; 

                    mapMarkers.forEach(m => map.removeLayer(m));
                    mapMarkers = [];

                    trains.forEach(train => {
                        if (!train || typeof train !== 'object') return;
                    
                        const firstChar = train.line ? train.line.charAt(0) : 'U';
                        const displayArrival = train.arrival ? train.arrival : 'TBD';
                        const displayDeparture = train.departure ? train.departure : 'TBD';
                        const fallbackTs = train.arrival_timestamp ? train.arrival_timestamp : 0;

                        tbody.innerHTML += `
                            <tr data-timestamp="${fallbackTs}" data-line="${firstChar}">
                                <td><span class="badge">MTA-${train.id || 'UNK'}</span></td>
                                <td><strong>${train.line || 'Unknown Line'}</strong></td>
                                <td>${train.origin || 'Terminal'} <i class="fa-solid fa-arrow-right-long" style="color:var(--badge-blue)"></i> ${train.destination || 'In Transit'}</td>
                                <td>${displayArrival}</td>
                                <td>${displayDeparture}</td>
                                <td class="countdown-cell" id="timer-${train.id || Math.random()}">Calculating...</td>
                            </tr>`;

                        if (train.lat && train.lon) {
                            const marker = L.marker([parseFloat(train.lat), parseFloat(train.lon)])
                                .bindPopup(`<b>Train ${train.id || 'UNK'}</b><br>${train.line || ''}`)
                                .addTo(map);
                            mapMarkers.push(marker);
                        }
                    });
                    updateAllCountdowns();
                    applyRowVisibility();
                } catch (err) {
                    console.error("Stream Parsing Error safely bypassed:", err);
                }
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
            if (lineAttr) {
                row.style.display = (activeFilter === 'ALL' || lineAttr === activeFilter) ? '' : 'none';
            }
        });
    }

    function updateAllCountdowns() {
        // Use server-synced epoch (client time + clockDelta) to avoid clock skew
        const nowServerEpoch = Math.floor(Date.now() / 1000) + (window.clockDelta || 0);
        document.querySelectorAll('#timetable-rows tr').forEach(row => {
            const targetTimestamp = parseInt(row.getAttribute('data-timestamp'), 10);
            const timerCell = row.querySelector('.countdown-cell');
            if (!timerCell) return;
            
            if (!targetTimestamp || isNaN(targetTimestamp)) {
                timerCell.innerHTML = '<span style="color:var(--text-muted)">No Schedule</span>';
                return;
            }

            const diffSecs = targetTimestamp - nowServerEpoch;

            if (diffSecs <= 0) {
                if (diffSecs > -45) {
                    timerCell.innerHTML = '<span class="status-boarding"><i class="fa-solid fa-triangle-exclamation"></i> Boarding</span>';
                } else {
                    timerCell.innerHTML = '<span style="color:var(--text-muted)">Departed</span>';
                }
            } else {
                const mins = Math.floor(diffSecs / 60);
                const secs = diffSecs % 60;
                timerCell.innerHTML = `<i class="fa-solid fa-bolt"></i> In ${mins}m ${secs}s`;
            }
        });
    }

    connectScheduleStream();
    setInterval(updateAllCountdowns, 1000);

    if (<?php echo $is_online ? 'true' : 'false'; ?>) {
        alertSource = new EventSource('alert-stream.php');
        alertSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                if (!data.train_number || !data.status) return;
                const banner = document.createElement('div');
                banner.className = 'incident-banner';
                banner.innerHTML = `<span>🚨 <strong>ALERT:</strong> Train ${data.train_number} is [${data.status}].</span><span style="cursor:pointer" onclick="this.parentElement.remove()">✕</span>`;
                document.getElementById('alert-zone').insertBefore(banner, document.getElementById('alert-zone').firstChild);
            } catch(e) {}
        };
    }
    </script>
</body>
</html>