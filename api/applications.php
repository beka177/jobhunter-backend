<?php
require 'db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// POST: Соискатель создает отклик
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['vacancy_id']) || empty($input['seeker_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Missing fields']);
        exit;
    }

    // Проверка: не откликался ли уже этот человек на эту вакансию?
    $check = $pdo->prepare("SELECT id FROM applications WHERE vacancy_id = ? AND seeker_id = ?");
    $check->execute([$input['vacancy_id'], $input['seeker_id']]);
    if ($check->fetch()) {
        http_response_code(409); // Ошибка 409 Conflict
        echo json_encode(['message' => 'Вы уже откликнулись на эту вакансию']);
        exit;
    }

    // Сохраняем отклик
    $stmt = $pdo->prepare("INSERT INTO applications (vacancy_id, seeker_id) VALUES (?, ?)");
    if ($stmt->execute([$input['vacancy_id'], $input['seeker_id']])) {
        http_response_code(201);
        echo json_encode(['message' => 'Application sent']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
}

// GET: Работодатель смотрит список откликов
elseif ($method === 'GET') {
    $employer_id = $_GET['employer_id'] ?? null;

    if (!$employer_id) {
        http_response_code(400);
        echo json_encode(['message' => 'Employer ID required']);
        exit;
    }

    // Сложный запрос: берем данные отклика + название вакансии + имя соискателя
    $sql = "
        SELECT a.id, a.created_at,
               v.title as vacancy_title,
               u.name as seeker_name, u.email as seeker_email
        FROM applications a
        JOIN vacancies v ON a.vacancy_id = v.id
        JOIN users u ON a.seeker_id = u.id
        WHERE v.employer_id = ?
        ORDER BY a.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employer_id]);
    echo json_encode($stmt->fetchAll());
}
?>