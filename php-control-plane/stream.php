<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Prevents Nginx/Apache from buffering the live stream chunks

$redis = new Redis();
try {
    $redis->connect('127.0.0.1', 6379, 1.5, null, 0, 0, ['protocol' => 2]);
} catch (Exception $e) {
    echo "data: " . json_encode([]) . "\n\n";
    exit;
}

// Stream live schedule updates in a tight loop and include server timestamp
while (true) {
    $schedule = $redis->get('mta-live-schedule');
    $server_ts = time();
    if ($schedule) {
        $trains = json_decode($schedule, true);
        if (!is_array($trains)) { $trains = []; }
        $payload = json_encode(['server_ts' => $server_ts, 'trains' => $trains]);
        echo "data: " . $payload . "\n\n";
    } else {
        echo "data: " . json_encode(['server_ts' => $server_ts, 'trains' => []]) . "\n\n";
    }

    // Flush the output buffer out to the client browser layout
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    // Sleep 1 second before streaming the next chunk matrix
    sleep(1);
}