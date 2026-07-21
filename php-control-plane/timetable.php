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
        .subtitle { color: var(--text-muted); margin-bottom: 10px; font-size: 14px; }
        .status-line { color: var(--text-muted); font-size: 12px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .status-pill { background: rgba(2,132,199,0.15); border: 1px solid rgba(56,189,248,0.25); color: #7dd3fc; padding: 4px 8px; border-radius: 999px; font-family: monospace; }
        .layout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .filter-group { margin-bottom: 20px; display: flex; gap: 10px; }
        .filter-btn { background: #1e3552; color: #94a3b8; border: 1px solid #1e293b; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .filter-btn.active { background: #0284c7; color: white; border-color: #0284c7; }
        .incident-banner { background: linear-gradient(90deg, #7f1d1d, #991b1b); color: #fca5a5; padding: 16px 24px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; justify-content: space-between; }
        .table-container { background-color: var(--panel-bg); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: var(--table-header); color: var(--text-muted); text-transform: uppercase; font-size: 11px; font-weight: 700; padding: 18px 24px; border-bottom: 1px solid var(--border-color); }
        th i, td i { display: inline-block; min-width: 16px; text-align: center; } /* Improved icon alignment */
        th i { color: #38bdf8; }
        td { padding: 18px 24px; border-bottom: 1px solid var(--border-color); font-size: 15px; }
        .badge { background-color: var(--badge-blue); color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-family: monospace; }
        .countdown-cell { color: var(--accent-green); font-family: monospace; font-weight: bold; white-space: nowrap; }
        .status-boarding { color: #f59e0b; font-weight: bold; }
        #map-panel { height: 535px; background: var(--panel-bg); border-radius: 8px; border: 1px solid var(--border-color); position: relative; overflow: hidden; }
    </style>
</head>
<body>
    <div id="alert-zone"></div>
    <h1>MTA Live Real-Time Feed Monitor</h1>
    <div class="subtitle"><i class="fa-solid fa-satellite-dish"></i> Resilient SSE Engine Active</div>
    <div id="status-line" class="status-line">
        <span class="status-pill" id="status-stream">Stream: connecting…</span>
        <span class="status-pill" id="status-clock">Clock: syncing…</span>
        <span class="status-pill" id="status-count">Trains: 0</span>
    </div>

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
    window.lastStreamSequence = 0;
    let map, mapMarkers = [];

    // Map initialization with error guard[cite: 1]
    if (typeof L !== 'undefined') {
        map = L.map('map-panel').setView([40.7580, -73.9855], 11);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png').addTo(map);
    } else {
        document.getElementById('map-panel').innerHTML = 
            `<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);font-size:13px;flex-direction:column;gap:10px;">
                <i class="fa-solid fa-earth-americas" style="font-size:24px"></i> Map Service Unavailable
             </div>`;
    }

    function updateStatusLine() {
        const streamEl = document.getElementById('status-stream');
        const clockEl = document.getElementById('status-clock');
        const countEl = document.getElementById('status-count');
        if (!streamEl || !clockEl || !countEl) return;

        const trainCount = document.querySelectorAll('#timetable-rows tr').length;
        countEl.textContent = `Trains: ${trainCount > 0 ? trainCount : 0}`;

        if (window.clockDelta === undefined || window.clockDelta === null) {
            clockEl.textContent = 'Clock: syncing…';
        } else {
            clockEl.textContent = `Clock: ${window.clockDelta >= 0 ? '+' : ''}${window.clockDelta}s`;
        }

        if (scheduleSource && scheduleSource.readyState === EventSource.OPEN) {
            streamEl.textContent = 'Stream: live';
        } else {
            streamEl.textContent = 'Stream: connecting…';
        }
    }

    function connectScheduleStream() {
        if (!<?php echo $is_online ? 'true' : 'false'; ?>) return;
        scheduleSource = new EventSource('stream.php');
        updateStatusLine();

        window.clockDelta = window.clockDelta || 0;
        scheduleSource.onmessage = function(event) {
            try {
                const payload = JSON.parse(event.data);
                let serverTs = Math.floor(Date.now() / 1000);
                let trains = Array.isArray(payload) ? payload : (payload.trains || []);
                let sequence = payload.sequence || null;

                if (sequence !== null) {
                    if (window.lastStreamSequence !== undefined && sequence <= window.lastStreamSequence) return;
                    window.lastStreamSequence = sequence;
                }

                updateStatusLine();

                const tbody = document.getElementById('timetable-rows');
                tbody.innerHTML = '';
                updateStatusLine();

                // Clear existing markers safely
                if (map) mapMarkers.forEach(m => map.removeLayer(m));
                mapMarkers = [];

                trains.forEach((train, index) => {
                    if (!train || typeof train !== 'object') return;

                    // Explicit icon definition to bypass tracking prevention asset blocking[cite: 1]
                    const defaultIcon = (typeof L !== 'undefined') ? L.icon({
                        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
                    }) : null;

                    tbody.innerHTML += `
                        <tr data-timestamp="${train.arrival_timestamp || 0}" data-line="${train.line?.charAt(0) || 'U'}" data-eta="${train.eta_seconds || ''}">
                            <td><span class="badge">MTA-${train.id || 'UNK'}</span></td>
                            <td><strong>${train.line || 'Unknown'}</strong></td>
                            <td>${train.origin || 'Terminal'} <i class="fa-solid fa-arrow-right-long" style="color:var(--badge-blue)"></i> ${train.destination || 'In Transit'}</td>
                            <td>${train.arrival || 'TBD'}</td>
                            <td>${train.departure || 'TBD'}</td>
                            <td class="countdown-cell" id="timer-${index}">Calculating...</td>
                        </tr>`;

                    if (map && defaultIcon && train.lat && train.lon) {
                        const marker = L.marker([parseFloat(train.lat), parseFloat(train.lon)], { icon: defaultIcon })
                            .bindPopup(`<b>Train ${train.id || 'UNK'}</b><br>${train.line || ''}`)
                            .addTo(map);
                        mapMarkers.push(marker);
                    }
                });
                updateAllCountdowns();
                applyRowVisibility();
            } catch (err) { console.error("Stream parsing error:", err); }
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
            row.style.display = (activeFilter === 'ALL' || lineAttr === activeFilter) ? '' : 'none';
        });
    }

    function updateAllCountdowns() {
        const nowServerEpoch = Math.floor(Date.now() / 1000) + (window.clockDelta || 0);
        document.querySelectorAll('#timetable-rows tr').forEach(row => {
            const timerCell = row.querySelector('.countdown-cell');
            if (!timerCell) return;

            const etaSeconds = Number(row.getAttribute('data-eta'));
            const targetTimestamp = parseInt(row.getAttribute('data-timestamp'), 10);
            let diffSecs = (etaSeconds > 0) ? etaSeconds : (targetTimestamp - nowServerEpoch);

            if (diffSecs <= 0 && diffSecs > -45) {
                timerCell.innerHTML = '<span class="status-boarding"><i class="fa-solid fa-triangle-exclamation"></i> Boarding</span>';
            } else if (diffSecs <= -45) {
                timerCell.innerHTML = '<span style="color:var(--text-muted)">Departed</span>';
            } else {
                timerCell.innerHTML = `<i class="fa-solid fa-bolt"></i> In ${Math.floor(diffSecs / 60)}m ${diffSecs % 60}s`;
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