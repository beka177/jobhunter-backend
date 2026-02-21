<?php
header("Access-Control-Allow-Origin: *");
// Разрешаем запросы с любого источника (CORS) — удобно при разработке локально.
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// Разрешаем заголовки, которые может отправлять клиент (например, Content-Type для JSON).

// Обработка предварительного запроса (Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = 'localhost';
$db   = 'jobhunter';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
// DSN (Data Source Name) — строка подключения, которая говорит PDO, куда подключаться.
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Если произойдёт ошибка работы с БД, будет выброшено исключение — удобно для отладки.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // По умолчанию результаты запросов будут возвращаться как ассоциативные массивы.
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Выключаем эмуляцию подготовленных выражений, чтобы использовать реальные prepared statements.
];

try {
    // Создаём новый объект PDO — это подключение к базе данных.
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Если подключение не удалось, вернём код 500 и сообщение об ошибке в формате JSON.
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    // Завершаем выполнение скрипта, так как без БД дальше работать нельзя.
    exit();
}
?>