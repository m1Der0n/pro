<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Диагностика авторизации</h2>";

// Проверка 1: Подключение к БД
echo "<h3>1. Проверка подключения к БД:</h3>";
try {
    require_once 'db_config.php';
    $pdo = getDB();
    echo "✅ Подключение к БД успешно<br>";
} catch (Exception $e) {
    echo "❌ Ошибка БД: " . $e->getMessage() . "<br>";
}

// Проверка 2: Проверка users.php
echo "<h3>2. Проверка users.php:</h3>";
try {
    require_once 'users.php';
    $userManager = new UserManager();
    echo "✅ users.php загружен успешно<br>";
} catch (Exception $e) {
    echo "❌ Ошибка users.php: " . $e->getMessage() . "<br>";
}

// Проверка 3: Проверка сессий
echo "<h3>3. Проверка сессий:</h3>";
session_start();
$_SESSION['test'] = 'working';
echo "✅ Session ID: " . session_id() . "<br>";
echo "✅ Session data: " . print_r($_SESSION, true) . "<br>";

// Проверка 4: Прямой тест API
echo "<h3>4. Тест API:</h3>";
try {
    $result = [
        'success' => true,
        'message' => 'API работает',
        'time' => date('Y-m-d H:i:s')
    ];
    echo "✅ JSON, который должен возвращаться: <br>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "<br>";
}

// Проверка 5: Проверка таблицы users
echo "<h3>5. Проверка таблицы users:</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Таблица users существует<br>";
        
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "👥 Всего пользователей: " . $count . "<br>";
        
        if ($count > 0) {
            $users = $pdo->query("SELECT id, username, role FROM users")->fetchAll();
            echo "📋 Список пользователей:<br>";
            echo "<ul>";
            foreach ($users as $user) {
                echo "<li>ID: {$user['id']}, Логин: {$user['username']}, Роль: {$user['role']}</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "❌ Таблица users не найдена<br>";
    }
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "<br>";
}

// Проверка 6: Проверка auth_api.php напрямую
echo "<h3>6. Тест auth_api.php?action=check_session:</h3>";
try {
    // Эмулируем запрос
    ob_start();
    $_GET['action'] = 'check_session';
    include 'auth_api.php';
    $output = ob_get_clean();
    
    echo "Ответ сервера:<br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Проверяем, является ли ответ JSON
    $json = json_decode($output, true);
    if ($json === null) {
        echo "❌ Ответ НЕ является JSON. Ошибка: " . json_last_error_msg() . "<br>";
    } else {
        echo "✅ Ответ является JSON<br>";
        echo "Содержимое: " . print_r($json, true) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "<br>";
}
?>