<?php
$redis = new Redis();
$is_online = false;
try {
    $host = getenv('REDISHOST') ?: '127.0.0.1';
    $port = getenv('REDISPORT') ?: 6379;
    $password = getenv('REDISPASSWORD') ?: null;
    $redis->connect($host, (int)$port, 1.5, null, 0, 0, ['protocol' => 2]);
    if ($password) { $redis->auth($password); }
    $is_online = $redis->ping() ? true : false;
} catch (Exception $e) { $is_online = false; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTA Live Real-Time Feed Monitor</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        .filter-btn { background: #1e3552; color: #94a3b8; border: 1px solid #1e293b; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .filter-btn.active { background: #0284c7; color: white; }
        .table-container { background-color: var(--panel-bg); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 18px 24px; border-bottom: 1px solid var(--border-color); }
        th { background-color: var(--table-header); color: var(--text-muted); font-size: 11px; text-transform: uppercase; }
        .badge { background-color: var(--badge-blue); color: white; padding: 4px 10px; border-radius: 4px; font-family: monospace; }
        .countdown-cell { color: var(--accent-green); font-family: monospace; font-weight: bold; }
        /* FEATURE 1: Visual Map Panel Container */
        #map-panel { height: 500px; background: var(--panel-bg); border-radius: 8px; border: 1px solid var(--border-color); }
        .incident-banner { background: #991b1b; color: #fca5a5; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div id="alert-zone"></div>
    <h1>MTA Live Real-Time Feed Monitor</h1>
    <div class="subtitle">📡 Resilient SSE Engine Matrix Engine</div>

    <div class="filter-group">
        <button class="filter-btn active" onclick="filterLine('ALL', this)">All Lines</button>
    </div>

    <div class="layout-grid">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Trip ID</th>
                        <th>Line Profile</th>
                        <th>Route Vector</th>
                        <th>Arrival Time</th>
                        <th>ETA Countdown</th>
                    </tr>
                </thead>
                <tbody id="timetable-rows">
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">⏳ Synchronizing with streaming nodes...</td></tr>
                </tbody>
            </table>
        </div>
        
        <div id="map-panel"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    let scheduleSource, alertSource;
    let activeFilter = 'ALL';
    
    // FEATURE 1: Map Initialization Context pointing at NYC
    const map = L.map('map-panel').setView([40.7580, -73.9855], 11);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png').addTo(map);
    let mapMarkers = [];

    function parseTransitTime(timeStr) {
        if (!timeStr || timeStr.includes('--')) return null;
        const now = new Date();
        const parts = timeStr.trim().split(' ');
        const timeTokens = parts[0].split(':');
        let hours = parseInt(timeTokens[0], 10);
        let minutes = parseInt(timeTokens[1], 10);
        let seconds = timeTokens.length === 3 ? parseInt(timeTokens[2], 10) : 0;
        if (parts[1].toUpperCase() === 'PM' && hours < 12) hours += 12;
        if (parts[1].toUpperCase() === 'AM' && hours === 12) hours = 0;
        return new Date(now.getFullYear(), now.getMonth(), now.getDate(), hours, minutes, seconds);
    }

    function connectScheduleStream() {
        if (!<?php echo $is_online ? 'true' : 'false'; ?>) return;
        scheduleSource = new EventSource('stream.php');

        scheduleSource.onmessage = function(event) {
            const trains = JSON.parse(event.data);
            if (!trains || trains.length === 0) return;
            
            const tbody = document.getElementById('timetable-rows');
            tbody.innerHTML = '';

            // FEATURE 1: Clear old visual map pins before updating map view layout
            mapMarkers.forEach(m => map.removeLayer(m));
            mapMarkers = [];

            trains.forEach(train => {
                const firstChar = train.line ? train.line.charAt(0) : 'U';
                tbody.innerHTML += `
                    <tr data-arrival="${train.arrival}" data-line="${firstChar}">
                        <td><span class="badge">MTA-${train.id}</span></td>
                        <td><strong>${train.line}</strong></td>
                        <td>${train.origin} &rarr; ${train.destination}</td>
                        <td>${train.arrival}</td>
                        <td class="countdown-cell" id="timer-${train.id}">Calculating...</td>
                    </tr>`;

                // FEATURE 1: Dynamically drop live geolocated pins onto vector map map topology
                if(train.lat && train.lon) {
                    const marker = L.marker([train.lat, train.lon])
                        .bindPopup(`<b>Train ${train.id}</b><br>${train.line}`)
                        .addTo(map);
                    mapMarkers.push(marker);
                }
            });
            updateAllCountdowns();
        };
    }

    function updateAllCountdowns() {
        const now = new Date();
        document.querySelectorAll('#timetable-rows tr').forEach(row => {
            const arrivalStr = row.getAttribute('data-arrival');
            const timerCell = row.querySelector('.countdown-cell');
            if (!arrivalStr || !timerCell) return;

            const arrivalDate = parseTransitTime(arrivalStr);
            if (!arrivalDate) return;
            const diffMs = arrivalDate - now;

            if (diffMs <= 0) {
                timerCell.innerText = 'Departed';
            } else {
                const totalSecs = Math.floor(diffMs / 1000);
                timerCell.innerText = `In ${Math.floor(totalSecs / 60)}m ${totalSecs % 60}s`;
            }
        });
    }

    connectScheduleStream();
    setInterval(updateAllCountdowns, 1000);

    if (<?php echo $is_online ? 'true' : 'false'; ?>) {
        alertSource = new EventSource('alert-stream.php');
        alertSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            const banner = document.createElement('div');
            banner.className = 'incident-banner';
            banner.innerHTML = `<span>🚨 SYSTEM ALERT: Override Train ${data.train_number} status set to [${data.status}].</span>`;
            document.getElementById('alert-zone').insertBefore(banner, document.getElementById('alert-zone').firstChild);
        };
    }
    </script>
</body>
</html>