<?php
// Файл: help.php
// Возвращает справочные статьи (только чтение).
// GET /help.php -> массив статей help_articles (по created_at DESC)

require 'db.php';
header('Content-Type: application/json');

// В текущей реализации разрешён только метод GET — сайт использует эти статьи для страницы помощи.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM help_articles ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        // Если произошла ошибка доступа к БД — возвращаем 500
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
} else {
    // Для простоты администрирование статей делается вручную в PHPMyAdmin.
    // В реальной системе можно добавить отдельный защищённый CRUD с авторизацией.
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed. Use PHPMyAdmin to manage articles.']);
}

?>