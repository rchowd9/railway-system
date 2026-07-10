<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$redis = new Redis();
try {
    $redis->connect('127.0.0.1', 6379, 0, null, 0, 0, ['protocol' => 2]);
    
    $last_payload = '';
    while (true) {
        // Clean exit if user closes the browser tab
        if (connection_aborted()) {
            break;
        }
        
        $current_payload = $redis->get('mta-live-schedule');
        if ($current_payload && $current_payload !== $last_payload) {
            echo "data: " . $current_payload . "\n\n";
            ob_flush();
            flush();
            $last_payload = $current_payload;
        }
        usleep(500000); // 500ms heartbeat
    }
} catch (Exception $e) {
    echo "data: {\"error\": \"Schedule stream disconnected\"}\n\n";
    ob_flush();
    flush();
}
?>