<?php
// sync_game_get.php - Принимает данные через GET параметры
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Логируем запрос
$logFile = 'get_sync_log.txt';
$logData = date('Y-m-d H:i:s') . " - GET Request from: " . $_SERVER['REMOTE_ADDR'] . "\n";
$logData .= "GET params: " . print_r($_GET, true) . "\n";
file_put_contents($logFile, $logData, FILE_APPEND);

// Проверяем наличие данных
if (!isset($_GET['data'])) {
    echo json_encode(['success' => false, 'error' => 'No data parameter']);
    exit();
}

// Декодируем данные из base64
$jsonData = base64_decode($_GET['data']);
if (!$jsonData) {
    echo json_encode(['success' => false, 'error' => 'Invalid base64 data']);
    exit();
}

$input = json_decode($jsonData, true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

// *** ПРОВЕРЯЕМ ТОЛЬКО MAFIA ИЛИ МИРНЫЕ ***
$winnerTeam = isset($input['winnerTeam']) ? $input['winnerTeam'] : '';

// Логируем
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Game ID: " . $input['gameId'] . ", Winner: " . $winnerTeam . "\n", FILE_APPEND);

// Если нет победителя или это не MAFIA и не мирные - пропускаем
if (empty($winnerTeam) || ($winnerTeam !== 'MAFIA' && $winnerTeam !== 'PEACEFUL' && $winnerTeam !== 'CIVILIANS')) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Game SKIPPED (invalid winner): " . $input['gameId'] . ", Winner: " . $winnerTeam . "\n", FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'message' => 'Game skipped - invalid winner team',
            'gameId' => $input['gameId'],
            'winnerTeam' => $winnerTeam
        ],
        'error' => null
    ]);
    exit();
}

require_once 'db_config.php';

try {
    $pdo = getDB();
    $pdo->beginTransaction();
    
    // Проверка на дубликат
    $stmt = $pdo->prepare("SELECT id FROM sync_status WHERE game_id = ?");
    $stmt->execute(array($input['gameId']));
    if ($stmt->fetch()) {
        throw new Exception('Game already synced');
    }
    
    // Сохраняем игру
    $stmt = $pdo->prepare("
        INSERT INTO game_history (game_id, game_date, winner_team) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute(array(
        $input['gameId'],
        $input['timestamp'],
        $winnerTeam
    ));
    
    $gameHistoryId = $pdo->lastInsertId();
    
    // Сохраняем игроков
    $playerCount = 0;
    foreach ($input['players'] as $player) {
        $stmt = $pdo->prepare("
            INSERT INTO game_players (
                game_history_id, player_name, role, 
                win_bonus, don_bonus, sheriff_bonus, doctor_bonus, kill_bonus,
                accusation_points, vote_points, best_move_bonus, total_points
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute(array(
            $gameHistoryId,
            $player['playerName'],
            $player['role'],
            isset($player['winBonus']) ? (float)$player['winBonus'] : 0,
            isset($player['donBonus']) ? (float)$player['donBonus'] : 0,
            isset($player['sheriffBonus']) ? (float)$player['sheriffBonus'] : 0,
            isset($player['doctorBonus']) ? (float)$player['doctorBonus'] : 0,
            isset($player['killBonus']) ? (float)$player['killBonus'] : 0,
            isset($player['accusationPoints']) ? (float)$player['accusationPoints'] : 0,
            isset($player['votePoints']) ? (float)$player['votePoints'] : 0,
            isset($player['bestMoveBonus']) ? (float)$player['bestMoveBonus'] : 0,
            (float)$player['totalPoints']
        ));
        
        // Определяем победу
        $winnerTeamForPlayer = null; // null – не победил, 'mafia' – победил за мафию, 'civilian' – за мирных

if ($winnerTeam === 'MAFIA' && (strpos($player['role'], 'Дон') !== false || strpos($player['role'], 'Мафия') !== false)) {
    $isWinner = true;
    $winnerTeamForPlayer = 'mafia';
} elseif (($winnerTeam === 'PEACEFUL' || $winnerTeam === 'CIVILIANS') && 
          (strpos($player['role'], 'Шериф') !== false || 
           strpos($player['role'], 'Доктор') !== false || 
           strpos($player['role'], 'Мирный') !== false)) {
    $isWinner = true;
    $winnerTeamForPlayer = 'civilian';
}
        
        // Обновляем рейтинг
        $stmt = $pdo->prepare("SELECT * FROM player_ratings WHERE player_name = ?");
        $stmt->execute(array($player['playerName']));
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $pdo->prepare("
    UPDATE player_ratings SET
        total_games = total_games + 1,
        total_wins = total_wins + ?,
        total_wins_mafia = total_wins_mafia + ?,
        total_wins_civilian = total_wins_civilian + ?,
        total_win_bonus = total_win_bonus + ?,
        total_don_bonus = total_don_bonus + ?,
        total_sheriff_bonus = total_sheriff_bonus + ?,
        total_doctor_bonus = total_doctor_bonus + ?,
        total_kill_bonus = total_kill_bonus + ?,
        total_accusation_points = total_accusation_points + ?,
        total_vote_points = total_vote_points + ?,
        total_best_move_bonus = total_best_move_bonus + ?,
        total_rating = total_rating + ?,
        last_update = ?
    WHERE player_name = ?
");

$stmt->execute(array(
    $isWinner ? 1 : 0,                                    // total_wins
    ($winnerTeamForPlayer === 'mafia') ? 1 : 0,           // total_wins_mafia
    ($winnerTeamForPlayer === 'civilian') ? 1 : 0,        // total_wins_civilian
    (float)$player['winBonus'],
    (float)$player['donBonus'],
    (float)$player['sheriffBonus'],
    (float)$player['doctorBonus'],
    (float)$player['killBonus'],
    (float)$player['accusationPoints'],
    (float)$player['votePoints'],
    (float)$player['bestMoveBonus'],
    (float)$player['totalPoints'],
    time(),
    $player['playerName']
));
        } else {
            $stmt = $pdo->prepare("
    INSERT INTO player_ratings (
        player_name, total_games, total_wins, total_wins_mafia, total_wins_civilian,
        total_win_bonus, total_don_bonus, total_sheriff_bonus, 
        total_doctor_bonus, total_kill_bonus, 
        total_accusation_points, total_vote_points, 
        total_best_move_bonus, total_rating, last_update
    ) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute(array(
    $player['playerName'],
    $isWinner ? 1 : 0,
    ($winnerTeamForPlayer === 'mafia') ? 1 : 0,
    ($winnerTeamForPlayer === 'civilian') ? 1 : 0,
    (float)$player['winBonus'],
    (float)$player['donBonus'],
    (float)$player['sheriffBonus'],
    (float)$player['doctorBonus'],
    (float)$player['killBonus'],
    (float)$player['accusationPoints'],
    (float)$player['votePoints'],
    (float)$player['bestMoveBonus'],
    (float)$player['totalPoints'],
    time()
));
        }
        
        $playerCount++;
    }
    
    // Отмечаем синхронизацию
    $stmt = $pdo->prepare("INSERT INTO sync_status (game_id, synced, status) VALUES (?, TRUE, 'completed')");
    $stmt->execute(array($input['gameId']));
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'message' => 'Game synced successfully',
            'gameHistoryId' => $gameHistoryId,
            'playersProcessed' => $playerCount
        ],
        'error' => null
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => $e->getMessage()
    ]);
}
?>