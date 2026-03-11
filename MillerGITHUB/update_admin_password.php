<?php
// update_admin_password.php - Обновление пароля администратора в БД
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_config.php';

// Ваш новый пароль (тот же, что в файле users.php)
$newPassword = 'C-O2kpchop'; // ← Если хотите другой, впишите здесь

try {
    $pdo = getDB();
    
    // Хешируем пароль
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Обновляем пароль администратора в БД
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hashedPassword]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Пароль администратора успешно обновлён в БД!<br>";
        echo "🔐 Новый пароль: <strong>" . htmlspecialchars($newPassword) . "</strong><br>";
        echo "🔒 Хеш в БД: " . $hashedPassword . "<br><br>";
        
        // Проверяем, что пароль работает
        echo "🔍 Проверка:<br>";
        $checkStmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
        $checkStmt->execute();
        $hashFromDB = $checkStmt->fetchColumn();
        
        if (password_verify($newPassword, $hashFromDB)) {
            echo "✅ Пароль корректно захэширован и работает!<br>";
            echo "Теперь вы можете войти с паролем: <strong>" . htmlspecialchars($newPassword) . "</strong>";
        } else {
            echo "❌ Ошибка проверки пароля!";
        }
        
    } else {
        echo "❌ Пользователь 'admin' не найден в БД. Создаём...<br>";
        
        // Если админа нет - создаём
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, role, email) 
            VALUES ('admin', ?, 'admin', 'admin@example.com')
        ");
        $stmt->execute([$hashedPassword]);
        
        echo "✅ Администратор создан с паролем: " . htmlspecialchars($newPassword);
    }
    
    // Показываем всех пользователей для проверки
    echo "<h3>📋 Текущие пользователи в БД:</h3>";
    $users = $pdo->query("SELECT id, username, role, email FROM users")->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Логин</th><th>Роль</th><th>Email</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage();
}
?>