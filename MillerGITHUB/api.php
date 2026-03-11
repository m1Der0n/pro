<?php
// api.php - отключаем вывод ошибок
error_reporting(0);
ini_set('display_errors', 0);

// Устанавливаем правильную кодировку для MySQL
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once 'db_config.php';

try {
    $pdo = getDB();
    
    // Устанавливаем кодировку соединения
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    
    // Получаем общее количество сыгранных игр (НЕ игроков!)
    $gamesStmt = $pdo->query("SELECT COUNT(*) as total FROM game_history");
    $totalGames = $gamesStmt->fetch()['total'];
    
    // Получаем всех игроков
    $query = "SELECT 
    id,
    player_name,
    COALESCE(total_rating, 0) as total_rating,
    COALESCE(total_games, 0) as total_games,
    COALESCE(total_wins, 0) as total_wins,
    COALESCE(total_wins_mafia, 0) as total_wins_mafia,
    COALESCE(total_wins_civilian, 0) as total_wins_civilian,
    COALESCE(total_win_bonus, 0) as total_win_bonus,
    COALESCE(total_don_bonus, 0) as total_don_bonus,
    COALESCE(total_sheriff_bonus, 0) as total_sheriff_bonus,
    COALESCE(total_doctor_bonus, 0) as total_doctor_bonus,
    COALESCE(total_kill_bonus, 0) as total_kill_bonus,
    COALESCE(total_accusation_points, 0) as total_accusation_points,
    COALESCE(total_vote_points, 0) as total_vote_points,
    COALESCE(total_best_move_bonus, 0) as total_best_move_bonus,
    COALESCE(last_update, 0) as last_update
  FROM player_ratings 
  ORDER BY total_rating DESC, id ASC";
    
    $stmt = $pdo->query($query);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = array();
    foreach ($players as $row) {
        // Преобразуем имя в правильную кодировку
        $name = $row['player_name'];
        
        // Убеждаемся что имя в UTF-8
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = utf8_encode($name);
        }
        
        $result[] = array(
            'id' => (int)$row['id'],
            'name' => $name,
            'avatar' => mb_substr($name, 0, 2, 'UTF-8'),
            'score' => (float)$row['total_rating'],
            'total_games' => (int)$row['total_games'],
            'total_wins' => (int)$row['total_wins'],
            'total_wins_mafia' => (int)$row['total_wins_mafia'],
            'total_wins_civilian' => (int)$row['total_wins_civilian'],
            'total_win_bonus' => (float)$row['total_win_bonus'],
            'total_don_bonus' => (float)$row['total_don_bonus'],
            'total_sheriff_bonus' => (float)$row['total_sheriff_bonus'],
            'total_doctor_bonus' => (float)$row['total_doctor_bonus'],
            'total_kill_bonus' => (float)$row['total_kill_bonus'],
            'total_accusation_points' => (float)$row['total_accusation_points'],
            'total_vote_points' => (float)$row['total_vote_points'],
            'total_best_move_bonus' => (float)$row['total_best_move_bonus'],
            'last_update' => (int)$row['last_update'],
            'win_rate' => $row['total_games'] > 0 
                ? round(($row['total_wins'] / $row['total_games']) * 100) 
                : 0,
            'total_games_count' => (int)$totalGames // Добавляем общее количество игр
        );
    }
    
    // Добавляем цвета для аватарок
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', 
        '#FFEEAD', '#D4A5A5', '#9B59B6', '#3498DB',
        '#E67E22', '#2ECC71', '#E74C3C', '#1ABC9C'
    ];
    
    foreach ($result as &$player) {
        $colorIndex = abs(crc32($player['name'])) % count($colors);
        $player['avatar_color'] = $colors[$colorIndex];
    }
    
    // Очищаем буфер
    if (ob_get_length()) ob_clean();
    
    // Отправляем JSON
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch(PDOException $e) {
    http_response_code(500);
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch(Exception $e) {
    http_response_code(500);
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'Внутренняя ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>