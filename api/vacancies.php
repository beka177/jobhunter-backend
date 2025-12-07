<?php
require 'db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET
if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT v.*, u.name as employer_name 
        FROM vacancies v 
        JOIN users u ON v.employer_id = u.id 
        ORDER BY v.created_at DESC
    ");
    echo json_encode($stmt->fetchAll());
}

// POST: Создание вакансии
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['title']) || empty($input['employer_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Missing fields']);
        exit;
    }

    $image = $input['image'] ?? null; // Получаем картинку

    $stmt = $pdo->prepare("INSERT INTO vacancies (employer_id, title, salary, description, image) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$input['employer_id'], $input['title'], $input['salary'], $input['description'], $image])) {
        http_response_code(201);
        echo json_encode(['message' => 'Vacancy created']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
}

// DELETE
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    $stmt = $pdo->prepare("DELETE FROM vacancies WHERE id = ?");
    $stmt->execute([$id]);
}
?>