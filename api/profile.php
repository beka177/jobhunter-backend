<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $name = $data['name'] ?? null;
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    $avatar = $data['avatar'] ?? null;

    if (!$id || !$name || !$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // Check if email is taken by another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already in use']);
        exit;
    }

    try {
        if ($password) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$name, $email, $hashedPassword, $avatar, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$name, $email, $avatar, $id]);
        }

        // Fetch updated user data
        $stmt = $pdo->prepare("SELECT id, name, email, role, avatar FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        echo json_encode(['success' => true, 'user' => $user]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}
?>
