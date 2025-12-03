<?php
require 'db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET: Получить резюме пользователя
if ($method === 'GET') {
    $user_id = $_GET['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['message' => 'User ID required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM resumes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $resume = $stmt->fetch();

    echo json_encode($resume ?: null); // Возвращаем null если резюме нет, это нормально
}

// POST: Создать или Обновить резюме
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'User ID required']);
        exit;
    }

    // Используем магию MySQL: INSERT ... ON DUPLICATE KEY UPDATE
    // Если резюме нет -> создаст. Если есть -> обновит.
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
    
    // Внимание: порядок переменных должен совпадать с порядком вопросительных знаков выше!
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