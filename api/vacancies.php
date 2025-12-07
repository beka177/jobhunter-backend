<?php
require 'db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id) {
        // ЛОГИКА ДЛЯ ОДНОЙ ВАКАНСИИ (Этого не хватало!)
        $stmt = $pdo->prepare("
            SELECT v.*, u.name as employer_name, u.avatar as employer_avatar
            FROM vacancies v 
            JOIN users u ON v.employer_id = u.id 
            WHERE v.id = ?
        ");
        $stmt->execute([$id]);
        $vacancy = $stmt->fetch();
        
        if ($vacancy) {
            echo json_encode($vacancy);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Vacancy not found']);
        }
    } else {
        // ЛОГИКА ДЛЯ СПИСКА
        $stmt = $pdo->query("
            SELECT v.*, u.name as employer_name, u.avatar as employer_avatar
            FROM vacancies v 
            JOIN users u ON v.employer_id = u.id 
            ORDER BY v.created_at DESC
        ");
        echo json_encode($stmt->fetchAll());
    }
}

// POST: Создание
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['title']) || empty($input['employer_id'])) {
        http_response_code(400); echo json_encode(['message' => 'Missing fields']); exit;
    }
    $image = $input['image'] ?? null; 
    $stmt = $pdo->prepare("INSERT INTO vacancies (employer_id, title, salary, description, image) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$input['employer_id'], $input['title'], $input['salary'], $input['description'], $image])) {
        http_response_code(201); echo json_encode(['message' => 'Created']);
    } else {
        http_response_code(500); echo json_encode(['message' => 'Error']);
    }
}

// DELETE
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    $stmt = $pdo->prepare("DELETE FROM vacancies WHERE id = ?");
    $stmt->execute([$id]);
}
?>