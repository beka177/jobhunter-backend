<?php
require_once 'db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Делаем связку таблиц (JOIN)
        $sql = "
            SELECT 
                u.id, 
                u.name, 
                u.email, 
                u.avatar, 
                u.created_at,
                r.first_name, 
                r.surname, 
                r.gender, 
                r.birthday, 
                r.city, 
                r.citizenship, 
                r.phone, 
                r.education_level, 
                r.education_institution, 
                r.education_faculty, 
                r.education_specialization as education_specialty, 
                r.education_year, 
                r.profession, 
                r.skills
            FROM users u
            LEFT JOIN resumes r ON u.id = r.user_id
            WHERE u.role = 'seeker'
            ORDER BY u.created_at DESC
        ";
        $stmt = $pdo->query($sql);
        $seekers = $stmt->fetchAll();
        echo json_encode($seekers);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>