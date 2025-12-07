<?php
require 'db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $employer_id = $_GET['employer_id'] ?? null;
    $seeker_id = $_GET['seeker_id'] ?? null;

    if ($employer_id) {
        // Добавлено поле u.avatar в выборку
        $sql = "
            SELECT a.id, a.created_at, a.status,
                   v.title as vacancy_title,
                   u.name as seeker_name, u.email as seeker_email, u.avatar,
                   r.surname, r.first_name, r.patronymic, 
                   r.gender, r.city, r.phone, r.birthday, r.citizenship, r.work_permit,
                   r.profession, r.skills,
                   r.education_level, r.education_institution, r.education_faculty
            FROM applications a
            JOIN vacancies v ON a.vacancy_id = v.id
            JOIN users u ON a.seeker_id = u.id
            LEFT JOIN resumes r ON u.id = r.user_id
            WHERE v.employer_id = ?
            ORDER BY a.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employer_id]);
        echo json_encode($stmt->fetchAll());
    } 
    elseif ($seeker_id) {
        $sql = "
            SELECT a.id, a.created_at, a.status,
                   v.title as vacancy_title, v.salary,
                   u.name as employer_name
            FROM applications a
            JOIN vacancies v ON a.vacancy_id = v.id
            JOIN users u ON v.employer_id = u.id
            WHERE a.seeker_id = ?
            ORDER BY a.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$seeker_id]);
        echo json_encode($stmt->fetchAll());
    }
}

elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $check = $pdo->prepare("SELECT id FROM applications WHERE vacancy_id = ? AND seeker_id = ?");
    $check->execute([$input['vacancy_id'], $input['seeker_id']]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['message' => 'Вы уже откликнулись на эту вакансию ранее']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO applications (vacancy_id, seeker_id) VALUES (?, ?)");
    $stmt->execute([$input['vacancy_id'], $input['seeker_id']]);
}

elseif ($method === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->execute([$input['status'], $input['id']]);
}
?>