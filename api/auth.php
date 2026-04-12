<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'login';

    if ($action === 'register') {
        $name = $data['name'];
        $email = $data['email'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = $data['role'];

        // Запрещаем регистрацию админов через обычную форму
        if ($role === 'admin') {
            $role = 'seeker'; 
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role]);
            echo json_encode(['success' => true, 'user' => ['id' => $pdo->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => $role]]);
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
        }
    } else {
        $email = $data['email'];
        $password = $data['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['banned_until'] !== null) {
                $banned_until = new DateTime($user['banned_until']);
                $now = new DateTime();
                if ($banned_until > $now) {
                    http_response_code(403);
                    $format = $banned_until->format('Y') == '9999' ? 'навсегда' : 'до ' . $banned_until->format('d.m.Y H:i');
                    echo json_encode(['error' => 'Ваш аккаунт заблокирован ' . $format]);
                    exit;
                } else {
                    // Бан истек, очищаем
                    $pdo->prepare("UPDATE users SET banned_until = NULL WHERE id = ?")->execute([$user['id']]);
                    $user['banned_until'] = null;
                }
            }

            unset($user['password']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    }
}
?>
