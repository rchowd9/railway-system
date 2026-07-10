<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Prevents server proxy buffering from stalling the pub/sub feed

// Set infinite read timeout so PHP doesn't drop the Redis subscription loop early
ini_set('default_socket_timeout', -1);

$redis = new Redis();
try {
    // Dynamically fetch matrix parameters
    $host = getenv('REDISHOST') ?: '127.0.0.1';
    $port = getenv('REDISPORT') ?: 6379;
    $password = getenv('REDISPASSWORD') ?: null;

    // Connect using the RESP2 protocol array option matching your terminal configuration
    $redis->connect($host, (int)$port, 0, null, 0, 0, ['protocol' => 2]);
    if ($password) {
        $redis->auth($password);
    }
    
    $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
    
    // Subscribe directly to the Redis messaging pipe
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