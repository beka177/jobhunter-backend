<?php
require 'db.php';

// Разрешаем запросы с любого домена (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header('Content-Type: application/json');

// Обработка предварительных запросов (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Получение одной вакансии по ID или всех сразу
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT v.*, u.name as employer_name FROM vacancies v JOIN users u ON v.employer_id = u.id WHERE v.id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
        } else {
            $stmt = $pdo->query("SELECT v.*, u.name as employer_name FROM vacancies v JOIN users u ON v.employer_id = u.id ORDER BY v.created_at DESC");
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        // Создание новой вакансии
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['title']) || empty($data['employer_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Название и ID работодателя обязательны"]);
            break;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO vacancies (employer_id, title, salary, description, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['employer_id'],
                $data['title'],
                $data['salary'],
                $data['description'],
                $data['image'] ?? null
            ]);
            echo json_encode(["message" => "Вакансия создана", "id" => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Ошибка БД: " . $e->getMessage()]);
        }
        break;

    case 'PUT':
        // РЕДАКТИРОВАНИЕ существующей вакансии
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['id'])) {
            http_response_code(400);
            echo json_encode(["message" => "ID вакансии обязателен для обновления"]);
            break;
        }

        try {
            $stmt = $pdo->prepare("UPDATE vacancies SET title = ?, salary = ?, description = ?, image = ? WHERE id = ?");
            $success = $stmt->execute([
                $data['title'],
                $data['salary'],
                $data['description'],
                $data['image'] ?? null,
                $data['id']
            ]);
            
            if ($success) {
                echo json_encode(["message" => "Вакансия успешно обновлена"]);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Вакансия не найдена"]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Ошибка при обновлении: " . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Удаление вакансии
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("DELETE FROM vacancies WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(["message" => "Вакансия удалена"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID не указан"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Метод не поддерживается"]);
        break;
}
?>