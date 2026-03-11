<?php
// users.php - Управление пользователями
require_once 'db_config.php';

class UserManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
        $this->initUsersTable();
    }
    
    // Создание таблицы пользователей, если её нет
    private function initUsersTable() {
        // Создаём таблицу, если её нет (с обновлённым ENUM)
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'guest', 'player') DEFAULT 'guest',
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->pdo->exec($sql);
        
        // Проверяем, есть ли в колонке role значение 'player'
        $stmt = $this->pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $column = $stmt->fetch();
        if ($column) {
            $type = $column['Type'];
            if (strpos($type, 'player') === false) {
                // Изменяем ENUM, добавляя 'player'
                $this->pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'guest', 'player') DEFAULT 'guest'");
            }
        }
        
        // Проверяем, есть ли админ, если нет – создаём
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $this->createAdmin();
        }
    }
    
    // Создание администратора
    private function createAdmin() {
        $hashedPassword = password_hash('C-O2kpchop', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, password, role, email) 
            VALUES ('admin', ?, 'admin', 'admin@example.com')
        ");
        $stmt->execute([$hashedPassword]);
    }
    
    // Проверка логина и пароля
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, password, role, is_active 
            FROM users 
            WHERE username = ? AND is_active = TRUE
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $this->updateLastLogin($username);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'is_admin' => ($user['role'] === 'admin')
                ]
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Неверный логин или пароль'
        ];
    }
    
    // Обновление времени последнего входа
    private function updateLastLogin($username) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET last_login = NOW() 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
    }
    
    // Загрузка пользователей из файла
    public function importUsersFromFile($filePath, $fileType) {
        $imported = 0;
        $errors = [];
        
        switch (strtolower($fileType)) {
            case 'csv':
                $result = $this->importFromCSV($filePath);
                $imported = $result['imported'];
                $errors = $result['errors'];
                break;
                
            case 'json':
                $result = $this->importFromJSON($filePath);
                $imported = $result['imported'];
                $errors = $result['errors'];
                break;
                
            case 'xls':
            case 'xlsx':
                $result = $this->importFromExcel($filePath);
                $imported = $result['imported'];
                $errors = $result['errors'];
                break;
                
            default:
                return [
                    'success' => false,
                    'error' => 'Неподдерживаемый формат файла'
                ];
        }
        
        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        ];
    }
    
    // Импорт из CSV
    private function importFromCSV($filePath) {
        $imported = 0;
        $errors = [];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $firstLine = fgets($handle);
            rewind($handle);
            
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
            
            $headers = fgetcsv($handle, 1000, $delimiter);
            $headers = array_map('trim', $headers);
            
            $usernameIdx = array_search('username', array_map('strtolower', $headers));
            $passwordIdx = array_search('password', array_map('strtolower', $headers));
            $roleIdx = array_search('role', array_map('strtolower', $headers));
            $emailIdx = array_search('email', array_map('strtolower', $headers));
            
            if ($usernameIdx === false || $passwordIdx === false) {
                fclose($handle);
                return ['imported' => 0, 'errors' => ['CSV должен содержать колонки username и password']];
            }
            
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                try {
                    $username = $data[$usernameIdx];
                    $password = password_hash($data[$passwordIdx], PASSWORD_DEFAULT);
                    $role = ($roleIdx !== false && isset($data[$roleIdx])) ? 
                            (in_array($data[$roleIdx], ['admin', 'guest']) ? $data[$roleIdx] : 'guest') : 
                            'guest';
                    $email = ($emailIdx !== false && isset($data[$emailIdx])) ? $data[$emailIdx] : null;
                    
                    $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    
                    if ($stmt->rowCount() > 0) {
                        $stmt = $this->pdo->prepare("
                            UPDATE users SET 
                                password = ?,
                                role = ?,
                                email = COALESCE(?, email)
                            WHERE username = ?
                        ");
                        $stmt->execute([$password, $role, $email, $username]);
                    } else {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO users (username, password, role, email)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$username, $password, $role, $email]);
                    }
                    
                    $imported++;
                    
                } catch (Exception $e) {
                    $errors[] = "Ошибка при импорте строки: " . $e->getMessage();
                }
            }
            fclose($handle);
        }
        
        return ['imported' => $imported, 'errors' => $errors];
    }
    
    // Импорт из JSON
    private function importFromJSON($filePath) {
        $imported = 0;
        $errors = [];
        
        $jsonContent = file_get_contents($filePath);
        $users = json_decode($jsonContent, true);
        
        if (!is_array($users)) {
            return ['imported' => 0, 'errors' => ['Неверный формат JSON']];
        }
        
        foreach ($users as $user) {
            try {
                if (!isset($user['username']) || !isset($user['password'])) {
                    $errors[] = 'Пропущены обязательные поля';
                    continue;
                }
                
                $username = $user['username'];
                $password = password_hash($user['password'], PASSWORD_DEFAULT);
                $role = isset($user['role']) && in_array($user['role'], ['admin', 'guest']) ? $user['role'] : 'guest';
                $email = isset($user['email']) ? $user['email'] : null;
                
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                
                if ($stmt->rowCount() > 0) {
                    $stmt = $this->pdo->prepare("
                        UPDATE users SET 
                            password = ?,
                            role = ?,
                            email = COALESCE(?, email)
                        WHERE username = ?
                    ");
                    $stmt->execute([$password, $role, $email, $username]);
                } else {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO users (username, password, role, email)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $password, $role, $email]);
                }
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = "Ошибка: " . $e->getMessage();
            }
        }
        
        return ['imported' => $imported, 'errors' => $errors];
    }
    
    // Импорт из Excel (упрощённый вариант через CSV)
    private function importFromExcel($filePath) {
        return [
            'imported' => 0, 
            'errors' => ['Для импорта Excel сначала конвертируйте в CSV или JSON']
        ];
    }
    
    // Получение списка пользователей
    public function getUsers() {
        $stmt = $this->pdo->query("
            SELECT id, username, role, email, created_at, last_login, is_active 
            FROM users 
            ORDER BY 
                CASE WHEN role = 'admin' THEN 0 ELSE 1 END,
                username ASC
        ");
        
        return $stmt->fetchAll();
    }
    
    // Смена пароля
    public function changePassword($username, $oldPassword, $newPassword) {
        $loginResult = $this->login($username, $oldPassword);
        
        if (!$loginResult['success']) {
            return ['success' => false, 'error' => 'Неверный текущий пароль'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("
            UPDATE users SET password = ? WHERE username = ?
        ");
        
        if ($stmt->execute([$hashedPassword, $username])) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Ошибка при смене пароля'];
    }
    
    // Блокировка/разблокировка пользователя
    public function toggleUserStatus($username) {
        if ($username === 'admin') {
            return ['success' => false, 'error' => 'Нельзя заблокировать администратора'];
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE users SET is_active = NOT is_active WHERE username = ?
        ");
        
        if ($stmt->execute([$username])) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Ошибка при изменении статуса'];
    }
    
    /**
 * Удаление пользователя по ID
 */
public function deleteUser($userId) {
    // Проверяем, не является ли пользователь admin
    $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'error' => 'Пользователь не найден'];
    }
    
    if ($user['username'] === 'admin') {
        return ['success' => false, 'error' => 'Нельзя удалить администратора'];
    }
    
    $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$userId])) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => 'Ошибка при удалении пользователя'];
}

