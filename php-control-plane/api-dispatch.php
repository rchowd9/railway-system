<?php
require_once 'auth.php';
verify_session_clearance();

$redis = new Redis();
$redis->connect('127.0.0.1', 6379, 2.5, null, 0, 0, ['protocol' => 2]); // Safe protocol mapping

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trainNumber = $_POST['train_id'] ?? ''; 
    $status = $_POST['status'] ?? 'On Time';

    // FIX: Keys are now lowercase to match Go's json tags exactly
    $payload = json_encode([
        'train_number' => $trainNumber,
        'status'       => $status
    ]);

    $redis->publish('transit-updates', $payload);
    header("Location: admin.php?success=1");
    exit();
}
?>