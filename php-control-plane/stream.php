<?php
// Set specific headers required for real-time Server-Sent Events (SSE)
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); 

$redis = new Redis();
try {
    // Connect using your local development settings matching mta_bridge.py
    $host = '127.0.0.1';
    $port = 6379;

    $redis->connect($host, (int)$port, 1.5, null, 0, 0, ['protocol' => 2]);
} catch (Exception $e) {
    echo "data: " . json_encode([]) . "\n\n";
    exit;
}

// Keep the event loop alive to stream live updates out to timetable.php
for ($i = 0; $i < 15; $i++) {
    // Pull the exact string key written by your mta_bridge.py script
    $data = $redis->get('mta-live-schedule'); 
    
    if ($data) {
        echo "data: " . $data . "\n\n";
    } else {
        echo "data: " . json_encode([]) . "\n\n"; 
    }
    
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
    
    sleep(2); 
}
?>