/**
 * Обновление данных пользователя
 */
public function updateUser($userId, $data) {
    // Проверяем существование пользователя
    $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['success' => false, 'error' => 'Пользователь не найден'];
    }
    
    $fields = [];
    $params = [];
    
    if (isset($data['email'])) {
        $fields[] = "email = ?";
        $params[] = $data['email'];
    }
    if (isset($data['role'])) {
        if (!in_array($data['role'], ['admin', 'guest', 'player'])) {
            return ['success' => false, 'error' => 'Недопустимая роль'];
        }
        $fields[] = "role = ?";
        $params[] = $data['role'];
    }
    if (isset($data['is_active'])) {
        $fields[] = "is_active = ?";
        $params[] = $data['is_active'] ? 1 : 0;
    }
    if (isset($data['password']) && !empty($data['password'])) {
        $fields[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($fields)) {
        return ['success' => false, 'error' => 'Нет данных для обновления'];
    }
    
    $params[] = $userId; // для WHERE
    
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    
    if ($stmt->execute($params)) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => 'Ошибка при обновлении пользователя'];
}
    
    /**
     * Добавление нового пользователя (используется для регистрации и создания админом)
     */
    public function addUser($username, $password, $email = null, $role = 'guest', $is_active = true) {
        // Проверяем допустимость роли
        if (!in_array($role, ['admin', 'guest', 'player'])) {
            return ['success' => false, 'error' => 'Недопустимая роль'];
        }
        
        // Проверяем уникальность логина
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'Пользователь с таким логином уже существует'];
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, password, role, email, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$username, $hashedPassword, $role, $email, $is_active ? 1 : 0])) {
            return ['success' => true, 'user_id' => $this->pdo->lastInsertId()];
        } else {
            return ['success' => false, 'error' => 'Ошибка при добавлении пользователя'];
        }
    }
}

?>