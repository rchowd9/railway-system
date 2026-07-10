<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Set infinity read timeout so PHP doesn't drop the Redis subscription loop early
ini_set('default_socket_timeout', -1);

$redis = new Redis();
try {
    $redis->connect('127.0.0.1', 6379, 0, null, 0, 0, ['protocol' => 2]);
    $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
    
    // Subscribe directly to the Go messaging pipe
    $redis->subscribe(['transit-updates'], function($instance, $channel, $message) {
        echo "data: " . $message . "\n\n";
        ob_flush();
        flush();
    });
} catch (Exception $e) {
    echo "data: {\"error\": \"Alert broadcast wire disconnected\"}\n\n";
    ob_flush();
    flush();
}
?>