<?php
// check_json.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Проверка JSON вывода</h2>";

try {
    $pdo = getDB();
    $pdo->exec("SET NAMES utf8mb4");
    
    $query = "SELECT player_name, total_rating FROM player_ratings LIMIT 3";
    $stmt = $pdo->query($query);
    $players = $stmt->fetchAll();
    
    echo "<h3>Данные из БД:</h3>";
    echo "<pre>";
    print_r($players);
    echo "</pre>";
    
    echo "<h3>JSON вывод:</h3>";
    echo "<pre>";
    $json = json_encode($players, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    echo $json;
    echo "</pre>";
    
    // Проверяем валидность JSON
    $test = json_decode($json);
    if ($test === null && json_last_error() !== JSON_ERROR_NONE) {
        echo "<p style='color:red'>Ошибка JSON: " . json_last_error_msg() . "</p>";
    } else {
        echo "<p style='color:green'>✓ JSON валидный</p>";
    }
    
} catch(Exception $e) {
    echo "<p style='color:red'>Ошибка: " . $e->getMessage() . "</p>";
}
?>