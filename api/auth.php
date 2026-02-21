<?php

require 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Читаем JSON тело запроса и декодируем его в ассоциативный массив
$input = json_decode(file_get_contents('php://input'), true);

// ---------------------------
// Регистрация пользователя
// ---------------------------
if ($action === 'register') {
    // Ожидаемые поля в $input:
    // - name  (string)  — имя пользователя (обязательно)
    // - email (string)  — уникальный email (обязательно)
    // - password (string) — пароль (обязательно)
    // - role (string) — роль: 'seeker' или 'employer' (опционально, по умолчанию 'seeker')
    // - avatar (string|null) — url или base64 аватара (опционально)

    // Базовая валидация: проверяем, что обязательные поля есть
    if (!$input || empty($input['email']) || empty($input['password']) || empty($input['name'])) {
        // 400 — неверный запрос: не все обязательные поля переданы
        http_response_code(400);
        echo json_encode(['message' => 'Заполните все поля']);
        exit;
    }

    // Проверяем, не занят ли уже email (чтобы не было дубликатов)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        // 409 Conflict — ресурс с таким уникальным ключом уже существует
        http_response_code(409);
        echo json_encode(['message' => 'Email уже занят']);
        exit;
    }

    // Хешируем пароль безопасным алгоритмом PHP (bcrypt/argon2 в зависимости от конфигурации)
    // password_hash автоматически выбирает безопасные параметры — это лучше, чем хранить пароль в явном виде.
    $hash = password_hash($input['password'], PASSWORD_DEFAULT);

    // Роль по умолчанию — соискатель
    $role = $input['role'] ?? 'seeker';
    // Аватар может быть nullable — в простом приложении это может быть URL или base64
    $avatar = $input['avatar'] ?? null;

    // Вставляем пользователя в таблицу `users` с помощью подготовленного выражения.
    // Подготовленные выражения защищают от SQL-инъекций.
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, avatar) VALUES (?, ?, ?, ?, ?)");

    // Выполняем запрос и возвращаем простой ответ
    if ($stmt->execute([$input['name'], $input['email'], $hash, $role, $avatar])) {
        // Успешная регистрация
        // Замечание по улучшению: здесь удобно сразу возвращать созданного пользователя
        // и сгенерированный токен (JWT). В этой учебной реализации возвращается только сообщение.
        echo json_encode(['message' => 'Регистрация успешна']);
    } else {
        // 500 — внутренняя ошибка сервера при попытке вставки
        http_response_code(500);
        echo json_encode(['message' => 'Ошибка сервера']);
    }

// ---------------------------
// Вход (аутентификация)
// ---------------------------
} elseif ($action === 'login') {
    // Ожидаемые поля в $input:
    // - email (string)
    // - password (string)

    if (!$input || empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Введите email и пароль']);
        exit;
    }

    // Получаем пользователя по email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch();

    // Проверяем наличие пользователя и сравниваем хеши паролей
    // password_verify корректно сравнивает хеш и исходный пароль
    if ($user && password_verify($input['password'], $user['password'])) {
        // Удаляем поле password из массива, чтобы случайно не вернуть хеш клиенту
        unset($user['password']);

        // В учебной реализации возвращается 'fake' токен. В реальном приложении нужно:
        // - генерировать JWT или другой токен с подписью
        // - хранить срок жизни токена и механизмы отзыва
        // - использовать HTTPS и httpOnly cookie или Authorization header
        echo json_encode([
            'message' => 'Вход успешен',
            'user' => $user,
            'token' => 'fake'
        ]);
    } else {
        // 401 Unauthorized — неверные учётные данные
        http_response_code(401);
        echo json_encode(['message' => 'Неверный логин или пароль']);
    }

} else {
    // Если действие не распознано — возвращаем 400 или пустой ответ
    http_response_code(400);
    echo json_encode(['message' => 'Укажите действие: ?action=register или ?action=login']);
}

?>