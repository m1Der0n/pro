<?php
// get_player_ratings.php - ДЛЯ PHP 5.6
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Method not allowed');
}

$pdo = getDB();

try {
    $stmt = $pdo->query("
        SELECT 
            player_name,
            total_games,
            total_wins,
            total_win_bonus,
            total_don_bonus,
            total_sheriff_bonus,
            total_doctor_bonus,
            total_kill_bonus,
            total_accusation_points,
            total_vote_points,
            total_best_move_bonus,
            total_rating,
            last_update
        FROM player_ratings 
        ORDER BY total_rating DESC
    ");
    
    $ratings = $stmt->fetchAll();
    
    // Преобразуем в формат, ожидаемый приложением
    $result = array();
    foreach ($ratings as $row) {
        $result[] = array(
            'player_name' => $row['player_name'],
            'total_games' => (int)$row['total_games'],
            'total_wins' => (int)$row['total_wins'],
            'total_win_bonus' => (float)$row['total_win_bonus'],
            'total_don_bonus' => (float)$row['total_don_bonus'],
            'total_sheriff_bonus' => (float)$row['total_sheriff_bonus'],
            'total_doctor_bonus' => (float)$row['total_doctor_bonus'],
            'total_kill_bonus' => (float)$row['total_kill_bonus'],
            'total_accusation_points' => (float)$row['total_accusation_points'],
            'total_vote_points' => (float)$row['total_vote_points'],
            'total_best_move_bonus' => (float)$row['total_best_move_bonus'],
            'total_rating' => (float)$row['total_rating'],
            'last_update' => (int)$row['last_update']
        );
    }
    
    sendResponse(true, $result);
    
} catch (Exception $e) {
    sendResponse(false, null, 'Server error: ' . $e->getMessage());
}
?>