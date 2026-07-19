<?php
session_start();
$_SESSION['user_role'] = $_SESSION['user_role'] ?? 'dispatcher'; 

if ($_SESSION['user_role'] !== 'dispatcher') {
    die("❌ Access Denied: Unauthorized Station Personnel Profile.");
}

// Suppress deprecated browser-level Shared Storage tracking telemetry warnings
header("Permissions-Policy: shared-storage=(), shared-storage-select-url=()");

$redis = new Redis();
$redis_connected = false;

try {
    $redis->connect('127.0.0.1', 6379, 1.5, null, 0, 0, ['protocol' => 2]);
    $redis_connected = true;
} catch (Exception $e) {
    $redis_connected = false;
}

// Handle Form submissions for addition, individual removal, and global resets
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $redis_connected) {
    try {
        if (isset($_POST['clear_override'])) {
            $redis->delete('mta-admin-override');
        } 
        // FEATURE GAP FIX: Handle targeted removal of an individual train override
        elseif (isset($_POST['delete_trip_id'])) {
            $target_id = $_POST['delete_trip_id'];
            $existing_override = $redis->get('mta-admin-override');
            if ($existing_override) {
                $current_schedule = json_decode($existing_override, true);
                if (is_array($current_schedule)) {
                    // Filter out the specific trip ID matching the target
                    $filtered_schedule = array_filter($current_schedule, function($train) use ($target_id) {
                        return isset($train['id']) && (string)$train['id'] !== (string)$target_id;
                    });
                    // Re-index array keys cleanly before encoding
                    $filtered_schedule = array_values($filtered_schedule);
                    
                    if (empty($filtered_schedule)) {
                        $redis->delete('mta-admin-override');
                    } else {
                        $redis->set('mta-admin-override', json_encode($filtered_schedule));
                    }
                    
                    $alert_payload = json_encode(['train_number' => $target_id, 'status' => 'REMOVED BY DISPATCHER']);
                    $redis->publish('transit-updates', $alert_payload);
                }
            }
        } 
        // Handle adding / modifying overrides
        elseif (isset($_POST['trip_id'])) {
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

            $arrival_ts = time() + 600;
            $turnaround_dwell = rand(6, 11) * 60; 
            $departure_ts = $arrival_ts + $turnaround_dwell;

            $new_train = [
                'id'                  => $trip_id,
                'line'                => $line . " Line Commuter",
                'origin'              => $origin,
                'destination'         => $destination,
                'arrival_timestamp'   => $arrival_ts,
                'departure_timestamp' => $departure_ts,
                'arrival'             => date('h:i:s A', $arrival_ts),
                'departure'           => date('h:i:s A', $departure_ts),
                'lat'                 => 40.7580 + (count($current_schedule) * 0.01),
                'lon'                 => -73.9855 + (count($current_schedule) * 0.01)
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
        // Suppress or catch gracefully to preserve dashboard uptime
    }
}

// Retrieve active overrides to populate management panel table grid
$active_overrides = [];
if ($redis_connected) {
    $raw_override = $redis->get('mta-admin-override');
    if ($raw_override) {
        $active_overrides = json_decode($raw_override, true) ?: [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transit Dispatcher Control Room</title>
    <style>
        body { background: #061325; color: white; font-family: sans-serif; padding: 40px; margin: 0; }
        .dashboard-container { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; max-width: 1300px; margin: 0 auto; }
        .box { background: #12243a; padding: 25px; border-radius: 8px; border: 1px solid #1e293b; height: fit-content; }
        h1 { max-width: 1300px; margin: 0 auto 25px auto; color: #38bdf8; }
        h3 { margin-top: 0; color: #94a3b8; border-bottom: 1px solid #1e3552; padding-bottom: 10px; }
        input, select, button { width: 100%; padding: 10px; margin: 10px 0; border-radius: 4px; border: 1px solid #1e3552; background: #061325; color: white; box-sizing: border-box; }
        button { background: #0284c7; font-weight: bold; cursor: pointer; border: none; transition: background 0.2s; }
        button:hover { background: #0369a1; }
        .danger-btn { background: #ef4444; }
        .danger-btn:hover { background: #dc2626; }
        .inline-delete-btn { background: #b91c1c; padding: 4px 8px; font-size: 11px; margin: 0; width: auto; border-radius: 3px; }
        .inline-delete-btn:hover { background: #991b1b; }
        .alert-banner { background: #7f1d1d; border: 1px solid #ef4444; padding: 10px; border-radius: 4px; margin-bottom: 15px; grid-column: span 2; }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 10px; }
        th { background: #1e3552; color: #94a3b8; font-size: 12px; text-transform: uppercase; padding: 12px; font-weight: bold; }
        td { padding: 12px; border-bottom: 1px solid #1e293b; font-size: 14px; }
        .badge { background: #0284c7; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Transit Dispatcher Control Room</h1>
    
    <div class="dashboard-container">
        <?php if (!$redis_connected): ?>
            <div class="alert-banner">⚠️ Warning: Unable to interface with the Redis node network. Engine offline.</div>
        <?php endif; ?>

        <!-- Left Column: Input Configurations -->
        <div class="box">
            <h3>Feature 4: Multi-Vector Terminal Injection</h3>
            <form method="POST">
                <input type="text" name="trip_id" placeholder="Trip ID (e.g., 111111)" required autocomplete="off">
                <select name="line">
                    <option value="1">1 Line</option>
                    <option value="A">A Line</option>
                    <option value="Q">Q Line</option>
                    <option value="R">R Line</option>
                </select>
                <input type="text" name="origin" placeholder="Origin Station" required autocomplete="off">
                <input type="text" name="destination" placeholder="Destination Terminal" required autocomplete="off">
                <button type="submit" <?php echo !$redis_connected ? 'disabled' : ''; ?>>Broadcast System Disruption / Injection</button>
            </form>
            
            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="clear_override" value="1">
                <button type="submit" class="danger-btn" <?php echo !$redis_connected ? 'disabled' : ''; ?>>Clear All Overrides & Reset Stream</button>
            </form>
        </div>

        <!-- Right Column: Live Data Override Matrix Control Management Table -->
        <div class="box">
            <h3>Active System Overrides Control Matrix</h3>
            <?php if (empty($active_overrides)): ?>
                <p style="color: var(--text-muted); font-size: 14px; text-align: center; padding: 40px 0;">No manual system overrides are currently injected into the schedule matrix.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Trip ID</th>
                            <th>Line Profile</th>
                            <th>Vector Profile</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_overrides as $train): ?>
                            <tr>
                                <td><span class="badge">MTA-<?php echo htmlspecialchars($train['id'] ?? 'UNK'); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($train['line'] ?? 'Unknown'); ?></strong></td>
                                <td><?php echo htmlspecialchars($train['origin'] ?? 'TBD'); ?> ➔ <?php echo htmlspecialchars($train['destination'] ?? 'TBD'); ?></td>
                                <td>
                                    <!-- Individual Removal Context -->
                                    <form method="POST" style="margin: 0; display: inline;">
                                        <input type="hidden" name="delete_trip_id" value="<?php echo htmlspecialchars($train['id']); ?>">
                                        <button type="submit" class="inline-delete-btn">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>