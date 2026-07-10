<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Passenger Transit Metrics Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f6f9; color: #333; margin: 40px; }
        .system-status-container { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; font-size: 14px; color: #555; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; background-color: #ccc; display: inline-block; }
        .status-dot.connected { background-color: #2ecc71; box-shadow: 0 0 8px #2ecc71; animation: pulse 2s infinite; }
        .status-dot.disconnected { background-color: #e74c3c; box-shadow: 0 0 8px #e74c3c; }
        @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.5; } 50% { transform: scale(1.05); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.5; } }
        
        .layout-container { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px; }
        .metrics-panel { flex: 1; min-width: 320px; display: flex; flex-direction: column; gap: 20px; }
        #map-viewport { flex: 2; min-width: 500px; height: 500px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 1px solid #ddd; z-index: 1; }
        
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 5px solid #3498db; }
        .time-remaining { font-size: 24px; font-weight: bold; margin: 8px 0; color: #2c3e50; }
        .metric { font-size: 13px; color: #666; margin: 4px 0; }
        .status-badge { display: inline-block; margin-top: 10px; padding: 4px 8px; font-size: 11px; font-weight: bold; border-radius: 4px; background: #e1f5fe; color: #0288d1; text-transform: uppercase; }
        .status-badge.delayed { background: #ffebee; color: #c62828; }
    </style>
</head>
<body>

    <h1>Live Passenger Transit Metrics Dashboard</h1>
    <div class="system-status-container">
        <span id="conn-dot" class="status-dot disconnected"></span>
        <span id="conn-text">Connecting to Live Event Stream Engine...</span>
    </div>

    <div class="layout-container">
        <div class="metrics-panel" id="dashboard-grid"></div>
        
        <div id="map-viewport"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const grid = document.getElementById('dashboard-grid');
        const connDot = document.getElementById('conn-dot');
        const connText = document.getElementById('conn-text');
        let socket;

        // Initialize Map Viewport centered on your localized transit network area
        const map = L.map('map-viewport').setView([40.7580, -73.5000], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Keep track of active map markers dynamically using a key-value storage object
        let mapMarkers = {};

        // Mock coordinates for static track station points (Start -> End coordinate paths)
        const tracksGeoDB = {
            "EXPRESS-B-02": [[40.7484, -73.9857], [40.7006, -73.8080]], // NYC Penn to Jamaica Line
            "METRO-A-99": [[40.7006, -73.8080], [40.7677, -73.5273]]   // Jamaica to Hicksville Line
        };

        function calculateCurrentCoordinates(trainId, currentDist, totalDist) {
            const path = tracksGeoDB[trainId];
            if (!path) return [40.7580, -73.5000];
            
            const ratio = Math.min(currentDist / totalDist, 1.0);
            const lat = path[0][0] + (path[1][0] - path[0][0]) * ratio;
            const lng = path[0][1] + (path[1][1] - path[0][1]) * ratio;
            return [lat, lng];
        }

        function connectWebSocket() {
            socket = new WebSocket("ws://localhost:8080/stream");

            socket.onopen = () => {
                connDot.className = "status-dot connected";
                connText.innerText = "System State: Connected to High-Performance Event Stream";
            };

            socket.onmessage = (event) => {
                const trains = JSON.parse(event.data);
                grid.innerHTML = "";

                trains.forEach(train => {
                    // 1. Build metrics view interface
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.innerHTML = `
                        <h3>Track Profile: ${train.TrainNumber}</h3>
                        <div class="time-remaining">${train.MinutesRemaining} Mins Remaining</div>
                        <div class="metric"><strong>Position Vector:</strong> ${train.CurrentDistance.toFixed(2)} / ${train.TotalDistance} miles</div>
                        <div class="metric"><strong>Velocity Vector:</strong> ${train.Speed.toFixed(1)} miles/min</div>
                        <div class="status-badge ${train.Status.toLowerCase()}">${train.Status}</div>
                    `;
                    grid.appendChild(card);

                    // 2. Compute geographic coordinate positioning map translation
                    const coords = calculateCurrentCoordinates(train.TrainNumber, train.CurrentDistance, train.TotalDistance);
                    
                    if (mapMarkers[train.TrainNumber]) {
                        mapMarkers[train.TrainNumber].setLatLng(coords);
                    } else {
                        mapMarkers[train.TrainNumber] = L.marker(coords).addTo(map).bindPopup(`<b>${train.TrainNumber}</b>`);
                    }
                });
            };

            socket.onclose = () => {
                connDot.className = "status-dot disconnected";
                connText.innerText = "Connection Dropped. Retrying transmission loop in 5 seconds...";
                grid.innerHTML = "";
                setTimeout(connectWebSocket, 5000);
            };
        }

        connectWebSocket();

        // Register Service Worker for asset caching mechanics
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/php-control-plane/sw.js');
            });
        }
    </script>
</body>
</html>