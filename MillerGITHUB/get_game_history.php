<?php
// get_game_history.php - ИСПРАВЛЕННАЯ ВЕРСИЯ
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Method not allowed');
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, (int)$_GET['limit']) : 50;
$offset = ($page - 1) * $limit;

$pdo = getDB();

try {
    // Получаем историю игр с пагинацией - ИСКЛЮЧАЕМ ABORTED_CIVILIANS
    // Но так как в таблице game_history нет поля status, используем другой подход
    
    // Сначала получаем все игры
    $stmt = $pdo->prepare("SELECT * FROM game_history ORDER BY game_date DESC LIMIT :offset, :limit");
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $games = $stmt->fetchAll();
    
    $result = array();
    foreach ($games as $game) {
        // Получаем игроков для каждой игры
        $stmtPlayers = $pdo->prepare("
            SELECT 
                player_name, role,
                win_bonus, don_bonus, sheriff_bonus, doctor_bonus, kill_bonus,
                accusation_points, vote_points, best_move_bonus, total_points
            FROM game_players 
            WHERE game_history_id = ?
        ");
        $stmtPlayers->execute(array($game['id']));
        $players = $stmtPlayers->fetchAll();
        
        $playersArray = array();
        foreach ($players as $p) {
            $playersArray[] = array(
                'player_name' => $p['player_name'],
                'role' => $p['role'],
                'win_bonus' => (float)$p['win_bonus'],
                'don_bonus' => (float)$p['don_bonus'],
                'sheriff_bonus' => (float)$p['sheriff_bonus'],
                'doctor_bonus' => (float)$p['doctor_bonus'],
                'kill_bonus' => (float)$p['kill_bonus'],
                'accusation_points' => (float)$p['accusation_points'],
                'vote_points' => (float)$p['vote_points'],
                'best_move_bonus' => (float)$p['best_move_bonus'],
                'total_points' => (float)$p['total_points']
            );
        }
        
        $result[] = array(
            'id' => (int)$game['id'],
            'game_id' => (int)$game['game_id'],
            'game_date' => (int)$game['game_date'],
            'winner_team' => $game['winner_team'],
            'players' => $playersArray
        );
    }
    
    sendResponse(true, $result);
    
} catch (Exception $e) {
    sendResponse(false, null, 'Server error: ' . $e->getMessage());
}
?>