<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); 

$redis = new Redis();
try {
    $redis->connect('127.0.0.1', 6379, 1.5, null, 0, 0, ['protocol' => 2]);
} catch (Exception $e) {
    echo "data: " . json_encode([]) . "\n\n";
    exit;
}

$last_payload_hash = '';

while (true) {
    // Check if the connection has been dropped by the browser client
    if (connection_aborted()) {
        break;
    }

    $schedule = $redis->get('mta-live-schedule');
    $server_ts = time();
    $sequence = null;

    // Generate hash to detect changes
    $current_hash = md5($schedule . $server_ts);

    // Only broadcast frame sequences when changes occur
    if ($current_hash !== $last_payload_hash) {
        try {
            $sequence = $redis->incr('mta-live-sequence');
        } catch (Exception $seqErr) {
            $sequence = null;
        }

        $trains = [];
        if ($schedule) {
            $trains = json_decode($schedule, true);
            if (!is_array($trains)) { $trains = []; }
        }

        $payload = json_encode(['server_ts' => $server_ts, 'sequence' => $sequence, 'trains' => $trains]);
        echo "data: " . $payload . "\n\n";
        
        $last_payload_hash = $current_hash;
    }

    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    sleep(1);
}