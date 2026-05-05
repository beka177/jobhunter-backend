<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("SELECT v.*, u.name as employer_name FROM vacancies v JOIN users u ON v.employer_id = u.id WHERE v.id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
    } else {
        $stmt = $pdo->query("SELECT v.*, u.name as employer_name FROM vacancies v JOIN users u ON v.employer_id = u.id ORDER BY v.created_at DESC");
        echo json_encode($stmt->fetchAll());
    }
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $employer_id = $data['employer_id'] ?? null;
    $title = $data['title'] ?? null;
    $description = $data['description'] ?? null;
    $salary = $data['salary'] ?? null;
    $image = $data['image'] ?? null;
    $city = $data['city'] ?? null;

    if (!$employer_id || !$title || !$description) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO vacancies (employer_id, title, description, salary, image, city) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$employer_id, $title, $description, $salary, $image, $city]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $title = $data['title'] ?? null;
    $description = $data['description'] ?? null;
    $salary = $data['salary'] ?? null;
    $image = $data['image'] ?? null;
    $city = $data['city'] ?? null;

    if (!$id || !$title || !$description) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE vacancies SET title = ?, description = ?, salary = ?, image = ?, city = ? WHERE id = ?");
    $stmt->execute([$title, $description, $salary, $image, $city, $id]);
    echo json_encode(['success' => true]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM vacancies WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}
?>
