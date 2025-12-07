<?php
require 'db.php';
header('Content-Type: application/json');

// Разрешаем только GET запросы (чтение)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM help_articles ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
} else {
    // Если кто-то пытается отправить POST/DELETE через сайт, запрещаем
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed. Use PHPMyAdmin to manage articles.']);
}
?>