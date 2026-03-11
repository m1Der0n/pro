<?php
// check_sync_status.php
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Method not allowed');
}

if (!isset($_GET['game_id'])) {
    sendResponse(false, null, 'game_id is required');
}

$gameId = (int)$_GET['game_id'];

$pdo = getDB();

try {
    $stmt = $pdo->prepare("SELECT synced, status, sync_date FROM sync_status WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $status = $stmt->fetch();
    
    if ($status) {
        sendResponse(true, [
            'synced' => (bool)$status['synced'],
            'status' => $status['status'],
            'date' => $status['sync_date']
        ]);
    } else {
        sendResponse(true, [
            'synced' => false,
            'status' => 'not_found',
            'date' => null
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(false, null, 'Server error: ' . $e->getMessage());
}
?>