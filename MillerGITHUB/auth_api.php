<?php
// auth_api.php - API для авторизации
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка OPTIONS запросов (для CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'users.php';

try {
    $userManager = new UserManager();
} catch (Exception $e) {
    sendJsonResponse(false, null, 'Failed to initialize UserManager: ' . $e->getMessage());
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(false, null, 'Method not allowed');
                break;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['username']) || !isset($input['password'])) {
                sendJsonResponse(false, null, 'Username and password required');
                break;
            }
            
            $result = $userManager->login($input['username'], $input['password']);
            
            if ($result['success']) {
                // Создаём сессию
                session_start();
                $_SESSION['user'] = $result['user'];
                $_SESSION['logged_in'] = true;
                
                sendJsonResponse(true, [
                    'user' => $result['user'],
                    'session_id' => session_id()
                ]);
            } else {
                sendJsonResponse(false, null, $result['error']);
            }
            break;
            
        case 'logout':
            session_start();
            session_destroy();
            sendJsonResponse(true, ['message' => 'Logged out successfully']);
            break;
        case 'register':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, null, 'Method not allowed');
        break;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $email = isset($input['email']) ? trim($input['email']) : null;
    
    if (empty($username) || empty($password)) {
        sendJsonResponse(false, null, 'Логин и пароль обязательны');
        break;
    }
    
    // Регистрация только с ролью guest
    $result = $userManager->addUser($username, $password, $email, 'guest', true);
    
    if ($result['success']) {
        sendJsonResponse(true, ['message' => 'Регистрация успешна', 'user_id' => $result['user_id']]);
    } else {
        sendJsonResponse(false, null, $result['error']);
    }
    break;
            
        case 'check_session':
            session_start();
            if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
                sendJsonResponse(true, ['user' => $_SESSION['user']]);
            } else {
                sendJsonResponse(false, null, 'Not logged in');
            }
            break;
            
        case 'import_users':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(false, null, 'Method not allowed');
                break;
            }
            
            // Проверяем права администратора
            session_start();
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
                sendJsonResponse(false, null, 'Access denied');
                break;
            }
            
            if (!isset($_FILES['file'])) {
                sendJsonResponse(false, null, 'No file uploaded');
                break;
            }
            
            $file = $_FILES['file'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Проверяем расширение
            if (!in_array($fileExt, ['csv', 'json', 'xls', 'xlsx'])) {
                sendJsonResponse(false, null, 'Invalid file format. Use CSV, JSON, or Excel');
                break;
            }
            
            // Сохраняем файл
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadPath = $uploadDir . time() . '_' . basename($file['name']);
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $result = $userManager->importUsersFromFile($uploadPath, $fileExt);
                
                if ($result['success']) {
                    sendJsonResponse(true, [
                        'imported' => $result['imported'],
                        'errors' => $result['errors']
                    ]);
                } else {
                    sendJsonResponse(false, null, $result['error']);
                }
                
                // Удаляем файл после обработки
                unlink($uploadPath);
            } else {
                sendJsonResponse(false, null, 'Failed to upload file');
            }
            break;
            
        case 'get_users':
            // Проверяем права администратора
            session_start();
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
                sendJsonResponse(false, null, 'Access denied');
                break;
            }
            
            $users = $userManager->getUsers();
            sendJsonResponse(true, ['users' => $users]);
            break;
        case 'add_user':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, null, 'Method not allowed');
        break;
    }
    
    session_start();
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        sendJsonResponse(false, null, 'Access denied');
        break;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $email = isset($input['email']) ? trim($input['email']) : null;
    $role = isset($input['role']) ? $input['role'] : 'guest';
    $is_active = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    
    if (empty($username) || empty($password)) {
        sendJsonResponse(false, null, 'Username and password required');
        break;
    }
    
    $result = $userManager->addUser($username, $password, $email, $role, $is_active);
    
    if ($result['success']) {
        sendJsonResponse(true, ['user_id' => $result['user_id']]);
    } else {
        sendJsonResponse(false, null, $result['error']);
    }
    break;
            
        case 'change_password':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(false, null, 'Method not allowed');
                break;
            }
            
            session_start();
            if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
                sendJsonResponse(false, null, 'Not logged in');
                break;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['old_password']) || !isset($input['new_password'])) {
                sendJsonResponse(false, null, 'Old and new password required');
                break;
            }
            
            $result = $userManager->changePassword(
                $_SESSION['user']['username'],
                $input['old_password'],
                $input['new_password']
            );
            
            if ($result['success']) {
                sendJsonResponse(true, ['message' => 'Password changed successfully']);
            } else {
                sendJsonResponse(false, null, $result['error']);
            }
            break;
            
        case 'toggle_user':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(false, null, 'Method not allowed');
                break;
            }
            
            session_start();
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
                sendJsonResponse(false, null, 'Access denied');
                break;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['username'])) {
                sendJsonResponse(false, null, 'Username required');
                break;
            }
            
            $result = $userManager->toggleUserStatus($input['username']);
            
            if ($result['success']) {
                sendJsonResponse(true, ['message' => 'User status updated']);
            } else {
                sendJsonResponse(false, null, $result['error']);
            }
            break;
            
        case 'clear_users':
            session_start();
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
                sendJsonResponse(false, null, 'Access denied');
                break;
            }
            
            try {
                require_once 'users.php';
                $pdo = getDB();
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE username != 'admin'");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                
                sendJsonResponse(true, ['deleted' => $deleted]);
            } catch (Exception $e) {
                sendJsonResponse(false, null, $e->getMessage());
            }
            break;
        case 'delete_user':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, null, 'Method not allowed');
        break;
    }
    session_start();
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        sendJsonResponse(false, null, 'Access denied');
        break;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['user_id'])) {
        sendJsonResponse(false, null, 'User ID required');
        break;
    }
    $result = $userManager->deleteUser($input['user_id']);
    if ($result['success']) {
        sendJsonResponse(true, ['message' => 'User deleted successfully']);
    } else {
        sendJsonResponse(false, null, $result['error']);
    }
    break;

case 'update_user':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, null, 'Method not allowed');
        break;
    }
    session_start();
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        sendJsonResponse(false, null, 'Access denied');
        break;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['user_id'])) {
        sendJsonResponse(false, null, 'User ID required');
        break;
    }
    // Извлекаем возможные поля для обновления
    $updateData = [];
    if (isset($input['email'])) $updateData['email'] = $input['email'];
    if (isset($input['role'])) $updateData['role'] = $input['role'];
    if (isset($input['is_active'])) $updateData['is_active'] = $input['is_active'];
    if (isset($input['password']) && !empty($input['password'])) {
        $updateData['password'] = $input['password'];
    }
    $result = $userManager->updateUser($input['user_id'], $updateData);
    if ($result['success']) {
        sendJsonResponse(true, ['message' => 'User updated successfully']);
    } else {
        sendJsonResponse(false, null, $result['error']);
    }
    break;
            
        default:
            sendJsonResponse(false, null, 'Unknown action');
    }
} catch (Exception $e) {
    sendJsonResponse(false, null, 'Server error: ' . $e->getMessage());
}

function sendJsonResponse($success, $data = null, $error = null) {
    // Очищаем буфер вывода
    if (ob_get_length()) ob_clean();
    
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>