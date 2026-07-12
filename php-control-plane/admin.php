<?php
session_start();
// FEATURE 5: Simulating a simple Role Check RBAC guard
$_SESSION['user_role'] = $_SESSION['user_role'] ?? 'dispatcher'; 

if ($_SESSION['user_role'] !== 'dispatcher') {
    die("❌ Access Denied: Unauthorized Station Personnel Profile.");
}

$redis = new Redis();
$redis->connect('127.0.0.1', 6379, 1.5, null, 0, 0, ['protocol' => 2]);

// FEATURE 4: Handle form submission for terminal injection overrides
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_override'])) {
        $redis->del('mta-admin-override');
    } else {
        $custom_schedule = [
            [
                'id' => $_POST['trip_id'],
                'line' => $_POST['line'] . " Line Commuter",
                'origin' => $_POST['origin'],
                'destination' => $_POST['destination'],
                'arrival' => date('h:i:s A', strtotime('+5 minutes')),
                'departure' => date('h:i:s A', strtotime('+15 minutes')),
                'lat' => 40.7580,
                'lon' => -73.9855
            ]
        ];
        $redis->set('mta-admin-override', json_encode($custom_schedule));
        
        // FEATURE 3: Hook into SMS/Alert publisher loop
        $alert_payload = json_encode(['train_number' => $_POST['trip_id'], 'status' => 'INJECTED OVERRIDE']);
        $redis->publish('transit-updates', $alert_payload);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Transit Dispatcher Control Room</title>
    <style>
        body { background: #061325; color: white; font-family: sans-serif; padding: 40px; }
        .box { background: #12243a; padding: 25px; border-radius: 8px; border: 1px solid #1e293b; max-width: 500px; }
        input, select, button { width: 100%; padding: 10px; margin: 10px 0; border-radius: 4px; border: 1px solid #1e3552; background: #061325; color: white; box-sizing: border-box; }
        button { background: #0284c7; font-weight: bold; cursor: pointer; border: none; }
        .danger-btn { background: #ef4444; }
    </style>
</head>
<body>
    <h1>Transit Dispatcher Control Room</h1>
    <div class="box">
        <h3>Feature 4: Live Terminal Vector Injection</h3>
        <form method="POST">
            <input type="text" name="trip_id" placeholder="Trip ID (e.g., 865729)" required>
            <select name="line">
                <option value="Q">Q Line</option>
                <option value="A">A Line</option>
                <option value="R">R Line</option>
                <option value="4">4 Line</option>
            </select>
            <input type="text" name="origin" placeholder="Origin Station" required>
            <input type="text" name="destination" placeholder="Destination Terminal" required>
            <button type="submit">Broadcast System Disruption / Injection</button>
        </form>
        
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="clear_override" value="1">
            <button type="submit" class="danger-btn">Release Override back to Python Stream</button>
        </form>
    </div>
</body>
</html>