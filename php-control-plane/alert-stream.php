<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Extend execution timeout so the long-lived SSE channel wire doesn't drop
set_time_limit(0);

$redis = new Redis();
try {
    $redis->connect('127.0.0.1', 6379, 0, null, 0, 0, ['protocol' => 2]);
    $redis->setOption(Redis::OPT_READ_TIMEOUT, -1); // Keep socket pipe open infinitely
} catch (Exception $e) {
    exit;
}

// Subscribe to the Redis updates channel topology matrix
try {
    $redis->subscribe(['transit-updates'], function($instance, $channel, $message) {
        echo "data: " . $message . "\n\n";
        
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    });
} catch (Exception $e) {
    exit;
}