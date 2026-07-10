<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trainNumber = $_POST['train_number'] ?? '';
    $status = $_POST['status'] ?? '';

    if (!empty($trainNumber) && !empty($status)) {
        $redis = new Redis();
        try {
            $redis->connect('127.0.0.1', 6379, 1.0, null, 0, 0, ['protocol' => 2]);
            
            $payload = json_encode([
                'train_number' => $trainNumber,
                'status' => $status
            ]);
            
            // Publish directly into the Redis asynchronous cluster channel
            $redis->publish('transit-updates', $payload);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}
echo json_encode(['success' => false, 'error' => 'Invalid Request Structure']);