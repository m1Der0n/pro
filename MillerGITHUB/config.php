<?php
// Настройки подключения к БД на Beget
$host = 'localhost';
$dbname = 's953066i_mafia';
$username = 's953066i_mafia';
$password = 'Y4we9WkisOR%';

// Подключение к базе
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die(json_encode(['error' => 'Ошибка подключения к БД: ' . $e->getMessage()]));
}
?>