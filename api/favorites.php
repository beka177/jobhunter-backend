<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'GET') {
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT v.*, u.name as employer_name 
        FROM favorites f
        JOIN vacancies v ON f.vacancy_id = v.id
        JOIN users u ON v.employer_id = u.id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetchAll());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? null;
    $vacancy_id = $data['vacancy_id'] ?? null;

    if (!$user_id || !$vacancy_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, vacancy_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $vacancy_id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            echo json_encode(['success' => true, 'message' => 'Already in favorites']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

if ($method === 'DELETE') {
    $user_id = $_GET['user_id'] ?? null;
    $vacancy_id = $_GET['vacancy_id'] ?? null;

    if (!$user_id || !$vacancy_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND vacancy_id = ?");
    $stmt->execute([$user_id, $vacancy_id]);
    echo json_encode(['success' => true]);
}
?>
