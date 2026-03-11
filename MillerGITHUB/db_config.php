<?php
// db_config.php
error_reporting(0);
ini_set('display_errors', 0);

// Настройки подключения к БД
define('DB_HOST', 'localhost');
define('DB_USER', 's953066i_mafia');
define('DB_PASS', 'Y4we9WkisOR%');
define('DB_NAME', 's953066i_mafia');

function getDB() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'error' => 'Database connection failed'));
        exit();
    }
}

function sendResponse($success, $data = null, $error = null) {
    echo json_encode(array(
        'success' => $success,
        'data' => $data,
        'error' => $error
    ), JSON_UNESCAPED_UNICODE);
    exit();
}
?>