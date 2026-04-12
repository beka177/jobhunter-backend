<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Автоматическое добавление колонки banned_until, если её нет
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN banned_until DATETIME NULL");
} catch (PDOException $e) {
    // Игнорируем ошибку, если колонка уже существует
}

// В реальном приложении здесь должна быть проверка токена/сессии администратора.
// Для упрощения мы просто проверяем переданный admin_id
$admin_id = $_GET['admin_id'] ?? null;
if (!$admin_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($action === 'create_admin') {
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!$name || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required']);
            exit;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$name, $email, $hashedPassword]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
        }
        exit;
    } elseif ($action === 'ban_user') {
        $id = $data['id'] ?? null;
        $duration = $data['duration'] ?? null; // '1_day', '1_week', '1_month', 'permanent', 'unban'
        
        if (!$id || !$duration) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        
        if ($id == $admin_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot ban yourself']);
            exit;
        }

        $banned_until = null;
        if ($duration === '1_day') $banned_until = date('Y-m-d H:i:s', strtotime('+1 day'));
        elseif ($duration === '1_week') $banned_until = date('Y-m-d H:i:s', strtotime('+1 week'));
        elseif ($duration === '1_month') $banned_until = date('Y-m-d H:i:s', strtotime('+1 month'));
        elseif ($duration === 'permanent') $banned_until = '9999-12-31 23:59:59';
        
        $stmt = $pdo->prepare("UPDATE users SET banned_until = ? WHERE id = ?");
        $stmt->execute([$banned_until, $id]);
        echo json_encode(['success' => true]);
        exit;
    }
} elseif ($method === 'GET') {
    if ($action === 'stats') {
        $stats = [];
        $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['vacancies'] = $pdo->query("SELECT COUNT(*) FROM vacancies")->fetchColumn();
        $stats['applications'] = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        echo json_encode($stats);
    } elseif ($action === 'users') {
        $stmt = $pdo->query("SELECT id, name, email, role, created_at, banned_until FROM users ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'vacancies') {
        $stmt = $pdo->query("SELECT v.id, v.title, v.employer_id, v.created_at, u.name as employer_name FROM vacancies v JOIN users u ON v.employer_id = u.id ORDER BY v.created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} elseif ($method === 'DELETE') {
    if ($action === 'user') {
        $id = $_GET['id'] ?? null;
        if ($id == $admin_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete yourself']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'vacancy') {
        $id = $_GET['id'] ?? null;
        $stmt = $pdo->prepare("DELETE FROM vacancies WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}
?>
