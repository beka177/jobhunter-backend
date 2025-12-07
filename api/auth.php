<?php
require 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

if ($action === 'register') {
    if (!$input || empty($input['email']) || empty($input['password']) || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Заполните все поля']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['message' => 'Email уже занят']);
        exit;
    }

    $hash = password_hash($input['password'], PASSWORD_DEFAULT);
    $role = $input['role'] ?? 'seeker';
    $avatar = $input['avatar'] ?? null; // Получаем аватар

    // Вставляем аватар в базу
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, avatar) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$input['name'], $input['email'], $hash, $role, $avatar])) {
        echo json_encode(['message' => 'Регистрация успешна']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Ошибка сервера']);
    }

} elseif ($action === 'login') {
    if (!$input || empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Введите email и пароль']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch();

    if ($user && password_verify($input['password'], $user['password'])) {
        unset($user['password']);
        echo json_encode([
            'message' => 'Вход успешен',
            'user' => $user,
            'token' => 'fake' 
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['message' => 'Неверный логин или пароль']);
    }
}
?>