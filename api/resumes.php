<?php
// Файл: resumes.php
// Управление резюме пользователя.
// - GET  ?user_id=...   -> получить резюме (или null если нет)
// - POST -> создать или обновить резюме (JSON в теле)

require 'db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// -------------------------
// GET: получить резюме по user_id
// -------------------------
if ($method === 'GET') {
    $user_id = $_GET['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['message' => 'User ID required']);
        exit;
    }

    // Простое выборка всех полей из таблицы resumes по user_id
    $stmt = $pdo->prepare("SELECT * FROM resumes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $resume = $stmt->fetch();

    // Возвращаем объект резюме или null (если резюме нет). Клиент ожидает именно null.
    echo json_encode($resume ?: null);
}

// -------------------------
// POST: создать или обновить резюме
// -------------------------
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'User ID required']);
        exit;
    }

    // Используется паттерн INSERT ... ON DUPLICATE KEY UPDATE,
    // чтобы одним запросом создать новую запись или обновить существующую.
    // Таблица resumes должна иметь UNIQUE(user_id) или PRIMARY KEY по user_id.
    $sql = "INSERT INTO resumes (
                user_id, surname, first_name, patronymic, gender, city, phone, 
                birthday, citizenship, work_permit, profession,
                education_level, education_institution, education_faculty, 
                education_specialization, education_year, skills
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) ON DUPLICATE KEY UPDATE
                surname=VALUES(surname), first_name=VALUES(first_name), patronymic=VALUES(patronymic),
                gender=VALUES(gender), city=VALUES(city), phone=VALUES(phone),
                birthday=VALUES(birthday), citizenship=VALUES(citizenship), work_permit=VALUES(work_permit),
                profession=VALUES(profession), education_level=VALUES(education_level),
                education_institution=VALUES(education_institution), education_faculty=VALUES(education_faculty),
                education_specialization=VALUES(education_specialization), education_year=VALUES(education_year),
                skills=VALUES(skills)";

    $stmt = $pdo->prepare($sql);
    
    // Важно: порядок элементов в массиве $params должен строго соответствовать порядку
    // местозаполнителей (?) в SQL-выражении выше.
    $params = [
        $input['user_id'], $input['surname'] ?? '', $input['first_name'] ?? '', $input['patronymic'] ?? '',
        $input['gender'] ?? 'male', $input['city'] ?? '', $input['phone'] ?? '',
        $input['birthday'] ?? null, $input['citizenship'] ?? '', $input['work_permit'] ?? '',
        $input['profession'] ?? '', $input['education_level'] ?? '', $input['education_institution'] ?? '',
        $input['education_faculty'] ?? '', $input['education_specialization'] ?? '', $input['education_year'] ?? '',
        $input['skills'] ?? ''
    ];

    if ($stmt->execute($params)) {
        echo json_encode(['message' => 'Resume saved successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
}

?>