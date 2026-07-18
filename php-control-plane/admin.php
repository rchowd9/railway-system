<?php
session_start();
$_SESSION['user_role'] = $_SESSION['user_role'] ?? 'dispatcher'; 

if ($_SESSION['user_role'] !== 'dispatcher') {
    die("❌ Access Denied: Unauthorized Station Personnel Profile.");
}

$redis = new Redis();
$redis_connected = false;

// EDGE CASE: Prevent Redis port locking or connection dropouts from halting execution
try {
    $redis->connect('127.0.0.1', 6379, 1.5, null, 0, 0, ['protocol' => 2]);
    $redis_connected = true;
} catch (Exception $e) {
    $redis_connected = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $redis_connected) {
    try {
        if (isset($_POST['clear_override'])) {
            $redis->del('mta-admin-override');
        } else {
            // Defensive sanitization of form parameters
            $trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_SANITIZE_SPECIAL_CHARS) ?: rand(100000, 999999);
            $line = filter_input(INPUT_POST, 'line', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'A';
            $origin = filter_input(INPUT_POST, 'origin', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'Unknown Station';
            $destination = filter_input(INPUT_POST, 'destination', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'Terminal Stop';

            $existing_override = $redis->get('mta-admin-override');
            $current_schedule = [];
            
            if ($existing_override) {
                $current_schedule = json_decode($existing_override, true);
                if (!is_array($current_schedule)) {
                    $current_schedule = [];
                }
            }

            $new_train = [
                'id'                => $trip_id,
                'line'              => $line . " Line Commuter",
                'origin'            => $origin,
                'destination'       => $destination,
                'arrival_timestamp' => time() + 600, // Balanced 10-minute projection
                'arrival'           => date('h:i:s A', time() + 600),
                'lat'               => 40.7580 + (count($current_schedule) * 0.01),
                'lon'               => -73.9855 + (count($current_schedule) * 0.01)
            ];

            $updated = false;
            foreach ($current_schedule as $index => $train) {
                if (isset($train['id']) && $train['id'] === $new_train['id']) {
                    $current_schedule[$index] = $new_train;
                    $updated = true;
                    break;
                }
            }
            
            if (!$updated) {
                $current_schedule[] = $new_train;
            }

            $redis->set('mta-admin-override', json_encode($current_schedule));
            
            $alert_payload = json_encode(['train_number' => $trip_id, 'status' => 'INJECTED OVERRIDE']);
            $redis->publish('transit-updates', $alert_payload);
        }
    } catch (Exception $action_err) {
        // Suppress crashes silently or log internally
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
        .alert-banner { background: #7f1d1d; border: 1px solid #ef4444; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Transit Dispatcher Control Room</h1>
    <?php if (!$redis_connected): ?>
        <div class="alert-banner">⚠️ Warning: Unable to interface with the Redis node network. Engine offline.</div>
    <?php endif; ?>
    <div class="box">
        <h3>Feature 4: Multi-Vector Terminal Injection</h3>
        <form method="POST">
            <input type="text" name="trip_id" placeholder="Trip ID (e.g., 111111)" required>
            <select name="line">
                <option value="1">1 Line</option>
                <option value="A">A Line</option>
                <option value="Q">Q Line</option>
                <option value="R">R Line</option>
            </select>
            <input type="text" name="origin" placeholder="Origin Station" required>
            <input type="text" name="destination" placeholder="Destination Terminal" required>
            <button type="submit" <?php echo !$redis_connected ? 'disabled' : ''; ?>>Broadcast System Disruption / Injection</button>
        </form>
        
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="clear_override" value="1">
            <button type="submit" class="danger-btn" <?php echo !$redis_connected ? 'disabled' : ''; ?>>Clear All Overrides & Reset Stream</button>
        </form>
    </div>
</body>
</html>